<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024, 2025, 2026 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\PersistentCGIKeys;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\EmailAddressService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Toolkit\Doctrine\ORM\EntitySerializer\EntityArrayAdapter;

/** Validation controller for some personal input fields. */
#[TSAttributes\TypeScript]
class MusicianValidationController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const END_POINT = 'validate/musicians';

  protected string $dataPrefix = '';

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    private EmailAddressService $emailAddressService,
    private GeoCodingService $geoCodingService,
    private PhoneNumberService $phoneNumberService,
    protected EntityManager $entityManager,
    protected IL10N $l,
    protected LoggerInterface $logger,
    protected PHPMyEdit $pme,
  ) {
    parent::__construct($appName, $request);

    $this->dataPrefix = $this->request->getParam(PersistentCGIKeys::DATA_PREFIX)['musicians'] ?? '';
  }

  /**
   * Return the value of the PME legacy parameter corresponding to the given
   * name.
   *
   * @param string $name
   *
   * @return string
   */
  private function requestParameter(string $name):string
  {
    return Util::normalizeSpaces(
      $this->request->getParam($this->pme->cgiDataName($this->dataPrefix . $name), ''),
    );
  }

  /**
   * @param string|EnumMusicianValidationTopic $topic What to validate.
   *
   * @param null|string|EnumMusicianValidationSubTopic $subTopic Optional subtopic.
   *
   * @param string $failure If literal 'error' return
   * Http::STATUS_BAD_RESPONSE on validation error, otherwise Http::STATUS_OK.
   *
   * @return DataResponse|JSONResponse
   *
   * @throws Exceptions\EnduserNotificationException
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::END_POINT . '/{topic}/{subTopic}', defaults: ['subTopic' => ''])]
  public function validate(
    string|EnumMusicianValidationTopic $topic,
    null|string|EnumMusicianValidationSubTopic $subTopic = null,
    string $failure = 'notice',
  ): DataResponse|JSONResponse {
    $topic = EnumMusicianValidationTopic::get($topic);
    $subTopic = $subTopic ? EnumMusicianValidationSubTopic::get($subTopic) : null;
    $message = [];
    switch ($topic) {
      case EnumMusicianValidationTopic::PHONE:
        $numbers = [
          'mobile' => [
            'number' => $this->requestParameter('mobile_phone'),
            'isMobile' => false,
            'valid' => false,
            'meta' => false,
          ],
          'fixed' => [
            'number' => $this->requestParameter('fixed_line_phone'),
            'isMobile' => false,
            'valid' => false,
            'meta' => false,
          ],
        ];

        $fixed = &$numbers['fixed'];
        $mobile = &$numbers['mobile'];

        // validata phone numbers
        foreach ($numbers as &$number) {
          try {
            if ($this->phoneNumberService->validate($number['number'])) {
              $number['number'] = $this->phoneNumberService->format();
              $number['meta'] = $this->phoneNumberService->metaData();
              $number['isMobile'] = $this->phoneNumberService->isMobile();
              $number['valid'] = true;
            }
          } catch (\libphonenumber\NumberParseException $e) {
            // empty
          }
          if (!$number['valid'] && !empty($number['number'])) {
            $message[] = $this->l->t(
              'The phone number %s does not appear to be a valid phone number. ',
              [ $number['number'], ]
            );
          }
        }

        $this->logDebug(print_r($numbers, true));

        if (!$fixed['valid'] && $mobile['valid'] && !$mobile['isMobile']) {
          $tmp = $fixed;
          $fixed = $mobile;
          $mobile = $tmp;
          $message[] = $this->l->t(
            'This (%s) is a fixed line phone number, injecting it in the correct column.',
            [ $fixed['number'] ]
          );
        }
        if (!$mobile['valid'] && $fixed['valid'] && $fixed['isMobile']) {
          $tmp = $mobile;
          $mobile = $fixed;
          $fixed = $tmp;
          $message[] = $this->l->t(
            'This (%s) is a mobile phone number, injecting it in the correct column.',
            [ $mobile['number'] ]
          );
        }
        if (!empty($mobile['number']) && !empty($fixed['number']) && !$mobile['isMobile'] && $fixed['isMobile']) {
          $tmp = $fixed;
          $fixed = $mobile;
          $mobile = $tmp;
        } elseif ($mobile['valid'] && !$mobile['isMobile']) {
          $message[] = $this->l->t(
            'The phone number %s does not appear to be a mobile phone number. ',
            [ $mobile['number'] ]
          );
        }

        return new DTO\PhoneNumberValidationResponse(
          messages: $message,
          mobilePhone: $mobile['number'],
          mobileMeta: nl2br($mobile['meta']),
          fixedLinePhone: $fixed['number'],
          fixedLineMeta: nl2br($fixed['meta']),
        )->response();

      case EnumMusicianValidationTopic::EMAIL:
        $email = $this->requestParameter('email');

        if (empty($email)) {
          return new DTO\EmailValidationResponse(
            messages: [ $this->t->t('Submitted email is empty') ],
            email: '',
          )->response($failure ? Http::STATUS_BAD_REQUEST : Http::STATUS_OK);
        }

        try {
          $emailArray = $this->emailAddressService->parseAddressString($email);
        } catch (Exceptions\EnduserNotificationException $e) {
          $message = $e->getMessage();
        }

        if (empty($message)) {
          $email = implode(', ', array_keys($emailArray));
          $messages = [];
          $statusCode = Http::STATUS_OK;
        } else {
          $messages = [ $message ];
          $statusCode = $failure ? Http::STATUS_BAD_REQUEST : Http::STATUS_OK;
        }

        return new DTO\EmailValidationResponse(
          messages: $messages,
          email: $email,
          details: $emailArray ?? [],
        )->response($statusCode);

      case EnumMusicianValidationTopic::AUTOCOMPLETE:
        $country = $this->requestParameter('country');
        $city = $this->requestParameter('city');
        $street = $this->requestParameter('street');
        $postalCode = $this->requestParameter('postal_code');
        switch ($subTopic) {
          case EnumMusicianValidationSubTopic::AUTOCOMPLETE_STREET:
            // separate street data into its own request as the OverPass API is slow.
            $streets = $this->geoCodingService->autoCompleteStreet($country, $city, $postalCode);

            sort($streets, SORT_LOCALE_STRING);
            $streets = empty($city) && empty($postalCode)
              ? []
            : array_values(array_unique($streets));

            return self::dataResponse([
              'streets' => $streets,
            ]);
            break;
          case EnumMusicianValidationSubTopic::AUTOCOMPLETE_PLACE:
            // compute auto-comlete for country, city, postal-code in one run
            $locations = $this->geoCodingService->cachedLocations($postalCode, $city, $country);
            if (count($locations) == 0 && ($city || $postalCode)) {
              // retry remotely with given country
              $locations = $this->geoCodingService->remoteLocations($postalCode, $city, $country);
              if (count($locations) == 0) {
                // retry without country, i.e. on same continent
                $locations = $this->geoCodingService->cachedLocations($postalCode, $city, null);
                if (count($locations) == 0) {
                  // still no luck: try a world search
                  $locations = $this->geoCodingService->cachedLocations($postalCode, $city, '%');
                  if (count($locations) == 0) {
                    // retry with remote service, on this continent ...
                    $locations = $this->geoCodingService->remoteLocations($postalCode, $city, null);
                  }
                }
              }
            }

            $cities = [];
            $postalCodes = [];
            $countries = [];
            foreach ($locations as $location) {
              $cities[] = $location['Name'];
              $postalCodes[] = $location['PostalCode'];
              $countries[] = $location['Country'];
            };
            sort($cities, SORT_LOCALE_STRING);
            sort($postalCodes, SORT_LOCALE_STRING);
            sort($countries);

            $cities = array_values(array_unique($cities, SORT_LOCALE_STRING));
            $postalCodes = array_values(array_unique($postalCodes, SORT_LOCALE_STRING));
            $countries = array_values(array_unique($countries));

            return self::dataResponse([
              'cities' => $cities,
              'postalCodes' => $postalCodes,
              'countries' => $countries,
            ]);
            break;
          default:
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Unsupported auto-complete request for "%s".', $subTopic ?? 'null'),
            );
        }
        break;
      case EnumMusicianValidationTopic::DUPLICATES:
        $nameCriteria = [];
        $surName = $this->requestParameter('sur_name');
        if (!empty($surName)) {
          $nameCriteria[] = [ 'surName' => $surName ];
        }
        $firstName = $this->requestParameter('first_name');
        if (!empty($firstName)) {
          $nameCriteria[] = [ 'firstName' => $firstName ];
        }
        if (!empty($nameCriteria)) {
          array_unshift($nameCriteria, [ '(&' => true ]);
          $nameCriteria[] = [ ')' => true ];
        }
        $commCriteria = [];
        $email = $this->requestParameter('email');
        if (!empty($email)) {
          $commCriteria[] = [ 'email' => $email ];
        }
        $fixedLinePhone = $this->requestParameter('fixed_line_phone');
        if (!empty($fixedLinePhone)) {
          $commCriteria[] = [ 'fixedLinePhone' => $fixedLinePhone ];
        }
        $mobilePhone = $this->requestParameter('mobile_phone');
        if (!empty($mobilePhone)) {
          $commCriteria[] = [ 'mobilePhone' => $mobilePhone ];
        }
        if (!empty($commCriteria)) {
          array_unshift($commCriteria, [ '(|' => true ]);
          $commCriteria[] = [ ')' => true ];
        }
        $usableAddress = 0;
        $addressCriteria = [];
        $street = $this->requestParameter('street');
        if (!empty($street)) {
          self::matchOrNull('street', $street, $addressCriteria);
          $usableAddress ++;
        }
        $streetNumber = $this->requestParameter('street_number');
        if (!empty($streetNumber)) {
          self::matchOrNull('streetNumber', $streetNumber, $addressCriteria);
        }
        $postalCode = $this->requestParameter('postal_code');
        if (!empty($postalCode)) {
          self::matchOrNull('postalCode', $postalCode, $addressCriteria);
        }
        $city = $this->requestParameter('city');
        if (!empty($city)) {
          self::matchOrNull('city', $city, $addressCriteria);
          $usableAddress ++;
        }
        $country = $this->requestParameter('country');
        if (!empty($country)) {
          self::matchOrNull('country', $country, $addressCriteria);
        }
        if ($usableAddress == 2) { // have street and city at least
          array_unshift($addressCriteria, [ '(&' => true ]);
          $addressCriteria[] = [ ')' => true ];
        } else {
          $addressCriteria = [];
        }

        $criteria = array_merge($nameCriteria, $commCriteria, $addressCriteria);
        if (empty($criteria)) {
          return new DTO\DuplicateMusiciansResponse(
            messages: [],
            duplicates: [],
          )->response();
        }
        array_unshift($criteria, [ '(|' => true ]);
        $criteria[] = [ ')' => true ];

        $this->logDebug('CRITERIA ' . print_r($criteria, true));

        $musicians = $this->getDatabaseRepository(Entities\Musician::class)
          ->findBy($criteria, [ 'surName' => 'ASC', 'firstName' => 'ASC' ]);

        $duplicateNames = '';
        $duplicates = [];
        /** @var Entities\Musician $musician */
        foreach ($musicians as $musician) {
          $musicianId = $musician->getId();
          $duplicateNames .= $musician['firstName'].' '.$musician['surName']." (Id = ".$musician['id'].")"."\n";

          // Compute the "severity" of the match, kind of hacky.

          // email address or any of the two phone numbers and first name matches
          // -> treat as exact match, 100 %

          // first-name and city and street and street-number match
          // -> 100 %

          // names match
          // -> 50 %

          // any of the email address or phone numbers match
          // -> 50 % (we have different participants which share their comms)

          $duplicatesProbability = 0.0;

          $commsMatch = (
            (!empty($email) && $email == $musician->getEmail())
            || (!empty($fixedLinePhone) && $fixedLinePhone == $musician->getFixedLinePhone())
            || (!empty($mobilePhone) && $mobilePhone == $musician->getMobilePhone()));

          $addressMatch = (
            (!empty($street) && !empty($streetNumber) && !empty($city))
            && $street == $musician->getStreet()
            && $streetNumber == $musician->getStreetNumber()
            && $city == $musician->getCity());

          $firstNameMatch = !empty($firstName) && $firstName == $musician->getFirstName();

          $surNameMatch = !empty($surName) && $surName == $musician->getSurName();

          $namesMatch = $firstNameMatch && $surNameMatch;

          if ($firstNameMatch) {
            if ($commsMatch) {
              $duplicatesProbability = 1.0; // treat as exact match
            }
            if ($addressMatch) {
              $duplicatesProbability = 1.0; // treat as exact match
            }
          }

          if ($duplicatesProbability < 1) {
            if ($namesMatch) {
              $duplicatesProbability = max($duplicatesProbability, 0.5);
            }
            if ($commsMatch) {
              $duplicatesProbability = max($duplicatesProbability, 0.5);
            }
          }

          if ($duplicatesProbability > 0) {
            $reasons = [];
            if ($namesMatch) {
              $reasons[] = $this->l->t('full name');
            } else {
              $firstNameMatch && $reasons[] = $this->l->t('first name');
              $surNameMatch && $reasons[] = $this->l->t('surname');
            }
            $commsMatch && $reasons[] = $this->l->t('communication');
            $addressMatch && $reasons[] = $this->l->t('address');

            $duplicates[$musicianId] = new DTO\DuplicateMusician(
              duplicatesProbability: $duplicatesProbability,
              reasons: $reasons,
              musician: $musician,
            );
          }
        }

        $messages = [];
        if (count($duplicates) > 0) {
          $messages[] = $this->l->t('Musician(s) with the same first and sur-name already exist: %s', $duplicateNames);
        }

        return new DTO\DuplicateMusiciansResponse(
          messages: $messages,
          duplicates: $duplicates,
        )->response();
    }
  }

  /**
   * Used internally to build query parameters.
   *
   * @param string $field The field to match.
   *
   * @param mixed $value The value to match.
   *
   * @param array $criteria Criteria to search for. The function adds them to
   * this value.
   *
   * @return void
   */
  private static function matchOrNull(string $field, mixed $value, array &$criteria):void
  {
    $criteria[] = [ '(|' . $field => $value ];
    $criteria[] = [ $field => null ];
    $criteria[] = [ ')' => true ];
  }
}

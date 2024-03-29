<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use \Mail_RFC822;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\GeoCodingService;
use OCA\CAFEVDB\Service\PhoneNumberService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\PageRenderer\Projects as Renderer;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\PHPMailer;

/** Validation controller for some personal input fields. */
class MusicianValidationController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\FlattenEntityTrait;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var GeoCodingService */
  private $geoCodingService;

  /** @var PhoneNumberService */
  private $phoneNumberService;

  /** @var EntityManager */
  protected $entityManager;

  /** @var MusiciansRepository */
  protected $musiciansRepository;

  /** @var string */
  protected $dataPrefix = '';

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    GeoCodingService $geoCodingService,
    PhoneNumberService $phoneNumberService,
    EntityManager $entityManager,
    PHPMyEdit $phpMyEdit,
  ) {

    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->geoCodingService = $geoCodingService;
    $this->phoneNumberService = $phoneNumberService;
    $this->entityManager = $entityManager;
    $this->pme = $phpMyEdit;
    $this->l = $this->l10N();
    $this->musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);
    $this->dataPrefix = $this->parameterService['dataPrefix']['musicians']??'';
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
      $this->parameterService[$this->pme->cgiDataName($this->dataPrefix . $name)]?:'');
  }

  /**
   * @param string $topic What to validate.
   *
   * @param null|string $subTopic Optional subtopic.
   *
   * @param string $failure
   *
   * @return DataResponse
   *
   * @NoAdminRequired
   */
  public function validate(string $topic, ?string $subTopic = null, string $failure = 'notice'):DataResponse
  {
    $message = [];
    switch ($failure) {
      case 'error':
        $returnFailures = fn($data) => self::grumble($data);
        break;
      default:
        $returnFailures = fn($data) => self::dataResponse($data);
        break;
    }
    switch ($topic) {
      case 'phone':
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

        $this->logInfo(print_r($numbers, true));

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

        return self::dataResponse([
          'message' => $message,
          'mobilePhone' => $mobile['number'],
          'mobileMeta' => nl2br($mobile['meta']),
          'fixedLinePhone' => $fixed['number'],
          'fixedLineMeta' => nl2br($fixed['meta']),
        ]);

        break;
      case 'email':
        $email = $this->requestParameter('email');

        if (empty($email)) {
          $returnFailures([ 'message' => $this->t->t('Submitted email is empty'), 'email' => '' ]);
        }

        $phpMailer = new PHPMailer(true);
        $parser = new Mail_RFC822(null, null, null, false);

        $parsedEmail = $parser->parseAddressList($email);
        $parseError = $parser->parseError();

        if ($parseError !== false) {
          $message[] = htmlspecialchars($this->l->t($parseError['message'], $parseError['data']));
        } else {
          $emailArray = [];
          foreach ($parsedEmail as $emailRecord) {
            if ($emailRecord->host === 'localhost') {
              $message[] = $this->l->t(
                'Missing host for email-address: %s. ',
                [ htmlspecialchars($emailRecord->mailbox) ]
              );
              continue;
            }
            $recipient = strtolower($emailRecord->mailbox.'@'.$emailRecord->host);
            if (!$phpMailer->validateAddress($recipient)) {
              $message[] = $this->l->t(
                'Validation failed for: %s. ',
                [ htmlspecialchars($recipient) ]
              );
              continue;
            }
            $emailArray[] = $recipient;
          }
        }

        if (empty($message)) {
          $email = implode(', ', $emailArray);
        }

        $result = [
          'message' => $message,
          'email' => $email,
        ];

        if (empty($message)) {
          return self::dataResponse($result);
        } else {
          return $returnFailures($result);
        }

      case 'autocomplete':
        $country = $this->requestParameter('country');
        $city = $this->requestParameter('city');
        $street = $this->requestParameter('street');
        $postalCode = $this->requestParameter('postal_code');
        switch ($subTopic) {
          case 'street':
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
          case 'place':
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
            return self::grumble($this->l->t('Unsupported auto-complete request for "%s".', $subTopic));
        }
        break;
      case 'duplicates':
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
          return self::dataResponse([
            'message' => [],
            'duplicates' => [],
          ]);
        }
        array_unshift($criteria, [ '(|' => true ]);
        $criteria[] = [ ')' => true ];

        $this->logInfo('CRITERIA ' . print_r($criteria, true));

        $musicians = $this->musiciansRepository->findBy($criteria, [
          'surName' => 'ASC', 'firstName' => 'ASC' ]);

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

          $duplicatesPropability = 0.0;

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
              $duplicatesPropability = 1.0; // treat as exact match
            }
            if ($addressMatch) {
              $duplicatesPropability = 1.0; // treat as exact match
            }
          }

          if ($duplicatesPropability < 1) {
            if ($namesMatch) {
              $duplicatesPropability = max($duplicatesPropability, 0.5);
            }
            if ($commsMatch) {
              $duplicatesPropability = max($duplicatesPropability, 0.5);
            }
          }

          if ($duplicatesPropability > 0) {
            $reasons = [];
            if ($namesMatch) {
              $reasons[] = $this->l->t('full name');
            } else {
              $firstNameMatch && $reasons[] = $this->l->t('first name');
              $surNameMatch && $reasons[] = $this->l->t('surname');
            }
            $commsMatch && $reasons[] = $this->l->t('communication');
            $addressMatch && $reasons[] = $this->l->t('address');

            $duplicates[$musicianId] = $this->flattenMusician($musician, only: []);
            $duplicates[$musicianId]['duplicatesPropability'] = $duplicatesPropability;
            $duplicates[$musicianId]['reasons'] = implode(', ', $reasons);
          }
        }

        $message = [];
        if (count($duplicates) > 0) {
          $message[] = $this->l->t('Musician(s) with the same first and sur-name already exist: %s', $duplicateNames);
        }

        return self::dataResponse([
          'message' => $message,
          'duplicates' => $duplicates,
        ]);
        break;
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request'));
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

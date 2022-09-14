<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\ILogger;
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

class MusicianValidationController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
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

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , GeoCodingService $geoCodingService
    , PhoneNumberService $phoneNumberService
    , EntityManager $entityManager
    , PHPMyEdit $phpMyEdit
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

  private function requestParameter($name)
  {
    return Util::normalizeSpaces(
      $this->parameterService[$this->pme->cgiDataName($this->dataPrefix . $name)]?:'');
  }

  /**
   * @NoAdminRequired
   *
   * @param string $topic
   * - phone
   * - email
   * - address
   * - duplicates
   */
  public function validate($topic, $subTopic = null)
  {
    $message = [];
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
        }
        if (!$number['valid'] && !empty($number['number'])) {
          $message[] = $this->l->t('The phone number %s does not appear to be a valid phone number. ',
                                  [ $number['number'], ]);
        }
      }

      $this->logInfo(print_r($numbers, true));

      if (!$fixed['valid'] && $mobile['valid'] && !$mobile['isMobile']) {
        $tmp = $fixed;
        $fixed = $mobile;
        $mobile = $tmp;
        $message[] = $this->l->t('This (%s) is a fixed line phone number, injecting it in the correct column.',
                                 [ $fixed['number'] ]);
      }
      if (!$mobile['valid'] && $fixed['valid'] && $fixed['isMobile']) {
        $tmp = $mobile;
        $mobile = $fixed;
        $fixed = $tmp;
        $message[] = $this->l->t('This (%s) is a mobile phone number, injecting it in the correct column.',
                               [ $mobile['number'] ]);
      }
      if (!empty($mobile['number']) && !empty($fixed['number']) && !$mobile['isMobile'] && $fixed['isMobile']) {
        $tmp = $fixed;
        $fixed = $mobile;
        $mobile = $tmp;
      } else if ($mobile['valid'] && !$mobile['isMobile']) {
        $message[] = $this->l->t('The phone number %s does not appear to be a mobile phone number. ',
                                [ $mobile['number'] ]);
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
        return self::dataResponse([ 'message' => '', 'email' => '' ]);
      }

      $phpMailer = new PHPMailer(true);
      $parser = new \Mail_RFC822(null, null, null, false);

      $parsedEmail = $parser->parseAddressList($email);
      $parseError = $parser->parseError();

      if ($parseError !== false) {
        $message[] = htmlspecialchars($this->l->t($parseError['message'], $parseError['data']));
      } else {
        $emailArray = [];
        foreach ($parsedEmail as $emailRecord) {
          if ($emailRecord->host === 'localhost') {
            $message[] = $this->l->t('Missing host for email-address: %s. ',
                                     [ htmlspecialchars($emailRecord->mailbox) ]);
            continue;
          }
          $recipient = $emailRecord->mailbox.'@'.$emailRecord->host;
          if (!$phpMailer->validateAddress($recipient)) {
            $message[] = $this->l->t('Validation failed for: %s. ',
                                    [ htmlspecialchars($recipient) ]);
            continue;
          }
          $emailArray[] = $recipient;
        }
      }

      if (empty($message)) {
        $email = implode(', ', $emailArray);
      }

      return self::dataResponse([
        'message' => $message,
        'email' => $email,
      ]);

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
          foreach($locations as $location) {
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
      if (!empty($surName = $this->requestParameter('sur_name'))) {
        $nameCriteria[] = [ 'surName' => $surName ];
      }
      if (!empty($firstName = $this->requestParameter('first_name'))) {
        $nameCriteria[] = [ 'firstName' => $firstName ];
      }
      if (!empty($nameCriteria)) {
        array_unshift($nameCriteria, [ '(&' => true ]);
        $nameCriteria[] = [ ')' => true ];
      }
      $commCriteria = [];
      if (!empty($email = $this->requestParameter('email'))) {
        $commCriteria[] = [ 'email' => $email ];
      }
      if (!empty($fixedLinePhone = $this->requestParameter('fixed_line_phone'))) {
        $commCriteria[] = [ 'fixedLinePhone' => $fixedLinePhone ];
      }
      if (!empty($mobilePhone = $this->requestParameter('mobile_phone'))) {
        $commCriteria[] = [ 'mobilePhone' => $mobilePhone ];
      }
      if (!empty($commCriteria)) {
        array_unshift($commCriteria, [ '(|' => true ]);
        $commCriteria[] = [ ')' => true ];
      }
      $usableAddress = 0;
      $addressCriteria = [];
      if (!empty($street = $this->requestParameter('street'))) {
        self::matchOrNull('street', $street, $addressCriteria);
        $usableAddress ++;
      }
      if (!empty($streetNumber = $this->requestParameter('street_number'))) {
        self::matchOrNull('streetNumber', $streetNumber, $addressCriteria);
      }
      if (!empty($postalCode = $this->requestParameter('postal_code'))) {
        self::matchOrNull('postalCode', $postalCode, $addressCriteria);
      }
      if (!empty($city = $this->requestParameter('city'))) {
        self::matchOrNull('city', $city, $addressCriteria);
        $usableAddress ++;
      }
      if (!empty($country = $this->requestParameter('country'))) {
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
          'messages' => [],
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
          (!empty($email) && $email == $musician->getEmailAddress())
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

        if ($firstNameMatch)  {
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

  static private function matchOrNull($field, $value, array &$criteria)
  {
    $criteria[] = [ '(|' . $field => $value ];
    $criteria[] = [ $field => null ];
    $criteria[] = [ ')' => true ];
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

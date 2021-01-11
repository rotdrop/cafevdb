<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

class MusiciansController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

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
  public function validate($topic)
  {
    switch ($topic) {
    case 'phone':
      $numbers = [
        'mobile' => [
          'number' => Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('mobile_phone')]),
          'isMobile' => false,
          'valid' => false,
          'meta' => false,
        ],
        'fixed' => [
          'number' => Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('fixed_line_phone')]),
          'isMobile' => false,
          'valid' => false,
          'meta' => false,
        ],
      ];

      $fixed = &$numbers['fixed'];
      $mobile = &$numbers['mobile'];

      $this->logInfo(print_r($numbers, true));


      // validata phone numbers
      foreach ($numbers as &$number) {
        if ($this->phoneNumberService->validate($number['number'])) {
          $number['number'] = $this->phoneNumberService->format();
          $number['meta'] = $this->phoneNumberService->metaData();
          $number['isMobile'] = $this->phoneNumberService->isMobile();
          $number['valid'] = true;
        } else if (!empty($number['number'])) {
          $message .= $this->l->t('The phone number %s does not appear to be a valid phone number. ',
                                  [ $number['number'], ]);
        }
      }

      if (!$fixed['valid'] && $mobile['valid'] && !$mobile['isMobile']) {
        $tmp = $fixed;
        $fixed = $mobile;
        $mobile = $tmp;
        $message = $this->l->t('This (%s) is a fixed line phone number, injecting it in the correct column.',
                               [ $fixed['number'] ]);
      }
      if (!$mobile['valid'] && $fixed['valid'] && $fixed['isMobile']) {
        $tmp = $mobile;
        $mobile = $fixed;
        $fixed = $tmp;
        $message = $this->l->t('This (%s) is a mobile phone number, injecting it in the correct column.',
                               [ $mobile['number'] ]);
      }
      if (!empty($mobile['number']) && !empty($fixed['number']) && !$mobile['isMobile'] && $fixed['isMobile']) {
        $tmp = $fixed;
        $fixed = $mobile;
        $mobile = $tmp;
      } else if ($mobile['valid'] && !$mobile['isMobile']) {
        $message .= $this->l->t('The phone number %s does not appear to be a mobile phone number. ',
                                [ $mobile['number'] ]);
      }

      return self::dataResponse([
        'message' => nl2br($message),
        'mobilePhone' => $mobile['number'],
        'mobileMeta' => nl2br($mobile['meta']),
        'fixedLinePhone' => $fixed['number'],
        'fixedLineMeta' => nl2br($fixed['meta']),
      ]);

      break;
    case 'email':
      $email = Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('email')]);

      if (empty($email)) {
        return self::dataResponse([ 'message' => '', 'email' => '' ]);
      }

      $phpMailer = new \PHPMailer\PHPMailer\PHPMailer(true);
      $parser = new \Mail_RFC822(null, null, null, false);

      $parsedEmail = $parser->parseAddressList($email);
      $parseError = $parser->error;

      if (!empty($parseError)) {
        $message .= htmlspecialchars($this->l->t($parseError)).'. ';
      } else {
        $emailArray = [];
        foreach ($parsedEmail as $emailRecord) {
          if ($emailRecord->host === 'localhost') {
            $message .= $this->l->t('Missing host for email-address: %s. ',
                                    [ htmlspecialchars($emailRecord->mailbox) ]);
            continue;
          }
          $recipient = $emailRecord->mailbox.'@'.$emailRecord->host;
          if (!$phpMailer->validateAddress($recipient)) {
            $message .= $this->l->t('Validation failed for: %s. ',
                                    [ htmlspecialchars($recipient) ]);
            continue;
          }
          $emailArray[] = $recipient;
        }
      }

      if ($message === '') {
        $email = implode(', ', $emailArray);
      }

      return self::dataResponse([
        'message' => nl2br($message),
        'email' => $email,
      ]);

    case 'address':
      $country = Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('country')]);
      $city = Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('city')]);
      $street = Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('street')]);
      $zip = Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('postal_code')]);
      $active = $this->parameterService['active_element'];


      $locations = $this->geoCodingService->cachedLocations($zip, $city, $country);
      $streets = $this->geoCodingService->autoCompleteStreet($street, $country, $city, $zip);

      if (count($locations) == 0 && ($city || $zip)) {
        // retry remotely with given country
        $locations = $this->geoCodingService->remoteLocations($zip, $city, $country);
        if (count($locations) == 0) {
          // retry without country, i.e. on same continent
          $locations = $this->geoCodingService->cachedLocations($zip, $city, null);
          if (count($locations) == 0) {
            // still no luck: try a world search
            $locations = $this->geoCodingService->cachedLocations($zip, $city, '%');
            if (count($locations) == 0) {
              // retry with remote service, on this continent ...
              $locations = $this->geoCodingService->remoteLocations($zip, $city, null);
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
      sort($streets, SORT_LOCALE_STRING);

      $cities = array_values(array_unique($cities, SORT_LOCALE_STRING));
      $postalCodes = array_values(array_unique($postalCodes, SORT_LOCALE_STRING));
      $countries = array_values(array_unique($countries));
      $streets = array_values(array_unique($streets));

      return self::dataResponse([
        'message' => nl2br($message),
        'city' => $city,
        'zip' => $zip,
        'street' => $street,
        'suggestions' => [
          'cities' => $cities,
          'postalCodes' => $postalCodes,
          'countries' => $countries,
          'streets' => $streets,
        ],
      ]);

      break;
    case 'duplicates':
      $lastName = Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('name')]?:'');
      $firstName = Util::normalizeSpaces($this->parameterService[$this->pme->cgiDataName('first_name')]?:'');

      $musicians = $this->musiciansRepository->findByName($firstName, $lastName);

      $duplicateNames = '';
      $duplicates = [];
      foreach ($musicians as $musician) {
        $duplicateNames .= $musician['firstName'].' '.$musician['name']." (Id = ".$musician['id'].")"."\n";
        $duplicates[$musician['id']] = $musician['firstName'].' '.$musician['name'];
      }

      $message = '';
      if (count($duplicates) > 0) {
        $message = $this->l->t('Musician(s) with the same first and sur-name already exist: %s', $duplicateNames);
      }

      return self::dataResponse([
        'message' => nl2br($message),
        'duplicates' => $duplicates,
      ]);
      break;
    default:
      break;
    }
    return self::grumble($this->l->t('Unknown Request'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

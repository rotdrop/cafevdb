<?php
/**
 * Orchestra member, musician and project management application.
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

use \PHP_IBAN\IBAN;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataDownloadResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

use OCA\CAFEVDB\Common\Util;

class InstrumentInsuranceController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var ParameterService */
  private $parameterService;

  /** @var FuzzyInputService */
  private $fuzzyInputService;

  /** @var ProjectService */
  private $projectService;

  /** @var InstrumentInsuranceService */
  private $insuranceService;

  /** @var \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit */
  protected $pme;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , InstrumentInsuranceService $insuranceService
    , ProjectService $projectService
    , FuzzyInputService $fuzzyInputService
    , PHPMyEdit $phpMyEdit
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->insuranceService = $insuranceService;
    $this->projectService = $projectService;
    $this->fuzzyInputService = $fuzzyInputService;
    $this->pme = $phpMyEdit;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function validate($control, $template)
  {
    $cgiPrefix  = $this->pme->cgiDataName();
    $pmeData = $this->parameterService->getPrefixParams($cgiPrefix);
    switch ($template) {
    case 'insurance-brokers':
      $cgiKeys = [
        'broker' => 'short_name',
        'brokerName' => 'long_name',
        'brokerAddress' => 'address',
      ];
      $values = [];
      foreach($cgiKeys as $key => $cgiKey) {
        $values[$key] = $pmeData[$cgiKey]?:false;
        if (is_string($values[$key])) {
          $values[$key] = trim($values[$key]);
        }
      }

      $message = [];
      switch($control) {
      case 'submit':
      case 'broker':
        $broker = $values['broker'];
        // No whitespace, s.v.p., and CamelCase
        $origBroker = $broker;

        $broker = $this->fuzzyInputService->ensureCamelCase($broker);
        if ($broker != $origBroker) {
          $message[] = $this->l->t(
            'Broker-name has been simplified from "%s" to "%s".',
            [ $values['broker'], $broker ]);
          $values['broker'] = $broker;
        }
        if ($control != 'submit') {
          break;
        }
      case 'brokerName':
        $values['brokerName'] = Util::normalizeSpaces($values['brokerName']);
        if ($control != 'submit') {
          break;
        }
      case 'brokerAddress':
        break;
      default:
        return self::grumble($this->l->t('Unknown request: "%s"', $control));
        break;
      }

      $values['message'] = $message;
      return self::dataResponse($values);
    case 'insurance-rates':
      $cgiKeys = [
        'rate' => 'rate',
        'date' => 'due_date',
        'policy' => 'policy_number',
      ];
      $values = [];
      foreach($cgiKeys as $key => $cgiKey) {
        $values[$key] = $pmeData[$cgiKey]?:false;
        if (is_string($values[$key])) {
          $values[$key] = trim($values[$key]);
        }
      }

      $message = [];
      switch($control) {
      case 'submit':
      case 'rate':
         $rate = $this->fuzzyInputService->floatValue($values['rate']);
         if ($rate <= 0 || $rate > 1e-2) {
           return self::grumble($this->l->t('Invalid insurance rate %f, should be larger than 0 and less than 1 percent.', $rate));
         }
         if ((string)$rate !== (string)$values['rate']) {
           $message[] = $this->l->t(
            'Rate has been simplified from "%s" to "%s".',
            [ $values['rate'], $rate ]);
           $values['rate'] = $rate;
         }
         break;
      case 'date': // date is validated client-side by date-picker
      case 'policy': // no way to validate, free-form text
        break; // break on last item
      default:
        return self::grumble($this->l->t('Unknown request: "%s"', $control));
        break;
      }
      $values['message'] = $message;
      return self::dataResponse($values);
    case 'instrument-insurance':
      $errorMessage = [];
      $message = [];
      // control -> name mapping
      $cgiKeys = [
        'instrumentHolder' => 'instrument_holder_id',
        'billToParty' => 'bill_to_party_id',
        'brokerSelect' => 'broker_id',
        'scopeSelect' => 'geographical_scope',
        'insuredItem' => 'object',
        'accessory' => 'accessory',
        'manufacturer' => 'manufacturer',
        'constructionYear' => 'year_of_construction',
        'amount' => 'insurance_amount',
      ];
      $values = [];
      foreach($cgiKeys as $key => $cgiKey) {
        $values[$key] = $pmeData[$cgiKey]?:false;
        if (is_string($values[$key])) {
          $values[$key] = trim($values[$key]);
        }
      }

      switch ($control) {
      case 'submit':
      case 'musician-id':
        $value = $values['instrumentHolder'];
        if (empty($value)) {
          // must not be empty
          $errorMessage[] = $this->l->t('Insured musician is missing');
        } else {
          // ? check perhaps for existence, however, this is an id
          // generated from a select box with values from the DB.
        }
        if ($control != 'submit') {
          break;
        }
      case 'bill-to-party':
        $value = $values['billToParty'];
        if (empty($value)) {
          // ? check perhaps for existence, however, this is an id
          // generated from a select box with values from the DB.
        }
        if ($control != 'submit') {
          break;
        }
      case 'broker-select':
        $value = $values['brokerSelect'];
        if (empty($value)) {
          // must not be empty
          $errorMessage[] = $this->l->t('Insurance broker is missing.');
        }
        if ($control != 'submit') {
          break;
        }
      case 'scope-select':
        $value = $values['scopeSelect'];
        if (empty($value)) {
          // must not be empty
          $errorMessage[] = $this->l->t('Geographical scope for the insurance is missing.');
        }
        if ($control != 'submit') {
          break;
        }
      case 'insuredItem':
      case 'insured-item':
        $value = $values['insuredItem'];
        if (empty($value)) {
          $errorMessage[] = $this->l->t('Insured object has not been specified.');
        }
        if ($control != 'submit') {
          break;
        }
      case 'accessory':
        $value = $values['accessory'];
        if (false && empty($value)) {
          // checkbox, may be empty.
          $errorMessage[] = $this->l->t('Object classification (instrument, accessory) is missing.');
        }
        if ($control != 'submit') {
          break;
        }
      case 'manufacturer':
        $value = $values['manufacturer'];
        if (empty($value)) {
          $infoMessage .= $this->l->t("Manufacturer field is empty.");
        } else {
          // Mmmh.
        }
        if ($control != 'submit') {
          break;
        }
      case 'constructionYear':
      case 'construction-year':
        $value = $values['constructionYear'];
        if (empty($value) || $value === (string)$this->l->t('unknown')) {
          $message[] = $this->l->t("Construction year is unknown.");
          // allow free-style like "ca. 1900" and such.
        /* } else if ($value != $this->l->t('unknown') && !preg_match("/[0-9]{4}/", $value)) { */
        /*   $errorMessage = $this->l->t("Construction year must be either a literal `%s' or a four digit year, you typed %s.", */
        /*                        array($this->l->t('unknown'), $value)); */
        }
        if ($control != 'submit') {
          break;
        }
      case 'amount':
        $value = $this->fuzzyInputService->currencyValue($values['amount']);
        if (empty($value)) {
          $errorMessage[] = $this->l->t('The insurance amount is missing.');
        } else {
          $LocaleInfo = localeconv();
          $value = str_replace($LocaleInfo["mon_thousands_sep"] , "", $value);
          $value = str_replace($LocaleInfo["mon_decimal_point"] , ".", $value);
          if (!is_numeric($value)) {
            $errorMessage[] = $this->l->t('Insurance amount should be a mere number.');
          }
          if ((string)floatval($value) != (string)intval($value)) {
            $errorMessage[] = $this->l->t('Insurance amount should be an integral number.');
          }
        }
        break; // break at last item
      default:
        $errorMessage[] = $this->l->t('Unknown Request: "%s / %s".', [ $control, $template ]);
        break;
      }

      if (!empty($errorMessage)) {
        return self::grumble(implode(' ', $errorMessage));
      }

      $values['message'] = $message;
      return self::dataResponse($values);
    }
    return self::grumble($this->l->t('Unknown Request: "%s / %s".', [ $control, $template ]));
  }

  /**
   * @NoAdminRequired
   */
  public function download($musicianId, $insuranceId)
  {
    $overview = $this->insuranceService->musicianOverview($musicianId);
    $fileData = $this->insuranceService->musicianOverviewLetter($overview);
    $fileName = $this->insuranceService->musicianOverviewFileName($overview);

    /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
    $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
    $mimeType = $mimeTypeDetector->detectString($fileData);

    return new DataDownloadResponse($fileData, $fileName, $mimeType);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

use \PHP_IBAN\IBAN;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

use OCA\CAFEVDB\Common\Util;

/** AJAX end-points for instrument insurances */
class InstrumentInsuranceController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private RequestParameterService $parameterService,
    protected ConfigService $configService,
    private InstrumentInsuranceService $insuranceService,
    private ProjectService $projectService,
    private FuzzyInputService $fuzzyInputService,
    protected PHPMyEdit $phpMyEdit,
  ) {
    parent::__construct($appName, $request);
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param string $control
   *
   * @param string $template
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function validate(string $control, string $template):Http\Response
  {
    $errorMessages = [];
    $message = [];
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
        foreach ($cgiKeys as $key => $cgiKey) {
          $values[$key] = $pmeData[$cgiKey]?:null;
          if (is_string($values[$key])) {
            $values[$key] = trim($values[$key]);
          }
        }

        switch ($control) {
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
            // fall through
          case 'brokerName':
            $values['brokerName'] = Util::normalizeSpaces($values['brokerName']);
            if ($control != 'submit') {
              break;
            }
            // fall through
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
        foreach ($cgiKeys as $key => $cgiKey) {
          $values[$key] = $pmeData[$cgiKey]?:false;
          if (is_string($values[$key])) {
            $values[$key] = trim($values[$key]);
          }
        }

        $message = [];
        switch ($control) {
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
          'rate' => 'insurance_rate',
        ];
        $values = [];
        foreach ($cgiKeys as $key => $cgiKey) {
          $values[$key] = $pmeData[$cgiKey] ?? false;
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
            // fall through
          case 'bill-to-party':
            $value = $values['billToParty'];
            if (empty($value)) {
              // ? check perhaps for existence, however, this is an id
              // generated from a select box with values from the DB.
            }
            if ($control != 'submit') {
              break;
            }
            // fall through
          case 'brokerSelect':
          case 'broker-select':
            $value = $values['brokerSelect'];
            if (empty($value)) {
              // must not be empty
              $errorMessage[] = $this->l->t('Insurance broker is missing.');
            }
            if ($control != 'submit') {
              break;
            }
            // fall through
          case 'scopeSelect':
          case 'scope-select':
            $value = $values['scopeSelect'];
            if (empty($value)) {
              // must not be empty
              $errorMessage[] = $this->l->t('Geographical scope for the insurance is missing.');
            }
            if ($control != 'submit') {
              break;
            }
            // fall through
          case 'insuredItem':
          case 'insured-item':
            $value = $values['insuredItem'];
            if (empty($value)) {
              $errorMessage[] = $this->l->t('Insured object has not been specified.');
            }
            if ($control != 'submit') {
              break;
            }
            // fall through
          case 'accessory':
            $value = $values['accessory'];
            if (false && empty($value)) {
              // checkbox, may be empty.
              $errorMessage[] = $this->l->t('Object classification (instrument, accessory) is missing.');
            }
            if ($control != 'submit') {
              break;
            }
            // fall through
          case 'manufacturer':
            $value = $values['manufacturer'];
            if (empty($value)) {
              $message[] = $this->l->t("Manufacturer field is empty.");
            } else {
              // Mmmh.
            }
            if ($control != 'submit') {
              break;
            }
            // fall through
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
            // fall through
          case 'amount':
            if (empty($values['amount'])) {
              $errorMessage[] = $this->l->t('The insurance amount is missing.');
              break;
            }
            $value = $this->fuzzyInputService->currencyValue($values['amount']);
            if (empty($value)) {
              $errorMessages[] = $this->l->t('Unable to parse currency value "%s".', $values['amount']);
              break;
            }
            if ((string)floatval($value) != (string)intval($value)) {
              $errorMessage[] = $this->l->t('Insurance amount should be an integral number.');
              break;
            }

            if ($control != 'submit') {
              $values['amount'] = intval($value);
              $values['fee'] = (float)$values['amount'] * (float)$values['rate'];
              break;
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
      default:
        break;
    }
    return self::grumble($this->l->t('Unknown Request: "%s / %s".', [ $control, $template ]));
  }

  /**
   * Download the current insurance bill without storing it in the data-base
   * file-system.
   *
   * @param int $musicianId
   *
   * @param int $insuranceId
   *
   * @return Http\DataDownloadResponse
   *
   * @NoAdminRequired
   */
  public function download(int $musicianId, int $insuranceId):Http\DataDownloadResponse
  {
    $overview = $this->insuranceService->musicianOverview($musicianId);
    $fileData = $this->insuranceService->musicianOverviewLetter($overview);
    $fileName = $this->insuranceService->musicianOverviewFileName($overview);

    /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
    $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
    $mimeType = $mimeTypeDetector->detectString($fileData);

    return $this->dataDownloadResponse($fileData, $fileName, $mimeType);
  }
}

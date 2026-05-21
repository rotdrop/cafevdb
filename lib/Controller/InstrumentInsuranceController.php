<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Controller\DTO;
use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\FuzzyInputService;
use OCA\CAFEVDB\Service\ProjectService;

/** AJAX end-points for instrument insurances */
#[TSAttributes\TypeScript]
class InstrumentInsuranceController extends Controller
{
  use GetPrefixParamsTrait;

  public const BASE_PATH = 'insurance';

  public const END_POINT_VALIDATE = 'validate';
  public const END_POINT_DOWNLOAD = 'download';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private FuzzyInputService $fuzzyInputService,
    private InstrumentInsuranceService $insuranceService,
    private ProjectService $projectService,
    protected PHPMyEdit $phpMyEdit,
    protected IL10N $l,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * @param string $control
   *
   * @param string $template
   *
   * @return Http\Response
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb:'POST', url: '/' . self::BASE_PATH . '/' . self::END_POINT_VALIDATE . '/{control}')]
  public function validate(string $control, string $template): Http\Response
  {
    $errorMessages = [];
    $message = [];
    $cgiPrefix  = $this->phpMyEdit->cgiDataName();
    $pmeData = $this->getPrefixParams($cgiPrefix);
    switch ($template) {
      case PageRenderer\InsuranceBrokers::TEMPLATE:
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
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Unknown request: "%s"', $control),
            );
        }

        $values['messages'] = [$message];
        return DTO\InsuranceBrokerValidationResponse::fromArray($values)->response();

      case PageRenderer\InsuranceRates::TEMPLATE:
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
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Invalid insurance rate %f, should be larger than 0 and less than 1 percent.', $rate),
              );
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
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Unknown request: "%s"', $control),
            );
            break;
        }
        $values['messages'] = $message;
        return DTO\InsuranceRateValidationResponse::fromArray(
          $values,
        )->reponse();

      case PageRenderer\InstrumentInsurances::TEMPLATE:
        $errorMessage = [];
        $messages = [];
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
            if (is_array($value)) {
              $value = array_values($value)[0];
              $values['accessory'] = $value;
            }
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
              $messages[] = $this->l->t("Manufacturer field is empty.");
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
              $messages[] = $this->l->t("Construction year is unknown.");
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
          throw new Exceptions\EnduserNotificationException(
            implode(' ', $errorMessage),
          );
        }

        $values['messages'] = $messages;
        return DTO\InstrumentInsuranceValidationResponse::fromArray(
          $values,
        )->response();

      default:
        break;
    }
    throw new Exceptions\EnduserNotificationException(
      message: $this->l->t('Unknown Request: "%s / %s".', [ $control, $template ]),
    );
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
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'GET', url: '/' . self::BASE_PATH . '/' . self::END_POINT_DOWNLOAD . '/{musicianId}/{insuranceId}', postfix: '.get')]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::BASE_PATH . '/' . self::END_POINT_DOWNLOAD)]
  public function download(int $musicianId, int $insuranceId):Http\DataDownloadResponse
  {
    $overview = $this->insuranceService->musicianOverview($musicianId);
    $fileData = $this->insuranceService->musicianOverviewLetter($overview);
    $fileName = $this->insuranceService->musicianOverviewFileName($overview);

    /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
    $mimeTypeDetector = \OCP\Server::get(\OCP\Files\IMimeTypeDetector::class);
    $mimeType = $mimeTypeDetector->detectString($fileData);

    return new Http\DataDownloadResponse($fileData, $fileName, $mimeType);
  }
}

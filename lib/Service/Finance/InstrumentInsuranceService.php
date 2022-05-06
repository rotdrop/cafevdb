<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service\Finance;

use \DateTimeImmutable as DateTime;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Documents\OpenDocumentFiller;
use OCA\CAFEVDB\Documents\PDFLetter;
use OCA\CAFEVDB\Exceptions;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\Functions;

/** Collective instrument insurance. */
class InstrumentInsuranceService
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\EnsureEntityTrait;

  const ENTITY = Entities\InstrumentInsurance::class;
  const TAXES = 0.19; // ?? make this configurable ??

  /** @var Repositories\InstrumentInsurancesRepository */
  private $insurancesRepository;

  /** @var OrganizationalRolesService */
  private $orgaRolesService;

  /** @var OpenDocumentFiller */
  private $documentFiller;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , OrganizationalRolesService $orgaRolesService
    , OpenDocumentFiller $documentFiller
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->orgaRolesService = $orgaRolesService;
    $this->documentFiller = $documentFiller;
    $this->l = $this->l10n();
    $this->insurancesRepository = $this->getDatabaseRepository(self::ENTITY);
  }

  /**
   * Compute the next due date.
   *
   * The fees for the given year are always for the following
   * insurance year in advance. If the insurance-year starts at
   * December 30., then the fees charged in year Y are for the
   * insurance period Y/12/30 - (Y+1)/12/29. If the insurance-year
   * starts at January 2nd, then the fees charged in year Y are for
   * Y/01/02 - (Y+1)/01/01.
   *
   * @param string|\DateTimeInterface $dueDate Start of the insurance
   * contract. The year portion is ignored.
   *
   * @param string|\DateTimeInterface $date The point in time we are
   * looking at. If unspecified then this is the current date.
   *
   * @return \DateTimeInterface The next due-date, i.e. the end of the
   * insurance-period in the next year.
   *
   * @todo Perhaps use the timezone at the location of the orchestra
   * or insurance agency.
   */
  public function dueDate($dueDate, $date = null)
  {
    $timeZone = $this->getDateTimeZone();
    if (empty($date)) {
      $date = new \DateTimeImmutable();
    }
    $date = self::convertToTimezoneDate(self::convertToDateTime($date), $timeZone);
    $year = (int)$date->format('Y');

    $dueDate = self::convertToTimezoneDate(self::convertToDateTime($dueDate), $timeZone)
      ->modify('+'.($year - $dueDate->format('Y') + 1).' years');

    return $dueDate;
  }

  /**
   * Compute the fraction (possibly larger than 1) of the annual insurance
   * fees given $startDate and $dueDate. The actual fraction may be
   * larger than 1 if the distance to $dueDate is larger than a year.
   *
   * The time slots or rounded down to full-months.
   *
   * @param \DateTimeInterface $insuranceStart The start-date
   * of the instrument insurance.
   *
   * @param null|\DateTimeInterface $insuranceEnd The end date of the
   * instrument insurance, e.g. after total damage or if the musician has
   * with-drawn its instrument or something.
   *
   * @param \DateTimeInterface $dueDate The end of the
   * insurance year for this contract.
   *
   * @return float Fraction
   */
  private function yearFraction(\DateTimeInterface $insuranceStart, ?\DateTimeInterface $insuranceEnd, \DateTimeInterface $dueDate)
  {
    $timeZone = $this->getDateTimeZone();
    $startDate = self::convertToTimezoneDate(self::convertToDateTime($insuranceStart), $timeZone);
    $dueDate = self::convertToTimezoneDate(self::convertToDateTime($dueDate), $timeZone);

    $startDistance = $startDate->diff($dueDate);

    // $dueDate is before $insuranceStart
    if ($startDistance->invert) {
      return 0.0;
    }

    // for our purpose everything > 0 days is a month, we only charge
    // full months
    $startDistance->d = 0;

    $months = $startDistance->y > 0 ? 12.0 : $startDistance->m;

    if (!empty($insuranceEnd)) {
      // to get the diff right -- $insuranceEnd is the last day where the
      // instrument was included by into the insurance, we have to add one
      // day. E.g.: Start 01.07.YYYY, end 31.07.ZZZZ should yield one year and
      // not 365 days.
      $endDate = self::convertToTimezoneDate(self::convertToDateTime($insuranceEnd), $timeZone)->modify('+1 day');
      $endDistance = $dueDate->diff($endDate);
      if ($endDistance->invert) {
        // due-date after end-date
        if ($endDistance->y > 0) {
          // ended longer than one year ago, so return 0
          return 0.0;
        }
        $endDistance->d = 0; // just include fractional months
        $months -= $endDistance->m;
      }
    }

    $fraction = $months / 12.0;

    return $fraction;
  }

  /**
   * Compute a sparse list of insurance fees per year for the given
   * musician. Years with an amount of 0 are skipped.
   *
   * The fees for the given year are always for the following
   * insurance year in advance. If the insurance-year starts at
   * December 30., then the fees charged in year Y are for the
   * insurance period Y/12/30 - (Y+1)/12/29. If the insurance-year
   * starts at January 2nd, then the fees charged in year Y are for
   * Y/01/02 - (Y+1)/01/01.
   *
   * @param int|Entities\Musician $musicianOrId
   *
   * @param mixed string|\DateTimeInterface $date Compute until this
   * date. Only the calendar-year of $date according to the timezone
   *
   * @return array<int, float>
   * ```[ YEAR => VALUE ]```
   *
   * @todo What happens if the rates change? The rates should have a
   * validity time-range.
   */
  public function insuranceFeesYearly($musicianOrId, $date = null)
  {
    $timeZone = $this->getDateTimeZone();
    if (empty($date)) {
      $date = new DateTime();
    }
    $date = self::convertToTimezoneDate($date, $timeZone);
    $yearUntil = $date->format('Y');

    $result = [];

    $insurances = $this->billableInsurances($musicianOrId);
    /** @var Entities\InstrumentInsurance $insurance */
    foreach ($insurances as $insurance) {
      $amount = $insurance->getInsuranceAmount();
      /** @var Entities\InsuranceRate $rate */
      $rate = $insurance->getInsuranceRate();
      $annualFee = $amount * $rate->getRate();

      $insuranceStart = self::convertToTimezoneDate($insurance->getStartOfInsurance(), $timeZone);
      $insuranceEnd = $insurance->getDeleted();
      if (!empty($insuranceEnd)) {
        $insuranceEnd = self::convertToTimezoneDate($insuranceEnd, $timeZone);
      }
      $startYear = $insuranceStart->format('Y');

      $lastDueDate = $this->dueDate($rate->getDueDate(), ($startYear - 1).'-06-01');
      for ($year = $startYear; $year <= $currentYear; ++$year) {
        $dueDate = $this->dueDate($rate->getDueDate(), $year.'-06-01');
        if ($lastDueDate > $insuranceEnd) {
          break;
        }
        $yearFraction = $this->yearFraction($insuranceStart, $insuranceEnd, $dueDate);
        if ($yearFraction != 0.0) {
          $fee = $yearFraction * $annualFee * self::TAXES;
          $result[$year] = ($result[$year] ?? 0.0) + $fee;
        }
        $lastDueDate = $dueDate;
      }
    }
    return $result;
  }

  /**
   * Return all insurance items which are billable to the given musician.
   *
   * @param int|Entities\Musician $musicianOrId
   *
   * @return Collection
   */
  public function billableInsurances($musicianOrId)
  {
    return $this->insurancesRepository->findBy([ 'billToParty' => $musicianOrId ]);
  }

  /**
   * Compute the annual insurance fee for the respective musician up
   * to the year containing the given date.
   *
   * The fees for any given year are always for the following
   * insurance year in advance. If the insurance-year starts at
   * December 30., then the fees charged in year Y are for the
   * insurance period Y/12/30 - (Y+1)/12/29. If the insurance-year
   * starts at January 2nd, then the fees charged in year Y are for
   * Y/01/02 - (Y+1)/01/01.
   *
    * @param int $musicianId
   *
   * @param string|DateTime $date
   *
   * @param bool $currentYearOnly
   */
  public function insuranceFee($musicianId, $date = null, ?array &$dueInterval = null)
  {
    $timeZone = $this->getDateTimeZone();
    if (empty($date)) {
      $date = new \DateTimeImmutable();
    }
    $date = self::convertToTimezoneDate($date, $timeZone);

    $payables = $this->billableInsurances($musicianId);

    $fee = 0.0;
    /** @var \DateTimeInterface $minDueDate */
    /** @var \DateTimeInterface $maxDueDate */
    $minDueDate = $maxDueDate = null;
    /** @var Entities\InstrumentInsurance $insurance */
    foreach ($payables as $insurance) {
      $insuranceStart = self::convertToTimezoneDate($insurance->getStartOfInsurance(), $timeZone);
      $insuranceEnd = $insurance->getDeleted();
      if (!empty($insuranceEnd)) {
        $insuranceEnd = self::convertToTimezoneDate($insuranceEnd, $timeZone);
      }

      /** @var Entities\InsuranceRate $rate */
      $rate = $insurance->getInsuranceRate();

      // end of insurance period
      $dueDate = $this->dueDate($rate->getDueDate(), $date);
      if (!empty($insuranceEnd) && $dueDate->modify('-1 year') > $insuranceEnd) {
        continue;
      }
      $minDueDate = empty($minDueDate) ? $dueDate : min($dueDate, $minDueDate);
      $maxDueDate = empty($maxDueDate) ? $dueDate : max($dueDate, $maxDueDate);

      $amount = $insurance->getInsuranceAmount();
      $annualFee = $amount * $rate->getRate();
      $annualFee *= $this->yearFraction($insuranceStart, $insuranceEnd, $dueDate);
      $fee += $annualFee * (1.0 + self::TAXES);
    }
    $dueInterval = [ 'min' => $minDueDate, 'max' => $maxDueDate ];
    return round($fee, 2);
  }

  /**
   * Fetch the insurance rates of the respective brokers. For the time
   * being brokers offer different rates, independent from the
   * instrument, but depending on the geographical scope (Germany,
   * Europe, World).
   *
   * Return value is an associative array of the form
   *
   * array(BROKERSCOPE => RATE)
   *
   * where "RATE" is the actual fraction, not the percentage.
   */
  public function getRates($translate = false, $nested = false)
  {
    $rates = [];
    $nestedRates = [];
    $entities = $this->getDatabaseRepository(Entities\InsuranceRate::class)->findAll();
    /** @var Entities\InsuranceRate $entity */
    foreach ($entities as $entity) {
      $scope = (string)$entity->getGeographicalScope();
      if ($translate) {
        $scope = $this->l->t($scope);
      }
      $shortBroker = $entity->getBroker()->getShortName();
      $rateKey = $shortBroker.$scope;
      $dueDate = $entity->getDueDate();
      if (!empty($dueDate)) {
        $dueDate = $this->dueDate($dueDate);
      }
      $rates[$rateKey] = [
        'rate' => $entity->getRate(),
        'due' => $dueDate,
        'policy' => $entity->getPolicyNumber(),
      ];
      if ($nested) {
        $nestedRates[$shortBroker][$scope] = $rates[$rateKey];
      }
    }
    return $nested ? $nestedRates : $rates;
  }

  /**
   * Fetch all the insurance brokers from the data-base, return an
   * array indexed by the short name.
   */
  public function getBrokers()
  {
    $brokers = [];
    $entities = $this->getDatabaseRepository(Entities\InsuranceBroker::class)->findAll();
    /** @var Entities\InsuranceBroker $entity */
    foreach ($entities as $entity) {
      $key = $entity->getShortName();
      $brokers[$key] = [
        'shortName' => $entity->getShortName(),
        'name' => $entity->getLongName(),
        'address' => $entity->getAddress(),
      ];
    }

    return $brokers;
  }

  /**
   * Generate an overview table to the respective musician. This is
   * meant for back-report to the musician, so we do not need all
   * fields. We include
   *
   * Broker, Geog. Scope, Object, Manufacturer, Amount, Rate, Fee
   *
   * Potentially, insured musician and payer may be different. We
   * generate a table of the form
   * ```
   * [
   *   'billToParty' => MUSICIAN_ENTITY
   *   'annual' => TOTAL_FEE_EXCLUDING_TAXES,
   *   'totals' => TOTAL_FEE_INCLUDING_TAXES,
   *   'musicians' => [
   *     MusID => [
   *       'name' => HUMAN_READABLE_NAME,
   *       'subtotals' => TOTAL_FEE_FOR_THIS_ONE_WITH_TAXES,
   *       'items' => [ INSURED_ITEMS ],
   *     ]
   *   ]
   * ]
   * ```
   */
  public function musicianOverview($musicianOrId, $date = null)
  {
    $timeZone = $this->getDateTimeZone();
    if (empty($date)) {
      $date = new DateTime();
    }
    $date = self::convertToTimezoneDate($date, $timeZone);

    /** @var Entities\Musician $musician */
    $billToParty = $this->ensureMusician($musicianOrId);

    $payableInsurances = $billToParty->getPayableInsurances();

    $insuranceOverview = [
      'billToParty' => $billToParty,
      'taxRate' => floatval(self::TAXES),
      'musicians' => [],
      'date' => $date,
    ];

    /** @var Entities\InstrumentInsurance $insurance */
    foreach ($payableInsurances as $insurance) {
      $insuranceStart = self::convertToTimezoneDate($insurance->getStartOfInsurance(), $timeZone);
      $insuranceEnd = $insurance->getDeleted();
      if (!empty($insuranceEnd)) {
        $insuranceEnd = self::convertToTimezoneDate($insuranceEnd, $timeZone);
      }

      /** @var Entities\InsuranceRate $rate */
      $rate = $insurance->getInsuranceRate();

      // end of the insurance year
      $dueDate = $this->dueDate($rate->getDueDate(), $date);

      // start of insurance year
      $lastDueDate = $dueDate->modify('-1 year');

      if (!empty($insuranceEnd) && $lastDueDate > $insuranceEnd) {
        // exclude instruments which are no longer insured
        continue;
      }

      if ($dueDate <= $insuranceStart) {
        // exclude instruments which were not yet insured in that year
        continue;
      }

      $amount = $insurance->getInsuranceAmount();
      $fraction = $this->yearFraction($insuranceStart, $insuranceEnd, $dueDate);
      $annualFee = $amount * $rate->getRate();

      $instrumentHolder = $insurance->getInstrumentHolder();
      $instrumentHolderId = $instrumentHolder->getId();
      if (empty($insuranceOverview['musicians'][$instrumentHolderId])) {
        $insuranceOverview['musicians'][$instrumentHolderId] = [
          'name' => $instrumentHolder->getPublicName(true),
          'subTotals' => 0.0,
          'items' => [],
        ];
      }

      $itemInfo = [
        'broker' => $insurance->getBroker()->getShortName(),
        'scope' => $insurance->getGeographicalScope(),
        'object' => $insurance->getObject(),
        'manufacturer' => $insurance->getManufacturer(),
        'amount' => (float)$amount,
        'rate' => $rate->getRate(),
        'lastDue' => $lastDueDate,
        'due' => empty($insuranceEnd) ? $dueDate : $insuranceEnd,
        'start' => $insuranceStart,
        'fullFee' => $annualFee,
        'fraction' => $fraction,
        'fee' => $annualFee * $fraction,
      ];

      $insuranceOverview['musicians'][$instrumentHolderId]['items'][] = $itemInfo;
    }

    $annual     = 0.0;
    $fractional = 0.0;
    foreach($insuranceOverview['musicians'] as $id => $info) {
      // ordinary annular fees
      $subTotals = 0.0;
      foreach($info['items'] as $itemInfo) {
        $subTotals += $itemInfo['fee'];
      }
      $insuranceOverview['musicians'][$id]['subTotals'] = $subTotals;
      // $this->logInfo('SUBTOTALS '.$subTotals);
      $annual += $subTotals;
    }
    $insuranceOverview['annual'] = $annual;

    return $insuranceOverview;
  }

  /**
   *Small support function in order to generate a consistent
   * file-name for the exported PDFs.
   */
  public function musicianOverviewFileName($overview)
  {
    /** @var Entities\Musician $billToParty */
    $billToParty = $overview['billToParty'];

    // $nameBase = $billToParty->getPublicName(true);
    $userIdSlug = $billToParty->getUserIdSlug();
    $camelCaseSlug = Util::dashesToCamelCase($userIdSlug, true, '_-.');

    $year = $overview['date']->format('Y');

    $components = [
      $this->timeStamp(),
      $billToParty->getId(),
      $camelCaseSlug,
      strtolower($this->l->t('insurance')),
      $year, $year + 1,
    ];

    return implode('-', $components) . '.pdf';
  }

  /**
   * Take the data provided by self::musicianOverview() to generate a
   * PDF with a DIN-letter in order to send the overview to the
   * respective musician by SnailMail. The resulting letter will be
   * returned as string.
   *
   * @param array $overview Data returned from InstrumentInsuranceService::musicianOverview()
   *
   * @param null|string $fileName If null the name generated by
   * InstrumentInsuranceService::musicianOverviewFileName() is used.
   *
   * @param string $format Requested Mime-type. The resulting data may have a different mime-type.
   *
   * @return string The generated document data as PHP string.
   *
   */
  public function musicianOverviewLetter(array $overview, ?string $fileName = null, string $format = 'application/pdf')
  {
    $templateName = ConfigService::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD;
    $templateFileName = $this->getDocumentTemplatesPath($templateName);
    if (empty($templateFileName)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('There is no document template for the insurance overview letter. Please upload one in the application\'s orchestra settings, sub-section "Document Templates".'));
    }

    // Prepare the date doing some translations first
    foreach($overview['musicians'] as $id => &$insurance) {
      foreach($insurance['items'] as &$item) {
        $item['scope'] = $this->l->t($item['scope']);
      }
    }

    list($fileData, $mimeType, $generatedFileName) = $this->documentFiller->fill(
      $templateFileName,
      $overview,
      [
        'sender' => 'org.treasurer',
        'recipient' => 'billToParty',
      ],
      $format == 'application/pdf'
    );
    return $fileData;
  }

  /**
   * Take the data provided by self::musicianOverview() to generate a
   * PDF with a DIN-letter in order to send the overview to the
   * respective musician by SnailMail. The resulting letter will be
   * returned as string.
   *
   * @param array $overview Data returned from InstrumentInsuranceService::musicianOverview()
   *
   * @param null|string $fileName
   *
   * @param $dest As used by TCPDF::Output().
   *
   * @return string|object
   *
   */
  public function musicianOverviewLetterLegacy(array $overview, ?string $fileName = null, string $dest = 'S')
  {
    // $this->logInfo(Functions\dump($overview));

    // Find the the treasurer
    /** @var Entities\Participant $treasurer */
    $treasurer = $this->orgaRolesService->getTreasurer()->getMusician();

    // Some styling, however TCPDF does not support all of these. In
    // particular padding and min-width are ignored at all.
    $year = $overview['date']->format('Y');
    $css = "insurance-overview-table";
    $parSkip = 0.5;
    $style = '<style>
  .no-page-break {
    page-break-inside:avoid;
  }
  table.'.$css.' {
    border: 0 solid #000;
    border-collapse:collapse;
    border-spacing:0;
  }
  table.'.$css.' tr.separator, table.'.$css.' tr.separator td {
    line-height:0;
    padding:0;
    margin:0
  }
  table.'.$css.' tr.hidden, table.'.$css.' tr.hidden td {
    color:#FFF;
    text-color:#FFF;
    border-color:#FFF;
    border: 0 solid #FFF;
    padding:0;
    margin:0;
    line-height:0;
    border-bottom: 0.3mm solid #000;
  }
  table.'.$css.' td {
    border: 0.3mm solid #000;
    /* min-width:5em; */
    padding: 0.1em 0.5em 0.1em 0.5em;
    margin:0;
  }
  table.'.$css.' th, table.'.$css.' td.header {
    border: 0.3mm solid #000;
    /* min-width:5em; */
    padding: 0.1em 0.5em 0.1em 0.5em;
    text-align:center;
    font-weight:bold;
    font-style:italic;
    margin:0;
  }
  table.'.$css.' td.musician-head {
    font-weight:bold;
    font-style:italic;
    padding: 0.1em 0.5em 0.1em 0.5em;
    margin:0;
  }
  table.'.$css.' td.summary {
    text-align:right;
  }
  td.tag {
    text-align:center;
  }
  td.money, td.percentage, td.date, td.number {
    text-align:right;
  }
  table.totals, tr.totals {
    font-weight:bold;
  }
</style>';

    // create a PDF object
    $pdf = new PDFLetter($this->configService, 'P', 'mm', 'A4', true, 'UTF-8', false);

    // set document (meta) information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($treasurer->getPublicName(true));
    $pdf->SetTitle($this->l->t('Annual Insurance Fees for %s, %d. %s',
                               [ $overview['billToParty']->getPublicName(true),
                                 $year,
                                 $this->getConfigValue('streetAddressName01'), ]));
    $pdf->SetSubject($this->l->t('Overview over insured instruments and insurance fee details and summary'));
    $pdf->SetKeywords('invoice, insurance, instruments');

    // add a page
    $pdf->addPage();

    // folding marks for DIN-Brief
    $pdf->foldingMarks();

    // Address record
    $pdf->frontHeader(
      'c/o '.$treasurer->getPublicName(true).'<br>'.
      $treasurer->getStreet().'<br>'.
      $treasurer->getPostalCode().' '.$treasurer->getCity().'<br>'.
      'Phone: '.$treasurer->getPhone.'<br>'.
      'EM@il: '.$this->l->t('treasurer').strstr($this->getConfigValue('emailfromaddress'), '@')
    );

    preg_match_all('/([^\s-])[^\s-]*([\s-]+|$)/', $treasurer->getFirstName(), $firstNames);
    $initials = '';
    foreach($firstNames[1] as $idx => $initial) {
      $separator = $firstNames[2][$idx];
      $initials .= $initial.'.'.(ctype_space($separator) ? ' ' : $separator);
    }

    $pdf->addressFieldSender($initials.' '.$treasurer->getSurName().', '.
                             $treasurer->getStreet().', '.
                             $treasurer->getPostalCode().' '.
                             $treasurer->getCity());
    $pdf->addressFieldRecipient(
      $overview['billToParty']->getPublicName(true).'
'.$overview['billToParty']->getStreet().'
'.$overview['billToParty']->getPostalCode().' '.$overview['billToParty']->getCity()
    );

    $pdf->date($this->dateTimeFormatter()->formatDate($overview['date'], 'medium'));

    $pdf->subject($this->l->t("Annular insurance fees for %d", $year));
    $pdf->letterOpen($this->l->t('Dear %s,', $overview['billToParty']->getPublicName(true)));

    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH-PDFLetter::LEFT_TEXT_MARGIN-PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+2*$pdf->fontSize(),
                        $this->l->t('this letter informs you about the details of the instrument-insurances
we are maintaining for you on your behalf. This letter is
machine-generated; in case of any inconsistencies or other questions
please contact us as soon as possible in order to avoid any further
misunderstandings. Please keep a copy of this letter in a safe
place; further insurance-charts may only be sent automatically to you
if something changes.'), '', 1);

    $html = '<h4>'.$this->l->t('Total Insurance Fees %s', $year).'</h4>';
    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH - PDFLetter::LEFT_TEXT_MARGIN - PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+$parSkip*$pdf->fontSize(),
                        $style.$html, '', 1);

    $totals = $overview['annual'];
    $taxRate = floatval(self::TAXES);
    $taxes = $totals * $taxRate;
    $html = '';
    $html .= '
<table class="totals no-page-break">
  <tr>
    <td width="280" class="summary">'.$this->l->t('Annual amount excluding taxes').':'.'</td>
    <td width="80" class="money">'.$this->moneyValue($totals).'</td>
  </tr>
  <tr>
    <td class="summary">'.$this->l->t('%s %% insurance taxes', $this->floatValue($taxRate*100.0)).':'.'</td>
    <td class="money">'.$this->moneyValue($taxes).'</td>
  </tr>
  <tr>
    <td class="summary">'.$this->l->t('Total amount to pay').':'.'</td>
    <td class="money">'.$this->moneyValue($totals+$taxes).'</td>
  </tr>
</table>';

    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH - PDFLetter::LEFT_TEXT_MARGIN - PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY() + -0.8* $pdf->fontSize(),
                        $style.$html, '', 1);
    $html = implode(' ', [
      $this->l->t('The insurance fee is always paid in advance for the next insurance period.'),
      $this->l->t('The amount to pay for newly insured items can be smaller than the regular annual
fee. Partial insurance years are rounded up to full months.'),
      $this->l->t('This is detailed in the table on the following page.'),
    ]);

    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH - PDFLetter::LEFT_TEXT_MARGIN - PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+$parSkip*$pdf->fontSize(),
                        $style.$html, '', 1);

    $html = $this->l->t('The insurance always rolls over to the next year unless explicitly terminated by you.');
    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH - PDFLetter::LEFT_TEXT_MARGIN - PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+$parSkip*$pdf->fontSize(),
                        $style.$html, '', 1);

    $html = $this->l->t('You have granted us a debit-mandate. The total amount due will be debited from your bank-account, no further action from your side is required. We will inform you by email about the date of the debit in good time in advance of the bank transaction.');

    $pdf->writeHtmlCell(PDFLetter::PAGE_WIDTH - PDFLetter::LEFT_TEXT_MARGIN - PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+$parSkip*$pdf->fontSize(),
                        $style.$html, '', 1);

    $pdf->letterClose($this->l->t('Best wishes,'),
                      $treasurer->getPublicName(true).' ('.$this->l->t('Treasurer').')',
                      $this->orgaRolesService->treasurerSignature());

    // Slightly smaller for table
    $pdf->SetFont(PDFLetter::FONT_NAME, '', 10);

    $columnInfo = [
      $this->l->t('Vendor') => [ 'min' => 90, ],
      $this->l->t('Scope') => [ 'min' => 60, 'sample' => array_map(function($v) { return $this->l->t((string)$v); }, Types\EnumGeographicalScope::values()), ],
      $this->l->t('Object') => [ 'min' => 180 ],
      $this->l->t('Manufacturer') => [ 'min' => 120, ],
      $this->l->t('Amount') => [ 'min' => 65, 'sample' => $this->moneyValue(99999.99), ],
      $this->l->t('Rate') => [ 'min' => 45, 'sample' => $this->floatValue(0.0055*100.0).' %', ],
      $this->l->t('Start') => [ 'min' => 65, 'sample' => $this->dateTimeFormatter()->formatDate(time(), 'medium'), ],
      $this->l->t('Valid until') => [ 'min' => 65, 'sample' => $this->dateTimeFormatter()->formatDate(time(), 'medium'), ],
      $this->l->t('Months') => [ 'min' => 50, ],
      $this->l->t('Fee') => [ 'min' => 50, 'sample' => $this->moneyValue(999.99), ],
    ];

    $pdf->SetFont(PDFLetter::FONT_NAME, 'BI', 10);
    foreach ($columnInfo as $key => &$info) {
      if ($info['min'] < 0) {
        continue;
      }
      $info['heading'] = ($pdf->GetStringWidth($key) / PDFLetter::PT + 4) * $pdf->getImageScale();
      if (!is_array($info['sample'])) {
        $info['sample'] = [ $info['sample']??'' ];
      }
      $info['sample'] = array_map(function($v) use ($pdf) {
          return ($pdf->GetStringWidth($v) / PDFLetter::PT + 4) * $pdf->getImageScale();
        }, $info['sample']);
      $info['min'] = max(array_merge([ $info['min'], $info['heading'], ], $info['sample']));
    }
    unset($info);
    $pdf->SetFont(PDFLetter::FONT_NAME, '', 10);

    $html = '<table class="no-page-break" cellpadding="2" class="'.$css.'">
  <tr class="hidden collapsed">';
    foreach ($columnInfo as $key => $info) {
      $width = $info['min'] < 0 ? '' : ' width="'.$info['min'].'"';
      $html .= '
    <td class="header"'.$width.'></td>';
    }
    $html .= '
  </tr>
';
    foreach($overview['musicians'] as $id => $insurance) {
      // $this->logInfo(Functions\dump($insurance));
      // <div class="no-page-break">
      // <h3>'.$this->l->t('Insured Person: %s', array($insurance['name'])).'</h3>
      $html .= '
  <tr>
    <td colspan="10" class="musician-head">'.$this->l->t('Insured Person: %s', array($insurance['name'])).'</td>
  </tr>
  <tr>';
      foreach ($columnInfo as $key => $info) {
        $html .= '
    <td class="header">'.$key.'</td>';
      }
      $html .= '
  </tr>
';
      foreach($insurance['items'] as $item) {
        // regular annual fee
        $html .= '
  <tr>
    <td class="text">'.$item['broker'].'</td>
    <td class="tag">'.$this->l->t($item['scope']).'</td>
    <td class="text">'.$item['object'].'</td>
    <td class="text">'.$item['manufacturer'].'</td>
    <td class="money">'.$this->moneyValue($item['amount']).'</td>
    <td class="percentage">'.$this->floatValue($item['rate']*100.0).' %'.'</td>
    <td class="date">'.$this->dateTimeFormatter()->formatDate($item['start']->getTimestamp(), 'medium').'</td>
    <td class="date">'.$this->dateTimeFormatter()->formatDate($item['due']->getTimestamp(), 'medium').'</td>
    <td class="number">'.$item['fraction']*12.0.'</td>
    <td class="money">'
        . $this->moneyValue($item['fee'])
        . ($item['fraction'] != 1.0 ? '<br>('.$this->moneyValue($item['fullFee']).')' : '')
        .'
    </td>
  </tr>';
      } // end insured items
      $html .= '
  <tr>
    <td class="summary" colspan="9">'.
      $this->l->t('Sub-totals (excluding taxes)').'
    </td>
    <td class="money">'.$this->moneyValue($insurance['subTotals']).'</td>
  </tr>';
    } // end loop over insured musicians

    $html .= '
  <tr class="separator"><td colspan="10"></td></tr>
  <tr>
    <td class="summary" colspan="9">'.
      $this->l->t('Totals excluding taxes').'
    </td>
    <td class="money">'.$this->moneyValue($totals).'</td>
  </tr>
  <tr>
    <td class="summary" colspan="9">'.
      $this->l->t('%s %% insurance taxes', $this->floatValue($taxRate*100.0)).'
    </td>
    <td class="money">'.$this->moneyValue($taxes).'</td>
  </tr>
  <tr class="separator"><td colspan="10"></td></tr>
  <tr class="totals">
    <td class="summary" colspan="9">'.
      $this->l->t('Totals including taxes').'
    </td>
    <td class="money">'.$this->moneyValue((float)$totals+(float)$taxes).'</td>
  </tr>
</table>';

    $pdf->addPage('L');
    $pdf->writeHtmlCell(PDFLetter::PAGE_HEIGHT,//-PDFLetter::LEFT_TEXT_MARGIN-PDFLetter::RIGHT_TEXT_MARGIN,
                        10,
                        PDFLetter::LEFT_TEXT_MARGIN, $pdf->GetY()+1*$pdf->fontSize(),
                        $style.$html, '', 1);

    // Restore font size
    $pdf->SetFont(PDF_FONT_NAME_MAIN, '', PDFLetter::FONT_SIZE);

    //Close and output PDF document
    $fileName = $fileName ?? $this->musicianOverviewFileName($overview);
    return $pdf->Output($fileName, $dest);
  }

  /**
   * Create fake insurance data for testing.
   *
   * @todo This should be move alongside
   * InstrumentationService::getDummyMusisican() to some extra
   * fake-provider class.
   *
   * @return Entities\Musician An unpersistent Entities\Musician
   * filled with enough dummy data to generate an insurance overview
   * letter.
   */
  public function getDummyMusician()
  {
    /** @var Service\InstrumentationService $instrumentationService */
    $instrumentationService = $this->di(Service\InstrumentationService::class);
    $billToParty = $instrumentationService->getDummyMusician(null, false);
    $instrumentHolder = $instrumentationService->getDummyMusician(null, false);

    // fake ids
    $billToParty->setId(PHP_INT_MAX);
    $instrumentHolder->setId(PHP_INT_MAX-1);
    $instrumentHolder->setFirstName($this->l->t('Jane')); // in order to distinguish from the bill-to-party

    $oneInsuranceBroker = (new Entities\InsuranceBroker)
                        ->setShortName('LaInsurance')
                        ->setLongName('La Insurance KG')
                        ->setAddress($this->l->t('unknown'));
    $otherInsuranceBroker = (new Entities\InsuranceBroker)
                          ->setShortName('InsuRance')
                          ->setLongName('Insolventus Maximus')
                          ->setAddress($this->l->t('unknown'));
    $germanyRate = (new Entities\InsuranceRate)
                 ->setBroker($oneInsuranceBroker)
                 ->setGeographicalScope(Types\EnumGeographicalScope::GERMANY())
                 ->setRate(0.0043)
                 ->setDueDate('2014-07-01')
                 ->setPolicyNumber('1234567890');
    $europeRate = (new Entities\InsuranceRate)
                ->setBroker($oneInsuranceBroker)
                ->setGeographicalScope(Types\EnumGeographicalScope::EUROPE())
                ->setRate(0.0051)
                ->setDueDate('2014-07-01')
                ->setPolicyNumber('1234567890');
    $worldRate = (new Entities\InsuranceRate)
               ->setBroker($otherInsuranceBroker)
               ->setGeographicalScope(Types\EnumGeographicalScope::WORLD())
               ->setRate(0.0068)
               ->setDueDate('2014-04-01')
               ->setPolicyNumber('1234567890');

    $dataItems = [
      [ "instrument_holder","bill_to_party","insurance_rate","object","accessory","manufacturer","year_of_construction","insurance_amount","start_of_insurance"],
      [ $billToParty,$billToParty,$europeRate,"Violoncello",false,"unbekannt","Ende 19. Jhdt.","15000","2013-06-11"],
      [ $billToParty,$billToParty,$europeRate,"Bogen Violoncello",true,"Seifert","unbekannt","2500","2013-06-11"],
      [ $billToParty,$billToParty,$worldRate,"Bogen Violincello, Sartory-Modell",true,"Seifert","unbekannt","4500","2013-06-11"],
      [ $billToParty,$billToParty,$worldRate,"Cellokoffer",true,"","unbekannt","1500","2013-06-11"],
      [ $instrumentHolder,$billToParty,$germanyRate,"Violine",false,"","unbekannt","2500","2013-06-11"],
      [ $instrumentHolder,$billToParty,$europeRate,"Bogen Violine",true,"","unbekannt","500","2013-06-11"],
      [ $instrumentHolder,$billToParty,$worldRate,"Geigenkasten",true,"","unbekannt","300","2013-06-11"],
    ];
    $keys = array_shift($dataItems);
    foreach ($dataItems as $data) {
      $data = array_combine($keys, $data);
      /** @var Entities\InstrumentInsurance $insuranceItem */
      $insuranceItem = new Entities\InstrumentInsurance;
      foreach ($data as $key => $value) {
        $insuranceItem[$key] = $value;
      }

      $insuranceItem->getBillToParty()->getPayableInsurances()->add($insuranceItem);
      $insuranceItem->getInstrumentHolder()->getInstrumentInsurances()->add($insuranceItem);
    }

    return $billToParty;
  }

};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service\Finance;

use DateTimeZone;
use DateTimeImmutable as DateTime;
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
  use \OCA\CAFEVDB\Traits\FlattenEntityTrait;
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

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    OrganizationalRolesService $orgaRolesService,
    OpenDocumentFiller $documentFiller,
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
      $date = new DateTime();
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
    $timeZone = new DateTimeZone('UTC'); // $this->getDateTimeZone();
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
      // day. E.g.: Start 01.07.YYYY, end 30.06.ZZZZ should yield one year and
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
   * date. Only the calendar-year of $date according to the timezone.
   *
   * @return array<int, float>
   * ```[ YEAR => VALUE ]```
   *
   * @todo What happens if the rates change? The rates should have a
   * validity time-range.
   *
   * @todo This function appears to be unused.
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
      for ($year = $startYear; $year <= $yearUntil; ++$year) {
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
   * @param int|Entities\Musician $musicianOrId Database entity or id.
   *
   * @param string|DateTime $date
   *
   * @param null|array $dueInterval Return the minimum and maximum due dates
   * found for the musician.
   *
   * @return float Insurance fees computed.
   */
  public function insuranceFee(mixed $musicianOrId, $date = null, ?array &$dueInterval = null):float
  {
    $timeZone = $this->getDateTimeZone();
    if (empty($date)) {
      $date = new DateTime();
    }
    $date = self::convertToTimezoneDate($date, $timeZone);

    $payables = $this->billableInsurances($musicianOrId);

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
   *
   * @param bool $translate Translate the geographical scope names.
   *
   * @param bool $nested Affects the layout of the returned array. \true means
   * to return a nested array
   * ```
   * [ BROKER => [ SCOPE => RATE, ... ], ... ]
   * ```
   * is returned. \false means to return an array
   * ```
   * [ BROKERSCOPE => [ 'rate' => RATE, 'due' => DUEDATE, 'policy' => POLICYNUMBER ], ... ]
   * ```.
   *
   * @return array Depending on argument $nested.
   */
  public function getRates(bool $translate = false, bool $nested = false):array
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
   * Fetch all the insurance brokers from the data-base.
   *
   * @return array  An array indexed by the short name of the broker.
   */
  public function getBrokers():array
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
   *   'billTo' => MUSICIAN_ENTITY
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
   *
   * @param int|Entities\Musician $musicianOrId Database entity or its id.
   *
   * @param null|DateTime $date Determines the insurance year.
   *
   * @return array
   */
  public function musicianOverview(mixed $musicianOrId, ?DateTime $date = null):array
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
      'billTo' => $this->flattenMusician($billToParty, only: []),
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
    foreach ($insuranceOverview['musicians'] as $id => $info) {
      // ordinary annular fees
      $subTotals = 0.0;
      foreach ($info['items'] as $itemInfo) {
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
   * Small support function in order to generate a consistent
   * file-name for the exported PDFs.
   *
   * @param array $overview As computed by musicianOverview().
   *
   * @return string
   */
  public function musicianOverviewFileName(array $overview):string
  {
    /** @var Entities\Musician $billToParty */
    $billToParty = $overview['billTo'];

    $userIdSlug = $billToParty['userIdSlug'];
    $camelCaseSlug = Util::dashesToCamelCase($userIdSlug, true, '_-.');

    $year = $overview['date']->format('Y');

    $components = [
      $this->timeStamp(),
      $billToParty['id'],
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
   * @param array $overview Data returned from InstrumentInsuranceService::musicianOverview().
   *
   * @param string $format Requested Mime-type. The resulting data may have a different mime-type.
   *
   * @return string The generated document data as PHP string.
   */
  public function musicianOverviewLetter(array $overview, string $format = 'application/pdf')
  {
    $templateName = ConfigService::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD;
    $templateFileName = $this->getDocumentTemplatesPath($templateName);
    if (empty($templateFileName)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('There is no document template for the insurance overview letter. Please upload one in the application\'s orchestra settings, sub-section "Document Templates".'));
    }

    // Prepare the date doing some translations first
    foreach ($overview['musicians'] as &$insurance) {
      foreach ($insurance['items'] as &$item) {
        $item['scope'] = $this->l->t($item['scope']);
      }
    }

    list($fileData, /* $mimeType, $generatedFileName */) = $this->documentFiller->fill(
      $templateFileName, [
        'instins' => $overview,
      ], [
        'sender' => 'org.treasurer',
        'recipient' => 'instins.billTo',
      ],
      $format == 'application/pdf'
    );
    return $fileData;
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
}

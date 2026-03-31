<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine
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
use DateTimeInterface;

use OCA\CAFEVDB\Toolkit\Common\RationalNumber;
use OCA\CAFEVDB\Toolkit\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Toolkit\Common\DecimalRationalP2S2 as TaxRateNumberType;
use OCA\CAFEVDB\Toolkit\Common\DecimalRationalP4S4 as InsuranceRateNumberType;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Documents\OpenDocumentFiller;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Toolkit\Doctrine\ORM\EntitySerializer\EntityArrayAdapter;

/** Collective instrument insurance. */
class InstrumentInsuranceService
{
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EnsureEntityTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const ENTITY = Entities\InstrumentInsurance::class;

  /** @var Repositories\InstrumentInsurancesRepository */
  private $insurancesRepository;

  /**
   * The insurance tax rate.
   */
  private ?TaxRateNumberType $taxRate = null;

  /** {@inheritdoc} */
  public function __construct(
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    private OrganizationalRolesService $orgaRolesService,
    private OpenDocumentFiller $documentFiller,
  ) {
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
   * @return RationalNumber Fraction with denominator 12 (full months)
   */
  private function yearFraction(
    DateTimeInterface $insuranceStart,
    ?DateTimeInterface $insuranceEnd,
    DateTimeInterface $dueDate,
  ): RationalNumber
  {
    $timeZone = new DateTimeZone('UTC'); // $this->getDateTimeZone();
    $startDate = self::convertToTimezoneDate(self::convertToDateTime($insuranceStart), $timeZone);
    $dueDate = self::convertToTimezoneDate(self::convertToDateTime($dueDate), $timeZone);

    $startDistance = $startDate->diff($dueDate);

    // $dueDate is before $insuranceStart
    if ($startDistance->invert) {
      return new RationalNumber(0, 0, 12);
    }

    // for our purpose everything > 0 days is a month, we only charge
    // full months
    $startDistance->d = 0;

    $months = $startDistance->y > 0 ? 12 : $startDistance->m;

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
          return new RationalNumber(0, 0, 12);
        }
        $endDistance->d = 0; // just include fractional months
        $months -= $endDistance->m;
      }
    }

    $fraction = new RationalNumber(0, $months, 12);

    return $fraction;
  }

  /**
   * Return all insurance items which are billable to the given musician.
   *
   * @param int|string|Entities\Musician $musicianOrId
   *
   * @param null|string|Entities\InsuranceBroker $broker Short name (db id) or
   * database entity or null. If null compute the fee for all brokers,
   * otherwise only for the given one.
   *
   * @return array
   */
  public function billableInsurances(
    int|string|Entities\Musician $musicianOrId,
    null|string|Entities\InsuranceBroker $broker,
  ):array {
    $criteria = [ 'billToParty' => $musicianOrId ];
    if ($broker !== null) {
      $criteria['insuranceRate.broker'] = $broker;
    }
    return $this->insurancesRepository->findBy($criteria);
  }

  /**
   * Fetch the tax rate from the TaxationStatutorySources table.
   *
   * @return TaxRateNumberType
   */
  public function getTaxRate(): TaxRateNumberType
  {
    if ($this->taxRate !== null) {
      return $this->taxRate;
    }
    $taxItems = $this->getDatabaseRepository(Entities\TaxationStatutorySource::class)->findBy(['taxType' => Types\EnumTaxType::INSURANCE]);
    if (empty($taxItems)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to determine the insurance tax rate. Please populate the TaxationStatutorySources table with appropriate items.'),
      );
    } elseif (count($taxItems) > 1) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('More than one possible insurance tax rate found. Please check the TaxationStatutorySources table.'),
      );
    }
    $this->taxRate = $taxItems[0]->getRate();
    return $this->taxRate;
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
   * @param null|string|Entities\InsuranceBroker $broker Short name (db id) or
   * database entity or null. If null compute the fee for all brokers,
   * otherwise only for the given one.
   *
   * @param string|DateTime $date
   *
   * @param null|array $dueInterval Return the minimum and maximum due dates
   * found for the musician.
   *
   * @return MonetaryNumberType Insurance fees computed.
   */
  public function insuranceFee(
    mixed $musicianOrId,
    null|string|Entities\InsuranceBroker $broker,
    $date = null,
    ?array &$dueInterval = null,
  ): MonetaryNumberType {
    $timeZone = $this->getDateTimeZone();
    if (empty($date)) {
      $date = new DateTime();
    }
    $date = self::convertToTimezoneDate($date, $timeZone);

    $payables = $this->billableInsurances($musicianOrId, $broker);

    $taxFactor = $this->getTaxRate()->add(1);

    $fee = MonetaryNumberType::zero();
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

      $annualFee = $rate->getRate()->mul($insurance->getInsuranceAmount());
      $annualFee->mulEq($this->yearFraction($insuranceStart, $insuranceEnd, $dueDate));

      $fee->addEq($annualFee->mul($taxFactor));
    }
    $dueInterval = [ 'min' => $minDueDate, 'max' => $maxDueDate ];

    return $fee->round(2);
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
      $brokerEntity = $entity->getBroker();
      $shortBroker = $brokerEntity->getShortName();
      $rateKey = $shortBroker . $scope;
      $dueDate = $entity->getDueDate();
      if (!empty($dueDate)) {
        $dueDate = $this->dueDate($dueDate);
      }
      $rates[$rateKey] = [
        'rate' => $entity->getRate(),
        'due' => $dueDate,
        'policy' => $entity->getPolicyNumber(),
        'scope' => $scope,
        'broker' => $shortBroker,
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
   * @param null|string|Entities\InsuranceBroker $broker Short name (db id) or
   * database entity or null. If null compute the fee for all brokers,
   * otherwise only for the given one.
   *
   * @param null|DateTime $date Determines the insurance year.
   *
   * @param null|string|Entities\InsuranceBroker $broker Short name (db id) or
   * database entity or null. If null compute the fee for all brokers,
   * otherwise only for the given one.
   *
   * @return array
   */
  public function musicianOverview(
    int|Entities\Musician $musicianOrId,
    null|string|Entities\InsuranceBroker $broker = null,
    ?DateTime $date = null,
  ):array {
    $timeZone = $this->getDateTimeZone();
    if (empty($date)) {
      $date = new DateTime();
    }
    $date = self::convertToTimezoneDate($date, $timeZone);

    /** @var Entities\Musician $musician */
    $billToParty = $this->ensureMusician($musicianOrId);

    $payableInsurances = $billToParty->getPayableInsurances();
    if ($broker !== null) {
      if ($broker instanceof Entities\InsuranceBroker) {
        $broker = $broker->getShortName();
      }
      $payableInsurances = $payableInsurances->filter(
        fn(Entities\InstrumentInsurance $insurance) => $insurance->getBroker()->getShortName() == $broker,
      );
    }

    $insuranceOverview = [
      'billTo' => EntityArrayAdapter::create($billToParty, depth: 0),
      'taxRate' => $this->getTaxRate(),
      'broker' => $broker,
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
      $endDate = $dueDate->modify('-1 day'); // last day of insurance year

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
      $annualFee = $rate->getRate()->mul($amount);

      $instrumentHolder = $insurance->getInstrumentHolder();
      $instrumentHolderId = $instrumentHolder->getId();
      if (empty($insuranceOverview['musicians'][$instrumentHolderId])) {
        $insuranceOverview['musicians'][$instrumentHolderId] = [
          'name' => $instrumentHolder->getPublicName(true),
          'subTotals' => MonetaryNumberType::create(0),
          'items' => [],
        ];
      }

      $itemInfo = [
        'broker' => $insurance->getBroker()->getShortName(),
        'scope' => $insurance->getGeographicalScope(),
        'object' => $insurance->getObject(),
        'manufacturer' => $insurance->getManufacturer(),
        'amount' => $amount,
        'rate' => $rate->getRate(),
        'lastDue' => $lastDueDate,
        'due' => empty($insuranceEnd) ? $endDate : $insuranceEnd,
        'start' => $insuranceStart,
        'fullFee' => $annualFee,
        'fraction' => $fraction,
        'fee' => $annualFee->mul($fraction),
      ];

      $insuranceOverview['musicians'][$instrumentHolderId]['items'][] = $itemInfo;
    }

    $annual = MonetaryNumberType::zero();
    foreach ($insuranceOverview['musicians'] as $id => $info) {
      // ordinary annular fees
      $subTotals = MonetaryNumberType::zero();
      foreach ($info['items'] as $itemInfo) {
        $subTotals = $subTotals->add($itemInfo['fee']);
      }
      $insuranceOverview['musicians'][$id]['subTotals'] = $subTotals;
      // $this->logInfo('SUBTOTALS '.$subTotals);
      $annual = $annual->add($subTotals);
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
    $templateName = ConfigConstants::DOCUMENT_TEMPLATE_INSTRUMENT_INSURANCE_RECORD;
    $templateFileName = $this->getDocumentTemplatesPath($templateName);
    if (empty($templateFileName)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('There is no document template for the insurance overview letter. Please upload one in the application\'s orchestra settings, sub-section "Document Templates".'));
    }

    // Prepare the data doing some translations first
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
                 ->setGeographicalScope(Types\EnumGeographicalScope::GERMANY)
                 ->setRate(0.0043)
                 ->setDueDate('2014-07-01')
                 ->setPolicyNumber('1234567890');
    $europeRate = (new Entities\InsuranceRate)
                ->setBroker($oneInsuranceBroker)
                ->setGeographicalScope(Types\EnumGeographicalScope::EUROPE)
                ->setRate(0.0051)
                ->setDueDate('2014-07-01')
                ->setPolicyNumber('1234567890');
    $worldRate = (new Entities\InsuranceRate)
               ->setBroker($otherInsuranceBroker)
               ->setGeographicalScope(Types\EnumGeographicalScope::WORLD)
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

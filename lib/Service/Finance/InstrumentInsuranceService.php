<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Common\Util;

/** Collective instrument insurance. */
class InstrumentInsuranceService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const ENTITY = Entities\InstrumentInsurance::class;
  const TAXES = 0.19; // ?? make this configurable ??

  /** @var Repositories\InstrumentInsurancesRepository */
  private $insurancesRepository;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
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
   * @todo Perhaps use the timezone at the location of the orchestra
   * or insurance agency.
   */
  public function dueDate($dueDate, $date = null)
  {
    $timeZone = $this->getDateTimeZone($dateStamp);
    if (empty($date)) {
      $date = new \DateTimeImmutable();
    }
    $date = Util::dateTime($date)->setTimezone($timeZone);
    $year = $date->format('Y');

    $dueDate = Util::dateTime($dueDate)
             ->setTimezone($timeZone)
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
   * @param string|\DateTimeInterface $insuranceStart The start-date
   * of the instrument insurance.
   *
   * @param string|\DateTimeInterface $dueDate The start of the
   * insurance year for this contract.
   *
   * @param bool $currentYearOnly Only take the current insurance-year
   * into account, yielding a fraction of 1 for all year safe the
   * first one after $insuranceStart.
   *
   * @return Fraction
   *
   */
  private static function yearFraction($insuranceStart, $dueDate, bool $currentYearOnly = false)
  {
    $timeZone = $this->getDateTimeZone($dateStamp);
    $startDate = Util::dateTime($insuranceStart)->setTimezone($timeZone);
    $dueDate = Util::dateTime($dueDate)->setTimezone($timeZone);

    $distance = $startDate->diff($dueDate);

    // $dueDate is before $insuranceStart
    if ($distance->invert) {
      return 0.0;
    }

    // for our purpose everything > 0 days is a month, we only charge
    // full months
    $distance->d = 0;

    if ($currentYearOnly) {
      $fraction = $distance->y > 0 ? 1.0 : $distance->m / 12.0;
    } else {
      $fraction = $distance->y + $distance->m / 12.0;
    }
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
    $timeZone = $this->getDateTimeZone($dateStamp);
    if (empty($date)) {
      $date = new DateTime();
    }
    $yearUntil = $date->setTimezone($timeZone)->format('Y');

    $result = [];

    $insurances = $this->insurancesRepository->findBy([ 'billToParty' => $musicianOrId ]);
    /** @var Entities\InstrumentInsurance $insurance */
    foreach ($insurances as $insurance) {
      $amount = $insurance->getInsuranceAmount();
      /** @var Entities\InsuranceRate $rate */
      $rate = $insurance->getInsuranceRate();
      $annualFee = $amount * $rate->getRate();

      $insuranceStart = $insurance->getStartOfInsurance()->setTimezone($timeZone);
      $insuranceEnd = $insurance->getDeleted()->setTimezone($timeZone);
      $startYear = $insuranceStart->format('Y');

      for ($year = $startYear - 1; $year <= $currentYear; ++$year) {
        $dueDate = $this->dueDate($rate->getDueDate(), $year.'-06-01');
        if ($dueDate > $insuranceEnd) {
          continue;
        }
        $yearFraction = $this->yearFraction($insuranceStart, $dueDate, true);
        if ($yearFraction == 0.0) {
          continue;
        }
        $fee = $yearFraction * $annualFee * self::TAXES;
        $result[$year] = ($result[$year] ?? 0.0) + $fee;
      }
    }
    return $result;
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
  public function insuranceFee($musicianId, $date = null, bool $currentYearOnly = true)
  {
    $timeZone = $this->getDateTimeZone($dateStamp);
    if (empty($date)) {
      $date = new DateTime();
    }
    $date = $date->setTimezone($timeZone);

    $payables = $this->insurancesRepository->findBy([ 'billToParty' => $musicianId ]);

    $fee = 0.0;
    /** @var Entities\InstrumentInsurance $insurance */
    foreach ($payables as $insurance) {
      $insuranceStart = $insurance->getStartOfInsurance()->setTimezone($timeZone);
      $insuranceEnd = $insurance->getDeleted()->setTimezone($timeZone);

      $dueDate = $this->dueDate($rate->getDueDate(), $date);
      if ($dueDate > $insuranceEnd) {
        continue;
      }

      $amount = $insurance->getInsuranceAmount();
      /** @var Entities\InsuranceRate $rate */
      $rate = $insurance->getInsuranceRate();
      $annualFee = $amount * $rate->getRate();
      $annualFee *= $this->yearFraction($insuranceStart, $dueDate, $currentYearOnly);
      $fee += $annualFee * self::TAXES;
    }
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
  public function getRates($translate = false)
  {
    $rates = [];
    $entities = $this->getDatabaseRepository(Entities\InsuranceRate::class)->findAll();
    foreach ($entities as $rate) {
      $scope = (string)$entity['geographicalScope'];
      if ($translate) {
        $scope = $this->l->t($scope);
      }
      $rateKey = $entity['broker'].$scope;
      $dueDate = $entity['dueDate'];
      if (!empty($dueDate)) {
        $dueDate = $this->dueDate($dueDate);
      }
      $rates[$rateKey] = [
        'rate' => $entity['rate'],
        'due' => $dueDate,
        'policy' => $entity['policyNumber'],
      ];
    }
    return $rates;
  }

  /**
   * Fetch all the insurance brokers from the data-base, return an
   * array indexed by the short name.
   */
  public function getBrokers()
  {
    $brokers = [];
    $entities = $this->getDatabaseRepository(Entities\InsuranceBroker::class)->findAll();
    foreach ($entities as $entity) {
      $key = $entity['shortName'];
      $brokers[$key] = [
        'shortName' => $entity['shortName'],
        'name' => $entity['longName'],
        'address' => $entity['address'],
      ];
    }

    return $brokers;
  }


};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

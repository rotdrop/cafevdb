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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

/** Finance and bank related stuff. */
class InsuranceService
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
   * Compute the next due date. The insurance contracts "always" last
   * for one year and are payed in advance, so it is always the next
   * due-date in the future.
   *
   * @param DateTime|string $dueDate Start of the insurance contract.
   *
   * @param int $priorMonths Shift $data this many months to the
   * future, e.g. to prepare debit notes one months before the actual
   * new insurance year.
   *
   * @param $date The point in time we are looking at. If
   * unspecified then this is the current date.
   */
  public function dueDate($dueDate, $priorMonths = 0, $date = null)
  {
    $oldTZ = date_default_timezone_get();
    //$tz = $this->getTimezone();
    $tz = 'Europe/Berlin';
    date_default_timezone_set($tz);

    if (!$date) {
      $dateStamp = time();
    } else {
      $dateStamp = strtotime($date);
    }

    $date = new \DateTime();
    $date->setTimestamp($dateStamp);
    $data->modify("+ ".$priorMonths." months");

    $dueDate = new \DateTime($dueDate);

    $distance = $dueDate->diff($date);
    $years = 1 + $distance->format('%y');

    $dueDate->modify("+ ".$years." years");

    date_default_timezone_set($oldTZ);

    return $dueDate;
  }

  /**
   * Compute the fraction (possibly larger than 1) of the annual insurance
   * fees given $startDate and $dueDate. The actual fraction may be
   * larger than 1 if the distance to $dueDate is larger than a year.
   *
   * The time slots or rounded down to full-months.
   *
   * @param $insuranceStart The start-date of the instrument
   * insurance. A string suitable to initialize a PHP
   * DateTime-object.
   *
   * @param $dueDate The start of the insurance year for this
   * contract. This should be a PHP DateTime-object.
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
    if (is_string($dueDate)) {
      $dueDate = new \DateTime($dueDate);
    }

    $startDate = new \DateTime($insuranceStart);
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
   * Compute the annual insurance fee for the respective musician up
   * to the given date.
   *
   * @param int $musicianId
   *
   * @param string|\DateTime $date
   *
   * @param bool $currentYearOnly
   */
  public function insuranceFee($musicianId, $date, bool $currentYearOnly = true)
  {
    if (empty($date)) {
      $date = new \DateTime();
    }

    $payables = $this->insurancesRepository->findBy([ 'billToParty' => $musicianId ]);

    $fee = 0.0;
    foreach ($payables as $insurance) {
      $amount = $insurance['insuranceAmount'];
      $rate = $insurance['insuranceRate'];
      $dueDate = $this->dueDate($rate['dueDate']);
      $annualFee = $amount * $rate['$rate'];
      $annualFee *= $this->yearFraction($insurance['startOfInsurance'], $date, $currentYearOnly);
      $fee += $annualFee * self::TAXES;
    }
    return round($fee, 2);
  }


};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

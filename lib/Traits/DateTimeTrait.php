<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Traits;

use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \InvalidArgumentException;

/** Support traits for date-time stuff */
trait DateTimeTrait
{
  /**
   * Ensure a valid date.
   *
   * @param null|DateTimeInterface $dateTime
   *
   * @return DateTimeInterface
   */
  public static function ensureDate(?DateTimeInterface $dateTime):DateTimeInterface
  {
    return $dateTime ?? (new DateTimeImmutable)->setTimestamp(1);
  }

  /**
   * Set
   *
   * @param string|int|\DateTimeInterface $dateTime
   *
   * @return null|\DateTimeImmutable
   */
  public static function convertToDateTime($dateTime):?DateTimeImmutable
  {
    if ($dateTime === null || $dateTime === '') {
      return null;
    } elseif (!($dateTime instanceof DateTimeInterface)) {
      $timeStamp = filter_var($dateTime, FILTER_VALIDATE_INT, [ 'min_range' => 0 ]);
      if ($timeStamp === false) {
        $timeStamp = filter_var($dateTime, FILTER_VALIDATE_FLOAT, [ 'min_range' => 0 ]);
      }
      if ($timeStamp !== false) {
        return (new DateTimeImmutable())->setTimestamp($timeStamp);
      } elseif (is_string($dateTime)) {
        return new DateTimeImmutable($dateTime);
      } else {
        throw new InvalidArgumentException('Cannot convert input to DateTime.');
      }
    } elseif ($dateTime instanceof DateTime) {
      return DateTimeImmutable::createFromMutable($dateTime);
    } elseif ($dateTime instanceof DateTimeImmutable) {
      return $dateTime;
    } else {
      throw new InvalidArgumentException('Unsupported date-time class: '.get_class($dateTime));
    }
    return null; // not reached
  }

  /** Reinterprete the date portion of a \DateTimeInterface object at time 00:00:00 in another time-zone. */
  public static function convertToTimezoneDate(DateTimeInterface $date, DateTimeZone $timeZone):DateTimeImmutable
  {
    return \DateTimeImmutable::createFromFormat('Y-m-d|', $date->format('Y-m-d'), $timeZone);
  }

}

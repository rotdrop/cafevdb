<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Traits;

use DateTimeZone;
use DateTimeInterface;
use DateTimeImmutable;

use OCP\IDateTimeZone;
use OCP\AppFramework\IAppContainer;

/** Generate a time-stamp strings. */
trait TimeStampTrait
{
  /** @var IDateTimeZone */
  protected $dateTimeZone;

  /** @var IAppContainer */
  protected $appContainer;

  /**
   * Get the current timezone
   *
   * @param bool|int $timeStamp
   *
   * @return DateTimeZone
   */
  public function getDateTimeZone(mixed $timeStamp = false):DateTimeZone
  {
    if (empty($this->dateTimeZone)) {
      $this->dateTimeZone = $this->appContainer->get(IDateTimeZone::class);
    }
    return $this->dateTimeZone->getTimeZone($timeStamp);
  }

  /**
   * Format the given date according to $format and $timeZone to a
   * human readable time-stamp, providing defaults for $format and
   * using the default time-zone if none is specified.
   *
   * @param null|int|DateTimeInterface $date
   *
   * @param null|string $format
   *
   * @param null|DateTimeZone $timeZone
   *
   * @return string
   */
  public function formatTimeStamp($date = null, ?string $format = null, ?DateTimeZone $timeZone = null):string
  {
    if ($date === null) {
      $date = new DateTimeImmutable;
    } elseif (!($date instanceof \DateTimeInterface)) {
      $date = (new DateTimeImmutable())->setTimestamp($date);
    }

    if (empty($format)) {
      $format = 'Ymd-His-T';
    }
    if (empty($timeZone)) {
      $timeZone = $this->getDateTimeZone();
    }
    return $date->setTimeZone($timeZone)->format($format);
  }

  /**
   * Call ConfigService::formatTimeStamp() with the current date and time.
   *
   * @param null|string $format
   *
   * @param null|\DateTimeZone $timeZone
   *
   * @return string
   */
  public function timeStamp(?string $format = null, ?DateTimeZone $timeZone = null):string
  {
    return $this->formatTimeStamp(new DateTimeImmutable, $format, $timeZone);
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Common;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Like ITimeFactory but stop using DateTime in favour of DateTimeImmutable.
 *
 * @method DateTimeImmutable now()
 *
 * @method int getTime()
 * Result of calling \time().
 *
 * @method DateTime getDateTime(string $time = 'now', ?DateTimeZone $timezone = null)
 * Return a DateTime object with the given DateTimeZone which defaults
 * to UTC or the timezone configured by generating an instance via
 * static::withTimeZone().
 *
 * @method DateTimeImmutable now() The current date-time in timezone UTC.
 *
 * @method TimeFactory withTimeZone(DateTimeZone $timezone) A clone attached to the given timezone.
 *
 * @method DateTimeZone getTimeZone(?string $timezone = null) Return a
 * DateTimeZone object. If $timezone is omitted the attached timezone of this
 * instance ist returned.
 */
class TimeFactory implements ITimeFactory
{
  private ITimeFactory $timeFactory;

  /**
   * @param ITimeFactory $timeFactory
   */
  public function __construct(
  ) {
    $this->timeFactory = \OCP\Server::get(ITimeFactory::class);
  }

  /** {@inheritdoc} */
  public function now(): DateTimeImmutable
  {
    return $this->timeFactory->now();
  }

  /** {@inheritdoc} */
  public function getTime(): int
  {
    return $this->timeFactory->getTime();
  }

  /** {@inheritdoc} */
  public function getDateTime(string $time = 'now', ?\DateTimeZone $timezone = null): \DateTime
  {
    return $this->timeFactory->getDateTime($time, $timezone);
  }

  /** {@inheritdoc} */
  public function withTimeZone(\DateTimeZone $timezone): static
  {
    $clone = clone $this;
    $clone->timeFactory = $this->timeFactory->withTimeZone($timezone);
    return $clone;
  }

  /** {@inheritdoc} */
  public function getTimeZone(?string $timezone = null): \DateTimeZone
  {
    return $this->timeFactory->getTimeZone($timezone);
  }

  /**
   * Like the parent class but returning an instance of DateTimeImmutable.
   *
   * @param string $time Anything understood by the constructor of
   * DateTimeImmutable. Defaults to literal 'now'.
   *
   * @param ?DateTimeZone $timezone DateTimeZone instance defaulting to UTC if
   * omitted.
   *
   * @return DateTimeImmutable
   */
  public function getDateTimeImmutable(string $time = 'now', ?DateTimeZone $timezone = null): DateTimeImmutable
  {
    return DateTimeImmutable::createFromMutable($this->timeFactory->getDateTime($time, $timezone));
  }

  /**
   * A wrapper around time_sleep_until().
   *
   * @param float $timestamp Argument for time_sleep_until() (see PHP doc).
   *
   * @return bool
   */
  public function sleepUntil(float $timestamp): bool
  {
    return \time_sleep_until($timestamp);
  }
}

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

use OC\AppFramework\Utility\TimeFactory as CoreTimeFactory;

/**
 * Like ITimeFactory but stop using DateTime in favour of DateTimeImmutable.
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
class TimeFactory extends CoreTimeFactory
{
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
    return DateTimeImmutable::createFromMutable($this->getDateTime($time, $timezone));
  }
}

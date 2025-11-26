<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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
/**
 * Based on, with "At" removed from the names, i.e. updatedAt replaced
 * by updated etc.
 *
 * Timestampable Trait, usable with PHP >= 5.4
 *
 * @author Gediminas Morkevicius <gediminas.morkevicius@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Traits;

use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable;
use OCA\CAFEVDB\Toolkit\Traits as ToolkitTraits;

/**
 * Like Toolkit\DateTimeTrait, but convert to Carbon.
 */
trait DateTimeTrait
{
  use ToolkitTraits\DateTimeTrait {
    ToolkitTraits\DateTimeTrait::convertToDateTime as convertToDateTimeImmutable;
  }

  /**
   * @param null|string|int|float|\DateTimeInterface $dateTime
   *
   * @return null|\DateTimeImmutable
   */
  public static function convertToDateTime($dateTime):?CarbonImmutable
  {
    $dateTimeImmutable = self::convertToDateTimeImmutable($dateTime);
    return $dateTimeImmutable ? CarbonImmutable::instance($dateTimeImmutable) : null;
  }
}

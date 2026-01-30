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

namespace OCA\CAFEVDB\Service\DTO;

use DateTimeInterface;
use OCA\CAFEVDB\Wrappper\Carbon\CarbonImmutable;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;
use OCA\CAFEVDB\Common\Uuid;

/**
 * DTO for the orchestra locale.
 */
class EventTimes extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  public readonly HumanDateTime $start;
  public readonly HumanDateTime $end;

  /** {@inheritdoc} */
  public function __construct(
    public readonly string $timezone,
    public readonly string $locale,
    public readonly bool $allDay,
    array|HumanDateTime $start,
    array|HumanDateTime $end,
  ) {
    $this->start = is_array($start) ? HumanDateTime::fromArray($start) : $start;
    $this->end = is_array($end) ? HumanDateTime::fromArray($end) : $end;
  }

  /**
   * Initialize from the given array.
   *
   * @param array $data
   *
   * @return self
   */
  public static function fromArray(array $data): self
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    return new self(
      timezone: $timezone,
      locale: $locale,
      allDay: $allDay,
      start: $start,
      end: $end,
    );
  }
}

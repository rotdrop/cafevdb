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

use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;
use OCA\CAFEVDB\Common\Uuid;

/**
 * DTO for the event matrix events.
 */
class EventMatrixEvent extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  public readonly ?CarbonImmutable $deleted;
  public readonly CarbonImmutable $seriesStart;
  public readonly CarbonImmutable $start;
  public readonly CarbonImmutable $end;
  public readonly UuidInterface $seriesUid;
  public readonly EventTimes $times;
  public readonly string $instanceId;

  /** {@inheritdoc} */
  public function __construct(
    public readonly int $projectId,
    public readonly string $uri,
    public readonly string $uid,
    ?DateTimeInterface $deleted,
    public readonly int $calendarId,
    public readonly int $sequence,
    public readonly int $recurrenceId,
    null|string|UuidInterface $seriesUid,
    public readonly int $absenceField,
    DateTimeInterface $start,
    DateTimeInterface $end,
    public readonly bool $allDay,
    public readonly string $summary,
    public readonly string $description,
    public readonly string $location,
    /** @var array<string> */
    public readonly array $categories,
    DateTimeInterface $seriesStart,
    public readonly string $urlPath,
    array|EventTimes $times,
  ) {
    $this->deleted = $deleted !== null ? CarbonImmutable::instance($deleted) : null;
    $this->seriesUid = !empty($seriesUid) ? Uuid::asUuid($seriesUid) : Uuid::nil();
    $this->start = CarbonImmutable::instance($start);
    $this->end = CarbonImmutable::instance($end);
    $this->seriesStart = CarbonImmutable::instance($seriesStart);
    $this->times = is_array($times) ? EventTimes::fromArray($times) : $times;

    $this->instanceId = $this->uri . ($this->recurrenceId ? '@' . $this->recurrenceId : '');
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
      absenceField: $absenceField,
      allDay: $allDay,
      calendarId: $calendarId,
      categories: $categories,
      deleted: $deleted,
      description: $description,
      end: $end,
      location: $location,
      projectId: $projectId,
      recurrenceId: $recurrenceId,
      sequence: $sequence,
      seriesStart: $seriesStart,
      seriesUid: $seriesUid,
      start: $start,
      summary: $summary,
      times: $times,
      uid: $uid,
      uri: $uri,
      urlPath: $urlPath,
    );
  }
}

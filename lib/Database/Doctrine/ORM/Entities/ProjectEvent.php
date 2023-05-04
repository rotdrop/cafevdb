<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use DateTimeInterface;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Common\Uuid;

/**
 * ProjectEvents
 *
 * @ORM\Table(
 *   name="ProjectEvents",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"project_id", "calendar_uri", "event_uid", "recurrence_id"}),
 *     @ORM\UniqueConstraint(columns={"project_id", "calendar_id", "event_uid", "recurrence_id"}),
 *     @ORM\UniqueConstraint(columns={"project_id", "calendar_uri", "event_uri", "recurrence_id"}),
 *     @ORM\UniqueConstraint(columns={"project_id", "calendar_id", "event_uri", "recurrence_id"}),
 *   }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectEventsRepository")
 * @Gedmo\SoftDeleteable(fieldName="deleted")
 */
class ProjectEvent implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  /**
   * @var int
   *
   * While it would be tempting to just use the calendar URI and event UID and
   * perhaps the recurrence id as composite key it turns out that this is
   * complicated for repeating events: calendar apps may choose to split
   * existing series into two when changing "this event and future" events and
   * at that point one needs to match the recurrence ids in order to "find"
   * the correct new old event. The event will then be part of a new event
   * series with a new UID.
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var Project
   *
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="calendarEvents", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(nullable=false)
   */
  private $project;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $calendarId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=764, nullable=false, options={"collation"="ascii_bin"})
   */
  private $calendarUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=255, nullable=false, options={"collation"="ascii_general_ci"})
   */
  private $eventUid;

  /**
   * @var \OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface
   *
   * A unique identifier which links RELATED-TO events. This occurs if
   * recurring event series are split but applying changes to "this and
   * future" events.
   *
   * @ORM\Column(type="uuid_binary", nullable=true)
   */
  private $seriesUid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=764, nullable=false, options={"collation"="ascii_bin"})
   */
  private $eventUri;

  /**
   * @var int
   *
   * The recurrence-id of the event instance as Unix timestamp. Non-recurring
   * events have an id of 0.
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"=0})
   */
  private $recurrenceId;

  /**
   * @var int
   * The SEQUENCE number tied to the event. We always use the highest
   * sequence, but technically this is part of the id.
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"=0})
   */
  private $sequence;

  /**
   * @var null|Types\EnumVCalendarType
   *
   * @ORM\Column(type="EnumVCalendarType", nullable=false)
   */
  private $type;

  /**
   * @var ProjectParticipantField
   * Linked ProjectParticipantField entities which can be used to record
   * asence from rehearsals or other calendar events. As calendar events are
   * possibly repeating or we need a list of linked fields in order to record
   * the participation for each event instance.
   *
   * @ORM\OneToOne(targetEntity="ProjectParticipantField", inversedBy="projectEvent", cascade={"remove"}, orphanRemoval=true)
   * @Gedmo\SoftDeleteableCascade(undelete=true)
   */
  private $absenceField;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return IdEvent
   */
  public function setId(int $id):ProjectEvent
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId():int
  {
    return $this->id;
  }

  /**
   * Set projectId.
   *
   * @param null|Project $project
   *
   * @return ProjectEvent
   */
  public function setProject($project):ProjectEvent
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project|null
   */
  public function getProject():?Project
  {
    return $this->project;
  }

  /**
   * Set calendarId.
   *
   * @param null|int $calendarId
   *
   * @return ProjectEvent
   */
  public function setCalendarId($calendarId):ProjectEvent
  {
    $this->calendarId = $calendarId;

    return $this;
  }

  /**
   * Get calendarId.
   *
   * @return int
   */
  public function getCalendarId()
  {
    return $this->calendarId;
  }

  /**
   * Set calendarUri.
   *
   * @param string $calendarUri
   *
   * @return ProjectEvent
   */
  public function setCalendarUri(string $calendarUri):ProjectEvent
  {
    $this->calendarUri = $calendarUri;

    return $this;
  }

  /**
   * Get calendarUri.
   *
   * @return string
   */
  public function getCalendarUri():string
  {
    return $this->calendarUri;
  }

  /**
   * Set eventUri.
   *
   * @param string|null $eventUri
   *
   * @return ProjectEvent
   */
  public function setEventUri($eventUri)
  {
    $this->eventUri = $eventUri;

    return $this;
  }

  /**
   * Get eventUri.
   *
   * @return string|null
   */
  public function getEventUri()
  {
    return $this->eventUri;
  }

  /**
   * Set eventUid.
   *
   * @param string|null $eventUid
   *
   * @return ProjectEvent
   */
  public function setEventUid($eventUid):ProjectEvent
  {
    $this->eventUid = $eventUid;

    return $this;
  }

  /**
   * Get eventUid.
   *
   * @return string|null
   */
  public function getEventUid():?string
  {
    return $this->eventUid;
  }

  /**
   * Set seriesUid.
   *
   * @param string|null $seriesUid
   *
   * @return ProjectEvent
   */
  public function setSeriesUid(mixed $seriesUid):ProjectEvent
  {
    $seriesUid = Uuid::asUuid($seriesUid);

    $this->seriesUid = $seriesUid;

    return $this;
  }

  /**
   * Get seriesUid.
   *
   * @return UuidInterface
   */
  public function getSeriesUid():?UuidInterface
  {
    return $this->seriesUid;
  }

  /**
   * Set sequence.
   *
   * @param int $sequence
   *
   * @return ProjectEvent
   */
  public function setSequence(int $sequence):ProjectEvent
  {
    $this->sequence = $sequence;

    return $this;
  }

  /**
   * Get sequence.
   *
   * @return int
   */
  public function getSequence():int
  {
    return $this->sequence;
  }

  /**
   * Set recurrenceId.
   *
   * @param mixed $recurrenceId
   *
   * @return ProjectEvent
   */
  public function setRecurrenceId(mixed $recurrenceId):ProjectEvent
  {
    if ($recurrenceId instanceof DateTimeInterface) {
      $recurrenceId = $recurrenceId->getTimestamp();
    }
    $this->recurrenceId = $recurrenceId;

    return $this;
  }

  /**
   * Get recurrenceId.
   *
   * @return int
   */
  public function getRecurrenceId():int
  {
    return $this->recurrenceId;
  }

  /**
   * Set type.
   *
   * @param Types\EnumVCalendarType|null|string $type
   *
   * @return ProjectEvent
   */
  public function setType(mixed $type = null):ProjectEvent
  {
    if ($type === null) {
      $this->type = $type;
    } else {
      $this->type = new Types\EnumVCalendarType($type);
    }

    return $this;
  }

  /**
   * Get type.
   *
   * @return Types\EnumVCalendarType|null
   */
  public function getType(): ?Types\EnumVCalendarType
  {
    return $this->type;
  }

  /**
   * Set absenceField.
   *
   * @param null|ProjectParticipantField $absenceField
   *
   * @return ProjectEvent
   */
  public function setAbsenceField(?ProjectParticipantField $absenceField):ProjectEvent
  {
    $this->absenceField = $absenceField;

    return $this;
  }

  /**
   * Get absenceField.
   *
   * @return ProjectParticipantField
   */
  public function getAbsenceField():?ProjectParticipantField
  {
    return $this->absenceField;
  }
}

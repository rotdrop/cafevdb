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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * ProjectEvents
 *
 * @ORM\Table(name="ProjectEvents", uniqueConstraints={@ORM\UniqueConstraint(columns={"project_id", "event_uid"})})
 * @ORM\Entity
 */
class ProjectEvent implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="calendarEvents", fetch="EXTRA_LAZY")
   * @ORM\Id
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
   * @ORM\Id
   */
  private $calendarUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=764, nullable=false, options={"collation"="ascii_bin"})
   * @ORM\Id
   */
  private $eventUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=255, nullable=false, options={"collation"="ascii_general_ci"})
   */
  private $eventUid;

  /**
   * @var null|Types\EnumVCalendarType
   *
   * @ORM\Column(type="EnumVCalendarType", nullable=true)
   */
  private $type;

  /**
   * @var Collection
   *
   * Linked ProjectParticipantField entities which can be used to record
   * asence from rehearsals or other calendar events. As calendar events are
   * possibly repeating or we need a list of linked fields in order to record
   * the participation for each event instance.
   *
   * @ORM\ManyToMany(targetEntity="ProjectParticipantField", fetch="EXTRA_LAZY")
   * @ORM\JoinTable(
   *   joinColumns={
   *     @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *     @ORM\JoinColumn(name="calendar_uri", referencedColumnName="calendar_uri"),
   *     @ORM\JoinColumn(name="event_uri", referencedColumnName="event_uri")
   *   },
   *   inverseJoinColumns={
   *     @ORM\JoinColumn(unique=true)
   *   }
   * )
   */
  private $absenceFields;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->absenceFields = new ArrayCollection;
  }
  // phpcs:enable

  /**
   * Set projectId.
   *
   * @param null|Project $project
   *
   * @return ProjectEvents
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
   * @return ProjectEvents
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
   * @return ProjectEvents
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
   * @return ProjectEvents
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
   * Set type.
   *
   * @param Types\EnumVCalendarType|null|string $type
   *
   * @return ProjectEvents
   */
  public function setType($type = null):ProjectEvent
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
   * Set absenceFields.
   *
   * @param Collection $absenceFields
   *
   * @return ProjectEvents
   */
  public function setAbsenceFields(Collection $absenceFields):ProjectEvent
  {
    $this->absenceFields = $absenceFields;

    return $this;
  }

  /**
   * Get absenceFields.
   *
   * @return Collection
   */
  public function getAbsenceFields():Collection
  {
    return $this->absenceFields;
  }
}

<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

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
   * @var string
   *
   * @ORM\Column(type="string", length=764, nullable=false)
   * @ORM\Id
   */
  private $eventUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=764, nullable=false)
   */
  private $eventUid;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $calendarId;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=764, nullable=false)
   */
  private $calendarUri;

  /**
   * @var null|Types\EnumVCalendarType
   *
   * @ORM\Column(type="EnumVCalendarType", nullable=true)
   */
  private $type;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set projectId.
   *
   * @param int|null $projectId
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
   * @param int $calendarId
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
}

<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectEvents
 *
 * @ORM\Table(name="ProjectEvents")
 * @ORM\Entity
 */
class ProjectEvent
{
    /**
     * @var int|null
     *
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     */
    private $projectid;

    /**
     * @var string|null
     *
     * @ORM\Column(type="string", length=764, nullable=false)
     * @ORM\Id
     */
    private $eventuri;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $calendarid;

    /**
     * @var enumvcalendartype|null
     *
     * @ORM\Column(type="enumvcalendartype", nullable=true)
     */
    private $type;

    /**
     * Set projectid.
     *
     * @param int|null $projectid
     *
     * @return ProjectEvents
     */
    public function setProjectId($projectid = null)
    {
        $this->projectid = $projectid;

        return $this;
    }

    /**
     * Get projectid.
     *
     * @return int|null
     */
    public function getProjectId()
    {
        return $this->projectid;
    }

    /**
     * Set calendarid.
     *
     * @param int $calendarid
     *
     * @return ProjectEvents
     */
    public function setCalendarId($calendarid)
    {
        $this->calendarid = $calendarid;

        return $this;
    }

    /**
     * Get calendarid.
     *
     * @return int
     */
    public function getCalendarId()
    {
        return $this->calendarid;
    }

    /**
     * Set eventuri.
     *
     * @param string|null $eventuri
     *
     * @return ProjectEvents
     */
    public function setEventURI($eventuri = null)
    {
        $this->eventuri = $eventuri;

        return $this;
    }

    /**
     * Get eventuri.
     *
     * @return string|null
     */
    public function getEventURI()
    {
        return $this->eventuri;
    }

    /**
     * Set type.
     *
     * @param enumvcalendartype|null $type
     *
     * @return ProjectEvents
     */
    public function setType($type = null)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type.
     *
     * @return enumvcalendartype|null
     */
    public function getType()
    {
        return $this->type;
    }
}

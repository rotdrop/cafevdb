<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectEvents
 *
 * @ORM\Table(name="ProjectEvents", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectId_EventId", columns={"Id", "ProjectId", "EventId"}), @ORM\UniqueConstraint(name="ProjectId_EventURI", columns={"Id", "ProjectId", "EventURI"}), @ORM\UniqueConstraint(name="EventId_EventURI", columns={"Id", "EventId", "EventURI"})})
 * @ORM\Entity
 */
class ProjectEvents
{
    /**
     * @var int
     *
     * @ORM\Column(name="Id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int|null
     *
     * @ORM\Column(name="ProjectId", type="integer", nullable=true)
     */
    private $projectid;

    /**
     * @var int
     *
     * @ORM\Column(name="CalendarId", type="integer", nullable=false)
     */
    private $calendarid;

    /**
     * @var int
     *
     * @ORM\Column(name="EventId", type="integer", nullable=false)
     */
    private $eventid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="EventURI", type="string", length=1024, nullable=true)
     */
    private $eventuri;

    /**
     * @var enumvcalendartype|null
     *
     * @ORM\Column(name="Type", type="enumvcalendartype", nullable=true)
     */
    private $type;



    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set projectid.
     *
     * @param int|null $projectid
     *
     * @return ProjectEvents
     */
    public function setProjectid($projectid = null)
    {
        $this->projectid = $projectid;

        return $this;
    }

    /**
     * Get projectid.
     *
     * @return int|null
     */
    public function getProjectid()
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
    public function setCalendarid($calendarid)
    {
        $this->calendarid = $calendarid;

        return $this;
    }

    /**
     * Get calendarid.
     *
     * @return int
     */
    public function getCalendarid()
    {
        return $this->calendarid;
    }

    /**
     * Set eventid.
     *
     * @param int $eventid
     *
     * @return ProjectEvents
     */
    public function setEventid($eventid)
    {
        $this->eventid = $eventid;

        return $this;
    }

    /**
     * Get eventid.
     *
     * @return int
     */
    public function getEventid()
    {
        return $this->eventid;
    }

    /**
     * Set eventuri.
     *
     * @param string|null $eventuri
     *
     * @return ProjectEvents
     */
    public function setEventuri($eventuri = null)
    {
        $this->eventuri = $eventuri;

        return $this;
    }

    /**
     * Get eventuri.
     *
     * @return string|null
     */
    public function getEventuri()
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

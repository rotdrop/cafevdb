<?php
namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectEvents
 *
 * @ORM\Table(name="ProjectEvents")
 * @ORM\Entity
 */
class ProjectEvents
{
    /**
     * @var int|null
     *
     * @ORM\Column(name="ProjectId", type="integer", nullable=false)
     * @ORM\Id
     */
    private $projectid;

    /**
     * @var string|null
     *
     * @ORM\Column(name="EventURI", type="string", length=764, nullable=false)
     * @ORM\Id
     */
    private $eventuri;

    /**
     * @var int
     *
     * @ORM\Column(name="CalendarId", type="integer", nullable=false)
     */
    private $calendarid;

    /**
     * @var enumvcalendartype|null
     *
     * @ORM\Column(name="Type", type="enumvcalendartype", nullable=true)
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

<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectInstruments
 *
 * @ORM\Table(name="ProjectInstruments", uniqueConstraints={@ORM\UniqueConstraint(columns={"project_id", "musician_id", "instrument_id"}), @ORM\UniqueConstraint(columns={"instrumentation_id", "instrument_id"})})
 * @ORM\Entity
 */
class ProjectInstrument
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table Projects"})
     */
    private $projectId;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table Musicians"})
     */
    private $musicianId;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table ProjectParticipants"})
     */
    private $instrumentationId;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"comment"="Index into table Instruments"})
     */
    private $instrumentId;

    /**
     * @var int|null
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    private $voice;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
     */
    private $sectionLeader = '0';

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
     * Set projectId.
     *
     * @param int $projectId
     *
     * @return ProjectInstruments
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;

        return $this;
    }

    /**
     * Get projectId.
     *
     * @return int
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * Set musicianId.
     *
     * @param int $musicianId
     *
     * @return ProjectInstruments
     */
    public function setMusicianId($musicianId)
    {
        $this->musicianId = $musicianId;

        return $this;
    }

    /**
     * Get musicianId.
     *
     * @return int
     */
    public function getMusicianId()
    {
        return $this->musicianId;
    }

    /**
     * Set instrumentationId.
     *
     * @param int $instrumentationId
     *
     * @return ProjectInstruments
     */
    public function setInstrumentationId($instrumentationId)
    {
        $this->instrumentationId = $instrumentationId;

        return $this;
    }

    /**
     * Get instrumentationId.
     *
     * @return int
     */
    public function getInstrumentationId()
    {
        return $this->instrumentationId;
    }

    /**
     * Set instrumentId.
     *
     * @param int $instrumentId
     *
     * @return ProjectInstruments
     */
    public function setInstrumentId($instrumentId)
    {
        $this->instrumentId = $instrumentId;

        return $this;
    }

    /**
     * Get instrumentId.
     *
     * @return int
     */
    public function getInstrumentId()
    {
        return $this->instrumentId;
    }

    /**
     * Set voice.
     *
     * @param int|null $voice
     *
     * @return ProjectInstruments
     */
    public function setVoice($voice = null)
    {
        $this->voice = $voice;

        return $this;
    }

    /**
     * Get voice.
     *
     * @return int|null
     */
    public function getVoice()
    {
        return $this->voice;
    }

    /**
     * Set sectionLeader.
     *
     * @param bool $sectionLeader
     *
     * @return ProjectInstruments
     */
    public function setSectionLeader($sectionLeader)
    {
        $this->sectionLeader = $sectionLeader;

        return $this;
    }

    /**
     * Get sectionLeader.
     *
     * @return bool
     */
    public function getSectionLeader()
    {
        return $this->sectionLeader;
    }
}

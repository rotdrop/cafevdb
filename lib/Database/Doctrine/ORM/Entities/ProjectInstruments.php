<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectInstruments
 *
 * @ORM\Table(name="ProjectInstruments", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectId", columns={"ProjectId", "MusicianId", "InstrumentId"}), @ORM\UniqueConstraint(name="InstrumentationId", columns={"InstrumentationId", "InstrumentId"})})
 * @ORM\Entity
 */
class ProjectInstruments
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
     * @var int
     *
     * @ORM\Column(name="ProjectId", type="integer", nullable=false, options={"comment"="Index into table Projekte"})
     */
    private $projectid;

    /**
     * @var int
     *
     * @ORM\Column(name="MusicianId", type="integer", nullable=false, options={"comment"="Index into table Musiker"})
     */
    private $musicianid;

    /**
     * @var int
     *
     * @ORM\Column(name="InstrumentationId", type="integer", nullable=false, options={"comment"="Index into table Besetzungen"})
     */
    private $instrumentationid;

    /**
     * @var int
     *
     * @ORM\Column(name="InstrumentId", type="integer", nullable=false, options={"comment"="Index into table Instrumente"})
     */
    private $instrumentid;

    /**
     * @var int|null
     *
     * @ORM\Column(name="Voice", type="integer", nullable=true)
     */
    private $voice;

    /**
     * @var bool
     *
     * @ORM\Column(name="SectionLeader", type="boolean", nullable=false, options={"default"="0"})
     */
    private $sectionleader = '0';



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
     * @param int $projectid
     *
     * @return ProjectInstruments
     */
    public function setProjectid($projectid)
    {
        $this->projectid = $projectid;

        return $this;
    }

    /**
     * Get projectid.
     *
     * @return int
     */
    public function getProjectid()
    {
        return $this->projectid;
    }

    /**
     * Set musicianid.
     *
     * @param int $musicianid
     *
     * @return ProjectInstruments
     */
    public function setMusicianid($musicianid)
    {
        $this->musicianid = $musicianid;

        return $this;
    }

    /**
     * Get musicianid.
     *
     * @return int
     */
    public function getMusicianid()
    {
        return $this->musicianid;
    }

    /**
     * Set instrumentationid.
     *
     * @param int $instrumentationid
     *
     * @return ProjectInstruments
     */
    public function setInstrumentationid($instrumentationid)
    {
        $this->instrumentationid = $instrumentationid;

        return $this;
    }

    /**
     * Get instrumentationid.
     *
     * @return int
     */
    public function getInstrumentationid()
    {
        return $this->instrumentationid;
    }

    /**
     * Set instrumentid.
     *
     * @param int $instrumentid
     *
     * @return ProjectInstruments
     */
    public function setInstrumentid($instrumentid)
    {
        $this->instrumentid = $instrumentid;

        return $this;
    }

    /**
     * Get instrumentid.
     *
     * @return int
     */
    public function getInstrumentid()
    {
        return $this->instrumentid;
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
     * Set sectionleader.
     *
     * @param bool $sectionleader
     *
     * @return ProjectInstruments
     */
    public function setSectionleader($sectionleader)
    {
        $this->sectionleader = $sectionleader;

        return $this;
    }

    /**
     * Get sectionleader.
     *
     * @return bool
     */
    public function getSectionleader()
    {
        return $this->sectionleader;
    }
}

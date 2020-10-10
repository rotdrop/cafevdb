<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * MusicianInstruments
 *
 * @ORM\Table(name="MusicianInstruments", uniqueConstraints={@ORM\UniqueConstraint(name="MusicianId", columns={"MusicianId", "InstrumentId"})})
 * @ORM\Entity
 */
class MusicianInstruments
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
     * @ORM\Column(name="MusicianId", type="integer", nullable=false, options={"comment"="Index into table Musiker"})
     */
    private $musicianid;

    /**
     * @var int
     *
     * @ORM\Column(name="InstrumentId", type="integer", nullable=false, options={"comment"="Index into table Instrumente"})
     */
    private $instrumentid;



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
     * Set musicianid.
     *
     * @param int $musicianid
     *
     * @return MusicianInstruments
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
     * Set instrumentid.
     *
     * @param int $instrumentid
     *
     * @return MusicianInstruments
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
}

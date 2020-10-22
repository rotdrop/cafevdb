<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * Instrumente
 *
 * @ORM\Table(name="Instruments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository")
 */
class Instrument
{
    use CAFEVDB\Traits\ArrayTrait;
    use CAFEVDB\Traits\FactoryTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="Id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="Instrument", type="string", length=64, nullable=false, unique=true)
     */
    private $instrument;

    /**
     * @var int
     *
     * @ORM\Column(name="Sortierung", type="smallint", nullable=false, options={"comment"="Orchestersortierung"})
     */
    private $sortierung;

    /**
     * @var bool
     *
     * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
     */
    private $disabled = '0';

    /**
     * @ORM\ManyToMany(targetEntity="Musician", mappedBy="instruments")
     */
    private $musicians;

    /**
     * @ORM\ManyToMany(targetEntity="InstrumentFamily", inversedBy="instruments")
     * @ORM\JoinTable(
     *   name="instrument_family",
     *   joinColumns={@ORM\JoinColumn(name="instrument_id", referencedColumnName="Id", onDelete="CASCADE")},
     *   inverseJoinColumns={@ORM\JoinColumn(name="family_id", referencedColumnName="id", onDelete="CASCADE")}
     * )
     */
    private $families;

    public function __construct() {
        $this->arrayCTOR();
        $this->musicians = new ArrayCollection();
        $this->families = new ArrayCollection();
    }

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
     * Set instrument.
     *
     * @param string $instrument
     *
     * @return Instrumente
     */
    public function setInstrument($instrument)
    {
        $this->instrument = $instrument;

        return $this;
    }

    /**
     * Get instrument.
     *
     * @return string
     */
    public function getInstrument()
    {
        return $this->instrument;
    }

    /**
     * Set familie.
     *
     * @param array $familie
     *
     * @return Instrumente
     */
    public function setFamilie($familie)
    {
        $this->familie = $familie;

        return $this;
    }

    /**
     * Get familie.
     *
     * @return array
     */
    public function getFamilie()
    {
        return $this->familie;
    }

    /**
     * Set sortierung.
     *
     * @param int $sortierung
     *
     * @return Instrumente
     */
    public function setSortierung($sortierung)
    {
        $this->sortierung = $sortierung;

        return $this;
    }

    /**
     * Get sortierung.
     *
     * @return int
     */
    public function getSortierung()
    {
        return $this->sortierung;
    }

    /**
     * Set disabled.
     *
     * @param bool $disabled
     *
     * @return Instrumente
     */
    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Get disabled.
     *
     * @return bool
     */
    public function getDisabled()
    {
        return $this->disabled;
    }
}

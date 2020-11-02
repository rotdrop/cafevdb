<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * Instrumente
 *
 * @ORM\Table(name="InstrumentFamilies")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentFamiliesRepository")
 */
class InstrumentFamily implements \ArrayAccess
{
    use CAFEVDB\Traits\ArrayTrait;
    use CAFEVDB\Traits\FactoryTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="family", type="string", length=64, nullable=false, unique=true)
     */
    private $family;

    /**
     * @var bool
     *
     * @ORM\Column(name="disabled", type="boolean", nullable=false, options={"default"="0"})
     */
    private $disabled = false;

    /**
     * @ORM\ManyToMany(targetEntity="Instrument", mappedBy="families", orphanRemoval=true, fetch="EXTRA_LAZY")
     */
    private $instruments;

    public function __construct() {
        $this->arrayCTOR();
        $this->instruments = new ArrayCollection();
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
     * Set family.
     *
     * @param string $family
     *
     * @return Familye
     */
    public function setFamily($family)
    {
        $this->family = $family;

        return $this;
    }

    /**
     * Get family.
     *
     * @return string
     */
    public function getFamily()
    {
        return $this->family;
    }

    /**
     * Set family.
     *
     * @param bool $disabled
     *
     * @return InstrumentFamily
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
    public function getDisabled():bool
    {
        return $this->disabled;
    }

    public function getUsage()
    {
        return $this->instruments->count();
    }

}

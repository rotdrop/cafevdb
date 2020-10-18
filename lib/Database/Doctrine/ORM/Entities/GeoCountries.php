<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * GeoCountries
 *
 * @ORM\Table(name="GeoCountries")
 * @ORM\Entity
 * @ORM\Entity @ORM\EntityListeners({"ArrayConstructor"})
 */
class GeoCountries
    extends ArrayConstructor
    implements \ArrayAccess
{
    use FactoryTrait;

    /**
     * @var string
     *
     * @ORM\Column(name="ISO", type="string", length=2, nullable=false)
     * @ORM\Id
     */
    private $iso;

    /**
     * @var string
     * @ORM\Id
     *
     * @ORM\Column(name="Target", type="string", length=2, nullable=false)
     */
    private $target;

    /**
     * @var string
     *
     * @ORM\Column(name="Data", type="string", length=1024, nullable=false)
     */
    private $data;

    public function __construct() {
        $this->arrayCTOR();
    }

    /**
     * Set iso.
     *
     * @param string $iso
     *
     * @return GeoCountries
     */
    public function setIso($iso)
    {
        $this->iso = $iso;

        return $this;
    }
    /**
     * Get iso.
     *
     * @return string
     */
    public function getIso()
    {
        return $this->iso;
    }

    /**
     * Set continent.
     *
     * @param string $target
     *
     * @return GeoCountries
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Get target.
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Set data.
     *
     * @param string $data
     *
     * @return GeoCountries
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }
}

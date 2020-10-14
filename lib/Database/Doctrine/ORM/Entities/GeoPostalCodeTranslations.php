<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * GeoPostalCodes
 *
 * @ORM\Table(name="GeoPostalCodeTranslations")
 * @ORM\Entity
 */
class GeoPostalCodeTranslations implements \ArrayAccess
{
    use ArrayTrait;
    use FactoryTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="PostalCodeId", type="integer", nullable=false)
     * @ORM\Id
     */
    private $postalcodeid;

    /**
     * @var string
     *
     * @ORM\Column(name="Target", type="string", length=2, nullable=false)
     * @ORM\Id
     */
    private $target;

    /**
     * @var string
     *
     * @ORM\Column(name="Translation", type="string", length=1024, nullable=false)
     */
    private $translation;

    /**
     * @ORM\ManyToOne(targetEntity="GeoPostalCodes", inversedBy="translations")
     * @ORM\JoinColumn(name="PostalCodeId", referencedColumnName="Id")
     */
    private $postalcode;

    public function __construct() {
        $this->arrayCTOR();
    }

    /**
     * Set postalcodeid.
     *
     * @param int $postalCodeId
     *
     * @return GeoPostalCodeTranslations
     */
    public function setPostalcodeid($postalcodeid)
    {
        $this->postalcodeid = $postalcodeid;

        return $this;
    }

    /**
     * Get postalcodeid.
     *
     * @return int
     */
    public function getPostalcodeid()
    {
        return $this->postalcodeid;
    }

    /**
     * Set target.
     *
     * @param string $target
     *
     * @return GeoPostalCodeTranslations
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
     * Set translation.
     *
     * @param string $translation
     *
     * @return GeoPostalCodeTranslations
     */
    public function setTranslation($translation)
    {
        $this->translation = $translation;

        return $this;
    }

    /**
     * Get translation.
     *
     * @return string
     */
    public function getTranslation()
    {
        return $this->translation;
    }

    /**
     * Get linked GeoPostalCodes entity.
     *
     * @return string
     */
    public function getPostalcode()
    {
        return $this->postalcode;
    }
}

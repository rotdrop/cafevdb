<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * GeoPostalCodes
 *
 * @ORM\Table(name="GeoPostalCodeTranslations", uniqueConstraints={@ORM\UniqueConstraint(name="PostalCodeId_Target", columns={"PostalCodeId", "Target"})})
 * @ORM\Entity
 */
class GeoPostalCodeTranslations implements \ArrayAccess
{
    use ArrayTrait;
    use FactoryTrait;

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
     * @ORM\Column(name="PostalCodeId", type="integer", nullable=false)
     */
    private $postalCodeId;

    /**
     * @var string
     *
     * @ORM\Column(name="Target", type="string", length=2, nullable=false)
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
    private $geoPostalCode;

    public function __construct() {
        $this->arrayCTOR();
    }

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return GeoPostalCodeTranslations
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * Set postalCodeId.
     *
     * @param int $postalCodeId
     *
     * @return GeoPostalCodeTranslations
     */
    public function setPostalCodeId($postalCodeId)
    {
        $this->postalCodeId = $postalCodeId;

        return $this;
    }

    /**
     * Get postalCodeId.
     *
     * @return int
     */
    public function getPostalCodeId()
    {
        return $this->postalCodeId;
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
    public function getGeoPostalcode()
    {
        return $this->geoPostalCode;
    }

    /**
     * Set geoPostalCode
     *
     * @param GeoPostalCodes postalCode
     *
     * @return GeoPostalCodeTranslations
     */
    public function setGeoPostalCode($geoPostalCode)
    {
        $this->geoPostalCode = $geoPostalCode;

        return $this;
    }
}

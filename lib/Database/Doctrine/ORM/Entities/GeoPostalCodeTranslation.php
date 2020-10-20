<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * GeoPostalCodeTranslation
 *
 * @ORM\Table(name="GeoPostalCodeTranslations", uniqueConstraints={@ORM\UniqueConstraint(name="PostalCodeId_Target", columns={"PostalCodeId", "Target"})})
 * @ORM\Entity
 */
class GeoPostalCodeTranslation implements \ArrayAccess
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
     * @ORM\ManyToOne(targetEntity="GeoPostalCode", inversedBy="translations")
     * @ORM\JoinColumn(name="PostalCodeId", referencedColumnName="Id", onDelete="CASCADE")
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
     * @return GeoPostalCodeTranslation
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
     * @return GeoPostalCodeTranslation
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
     * @return GeoPostalCodeTranslation
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
     * @return GeoPostalCodeTranslation
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
     * Get linked GeoPostalCode entity.
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
     * @param GeoPostalCode postalCode
     *
     * @return GeoPostalCodeTranslation
     */
    public function setGeoPostalCode($geoPostalCode)
    {
        $this->geoPostalCode = $geoPostalCode;

        return $this;
    }
}

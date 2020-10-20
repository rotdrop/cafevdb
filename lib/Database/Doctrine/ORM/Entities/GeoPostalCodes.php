<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * GeoPostalCodes
 *
 * @ORM\Table(name="GeoPostalCodes",
 *    uniqueConstraints={
 *      @ORM\UniqueConstraint(name="Country_PostalCode_Name", columns={"Country", "PostalCode", "Name"})
 *    })
 * @ORM\Entity
 * @ORM\Entity @ORM\EntityListeners({"ArrayConstructor"})
 */
class GeoPostalCodes
    extends ArrayConstructor
    implements \ArrayAccess
{
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
     * @var string
     *
     * @ORM\Column(name="Country", type="string", length=2, nullable=false)
     */
    private $country;

    /**
     * @var string
     *
     * @ORM\Column(name="PostalCode", type="string", length=32, nullable=false)
     */
    private $postalCode;

    /**
     * @var string
     *
     * @ORM\Column(name="Name", type="string", length=650, nullable=false)
     */
    private $name;

    /**
     * @var double
     *
     * @ORM\Column(name="Latitude", type="float", nullable=false)
     */
    private $latitude;

    /**
     * @var double
     *
     * @ORM\Column(name="Longitude", type="float", nullable=false)
     */
    private $longitude;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="Updated", type="datetime", nullable=false)
     */
    private $updated;

    /**
     * @ORM\OneToMany(targetEntity="GeoPostalCodeTranslations", mappedBy="geoPostalCode")
     */
    private $translations;

    public function __construct() {
        $this->arrayCTOR();
        $this->translations = new ArrayCollection();
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
     * Set country.
     *
     * @param string $country
     *
     * @return GeoPostalCodes
     */
    public function setCountry($country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Set postalCode.
     *
     * @param string $postalCode
     *
     * @return GeoPostalCodes
     */
    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * Get postalCode.
     *
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return GeoPostalCodes
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set latitude.
     *
     * @param int $latitude
     *
     * @return GeoPostalCodes
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude.
     *
     * @return int
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude.
     *
     * @param double $longitude
     *
     * @return GeoPostalCodes
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude.
     *
     * @return double
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set updated.
     *
     * @param \DateTime $updated
     *
     * @return GeoPostalCodes
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get updated.
     *
     * @return \DateTime
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Get linked GeoPostalCodeTranslations entity.
     *
     * @return ArrayCollection[]
     */
    public function getTranslations()
    {
        return $this->translations;
    }
}

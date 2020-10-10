<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * GeoPostalCodes
 *
 * @ORM\Table(name="GeoPostalCodes", uniqueConstraints={@ORM\UniqueConstraint(name="Country", columns={"Country", "PostalCode", "Name"})})
 * @ORM\Entity
 */
class GeoPostalCodes
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
     * @var string
     *
     * @ORM\Column(name="Country", type="string", length=4, nullable=false)
     */
    private $country;

    /**
     * @var string
     *
     * @ORM\Column(name="PostalCode", type="string", length=32, nullable=false)
     */
    private $postalcode;

    /**
     * @var string
     *
     * @ORM\Column(name="Name", type="string", length=180, nullable=false)
     */
    private $name;

    /**
     * @var string|null
     *
     * @ORM\Column(name="en", type="string", length=180, nullable=true)
     */
    private $en;

    /**
     * @var string|null
     *
     * @ORM\Column(name="fr", type="string", length=180, nullable=true)
     */
    private $fr;

    /**
     * @var string|null
     *
     * @ORM\Column(name="de", type="string", length=180, nullable=true)
     */
    private $de;

    /**
     * @var int
     *
     * @ORM\Column(name="Latitude", type="integer", nullable=false)
     */
    private $latitude;

    /**
     * @var int
     *
     * @ORM\Column(name="Longitude", type="integer", nullable=false)
     */
    private $longitude;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="Updated", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $updated = 'CURRENT_TIMESTAMP';



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
     * Set postalcode.
     *
     * @param string $postalcode
     *
     * @return GeoPostalCodes
     */
    public function setPostalcode($postalcode)
    {
        $this->postalcode = $postalcode;

        return $this;
    }

    /**
     * Get postalcode.
     *
     * @return string
     */
    public function getPostalcode()
    {
        return $this->postalcode;
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
     * Set en.
     *
     * @param string|null $en
     *
     * @return GeoPostalCodes
     */
    public function setEn($en = null)
    {
        $this->en = $en;

        return $this;
    }

    /**
     * Get en.
     *
     * @return string|null
     */
    public function getEn()
    {
        return $this->en;
    }

    /**
     * Set fr.
     *
     * @param string|null $fr
     *
     * @return GeoPostalCodes
     */
    public function setFr($fr = null)
    {
        $this->fr = $fr;

        return $this;
    }

    /**
     * Get fr.
     *
     * @return string|null
     */
    public function getFr()
    {
        return $this->fr;
    }

    /**
     * Set de.
     *
     * @param string|null $de
     *
     * @return GeoPostalCodes
     */
    public function setDe($de = null)
    {
        $this->de = $de;

        return $this;
    }

    /**
     * Get de.
     *
     * @return string|null
     */
    public function getDe()
    {
        return $this->de;
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
     * @param int $longitude
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
     * @return int
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
}

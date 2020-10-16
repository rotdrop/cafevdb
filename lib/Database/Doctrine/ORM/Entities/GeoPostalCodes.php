<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;


  //   $query = "SELECT DISTINCT t1.PostalCode, t1.Country, t1.Updated FROM
  // `".self::POSTAL_CODES_TABLE."` as t1
  //   LEFT JOIN `Musiker` as t2
  //     ON t1.PostalCode = t2.Postleitzahl AND t1.Country = t2.Land
  // WHERE TIMESTAMPDIFF(MONTH,Updated,NOW()) > 1
  // ORDER BY `Updated` ASC
  // LIMIT ".$limit;
  //   $result = mySQL::query($query);
  //   while ($line = mySQL::fetch($result)) {
  //     $zipCodes[$line['PostalCode']] = $line['Country'];
  //   }

/**
 * GeoPostalCodes
 *
 * @ORM\Table(name="GeoPostalCodes",
 *    uniqueConstraints={
 *      @ORM\UniqueConstraint(name="Country_PostalCode_Name", columns={"Country", "PostalCode", "Name"})
 *    })
 * @ORM\Entity
 */
class GeoPostalCodes implements \ArrayAccess
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
     * @ORM\Column(name="Updated", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $updated = 'CURRENT_TIMESTAMP';

    /**
     * @ORM\OneToMany(targetEntity="GeoPostalCodeTranslations", mappedBy="PostalCodeId")
     */
    private $translations;

    //   $query = "SELECT DISTINCT t1.PostalCode, t1.Country, t1.Updated FROM
    // `".self::POSTAL_CODES_TABLE."` as t1
    //   LEFT JOIN `Musiker` as t2
    //     ON t1.PostalCode = t2.Postleitzahl AND t1.Country = t2.Land
    /**
     * @ORM\OneToMany(targetEntity="Musiker", mappedBy="PostalCode,Country")
     */
    private $musicians;

    public function __construct() {
        $this->arrayCTOR();
        $this->translations = new ArrayCollection();
        $this->musicians = new ArrayColletction();
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

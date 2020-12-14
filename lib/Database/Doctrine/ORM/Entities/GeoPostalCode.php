<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * GeoPostalCode
 *
 * @ORM\Table(name="GeoPostalCodes",
 *    uniqueConstraints={
 *      @ORM\UniqueConstraint(columns={"country", "postal_code", "name"})
 *    })
 * @ORM\Entity
 */
class GeoPostalCode implements \ArrayAccess
{
    use CAFEVDB\Traits\ArrayTrait;
    use CAFEVDB\Traits\FactoryTrait;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=2, nullable=false)
     */
    private $country;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=32, nullable=false)
     */
    private $postalCode;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=650, nullable=false)
     */
    private $name;

    /**
     * @var double
     *
     * @ORM\Column(type="float", nullable=false)
     */
    private $latitude;

    /**
     * @var double
     *
     * @ORM\Column(type="float", nullable=false)
     */
    private $longitude;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime", nullable=false)
     */
    private $updated;

    /**
     * @ORM\OneToMany(targetEntity="GeoPostalCodeTranslation", mappedBy="geoPostalCode", cascade={"all"})
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
     * @return GeoPostalCode
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
     * @return GeoPostalCode
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
     * @return GeoPostalCode
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
     * @return GeoPostalCode
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
     * @return GeoPostalCode
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
     * @return GeoPostalCode
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
     * Get linked GeoPostalCodeTranslation entities.
     *
     * @return ArrayCollection[]
     */
    public function getTranslations()
    {
        return $this->translations;
    }
}

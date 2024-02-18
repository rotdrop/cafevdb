<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * GeoPostalCode
 *
 * @ORM\Table(
 *    name="GeoPostalCodes",
 *    uniqueConstraints={
 *      @ORM\UniqueConstraint(columns={"country", "postal_code", "name"})
 *    })
 * @ORM\Entity
 * @Gedmo\Loggable(enabled=false)
 */
class GeoPostalCode implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;

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
   * @ORM\Column(type="string", length=2, nullable=false, options={"fixed" = true, "collation"="ascii_general_ci"})
   */
  private $country;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=3, nullable=true, options={"fixed" = true, "collation"="ascii_general_ci"})
   */
  private $stateProvince;

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
   * @ORM\OneToMany(targetEntity="GeoPostalCodeTranslation", mappedBy="geoPostalCode", cascade={"all"})
   */
  private $translations;

  /** {@inheritdoc} */
  public function __construct()
  {
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
   * @param null|string $country Country-code.
   *
   * @return GeoPostalCode
   */
  public function setCountry(?string $country):GeoPostalCode
  {
    $this->country = $country;

    return $this;
  }

  /**
   * Get country code.
   *
   * @return null|string
   */
  public function getCountry():?string
  {
    return $this->country;
  }

  /**
   * Set stateProvince.
   *
   * @param null|string $stateProvince StateProvince-code.
   *
   * @return GeoPostalCode
   */
  public function setStateProvince(?string $stateProvince):GeoPostalCode
  {
    $this->stateProvince = $stateProvince;

    return $this;
  }

  /**
   * Get stateProvince code.
   *
   * @return null|string
   */
  public function getStateProvince():?string
  {
    return $this->stateProvince;
  }

  /**
   * Set postalCode.
   *
   * @param null|string $postalCode
   *
   * @return GeoPostalCode
   */
  public function setPostalCode(?string $postalCode):GeoPostalCode
  {
    $this->postalCode = $postalCode;

    return $this;
  }

  /**
   * Get postalCode.
   *
   * @return string
   */
  public function getPostalCode():string
  {
    return $this->postalCode;
  }

  /**
   * Set name.
   *
   * @param null|string $name
   *
   * @return GeoPostalCode
   */
  public function setName(?string $name):GeoPostalCode
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return null|string
   */
  public function getName():?string
  {
    return $this->name;
  }

  /**
   * Set latitude.
   *
   * @param null|float $latitude Latitude.
   *
   * @return GeoPostalCode
   */
  public function setLatitude(?float $latitude):GeoPostalCode
  {
    $this->latitude = $latitude;

    return $this;
  }

  /**
   * Get latitude.
   *
   * @return null|float
   */
  public function getLatitude():?float
  {
    return $this->latitude;
  }

  /**
   * Set longitude.
   *
   * @param float $longitude Longitude.
   *
   * @return GeoPostalCode
   */
  public function setLongitude(?float $longitude):GeoPostalCode
  {
    $this->longitude = $longitude;

    return $this;
  }

  /**
   * Get longitude.
   *
   * @return null|float
   */
  public function getLongitude():?float
  {
    return $this->longitude;
  }

  /**
   * Set translations.
   *
   * @param Collection $translations
   *
   * @return GeoPostalCode
   */
  public function setTranslations(Collection $translations):GeoPostalCode
  {
    $this->translations = $translations;

    return $this;
  }

  /**
   * Get linked GeoPostalCodeTranslation entities.
   *
   * @return Collection
   */
  public function getTranslations():Collection
  {
    return $this->translations;
  }
}

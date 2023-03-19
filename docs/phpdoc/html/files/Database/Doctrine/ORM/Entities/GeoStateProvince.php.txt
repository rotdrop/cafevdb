<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

/**
 * GeoStatesProvinces, localized first-level administrative regions, states,
 * provinces etc.
 *
 * @ORM\Table(name="GeoStatesProvinces")
 * @ORM\Entity
 * @Gedmo\Loggable(enabled=false)
 */
class GeoStateProvince implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, nullable=false, options={"fixed":true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private $countryIso;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=3, nullable=false, options={"fixed":true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private $code;

  /**
   * @var string
   * @ORM\Id
   *
   * @ORM\Column(type="string", length=2, nullable=false, options={"fixed":true, "collation"="ascii_general_ci"})
   */
  private $target;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $l10nName;

  /**
   * @var GeoCountry
   *
   * @ORM\ManyToOne(targetEntity="GeoCountry", inversedBy="statesProvinces", fetch="EAGER")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="country_iso", referencedColumnName="iso"),
   *   @ORM\JoinColumn(name="target", referencedColumnName="target")
   * )
   */
  private $country;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
  }

  /**
   * Set code.
   *
   * @param string $code
   *
   * @return GeoCountry
   */
  public function setCode(string $code):GeoStateProvince
  {
    $this->code = $code;

    return $this;
  }
  /**
   * Get code.
   *
   * @return string
   */
  public function getCode():string
  {
    return $this->code;
  }

  /**
   * Set countryIso.
   *
   * @param string $countryIso
   *
   * @return GeoCountry
   */
  public function setCountryIso(string $countryIso):GeoStateProvince
  {
    $this->countryIso = $countryIso;

    return $this;
  }
  /**
   * Get countryIso.
   *
   * @return string
   */
  public function getCountryIso():string
  {
    return $this->countryIso;
  }

  /**
   * Set continent.
   *
   * @param string $target
   *
   * @return GeoCountries
   */
  public function setTarget(string $target):GeoStateProvince
  {
    $this->target = $target;

    return $this;
  }

  /**
   * Get target.
   *
   * @return string
   */
  public function getTarget():string
  {
    return $this->target;
  }

  /**
   * Set l10nName.
   *
   * @param string $l10nName
   *
   * @return GeoCountries
   */
  public function setL10nName(string $l10nName):GeoStateProvince
  {
    $this->l10nName = $l10nName;

    return $this;
  }

  /**
   * Get l10nName.
   *
   * @return string
   */
  public function getL10nName():string
  {
    return $this->l10nName;
  }

  /**
   * Set country.
   *
   * @param GeoCountry $country
   *
   * @return GeoCountries
   */
  public function setCountry(GeoCountry $country):GeoStateProvince
  {
    $this->country = $country;

    return $this;
  }

  /**
   * Get country.
   *
   * @return GeoCountry
   */
  public function getCountry():?GeoCountry
  {
    return $this->country;
  }

  /**
   * Return the ISO 3166-2 code for the state or province.
   *
   * @return string
   *
   * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
   */
  public function getIso3166_2():string
  {
    return $this->countryIso . '-' . $this->code;
  }
}

<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * GeoCountries
 *
 * @ORM\Table(name="GeoCountries")
 * @ORM\Entity
 * @Gedmo\Loggable(enabled=false)
 */
class GeoCountry implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, nullable=false, options={"fixed":true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private $iso;

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
   * @ORM\ManyToOne(targetEntity="GeoContinent", inversedBy="countries")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="continent_code", referencedColumnName="code"),
   *   @ORM\JoinColumn(name="target", referencedColumnName="target")
   * )
   */
  private $continent;

  public function __construct() {
    $this->arrayCTOR();
    $this->continents = new ArrayCollection;
  }

  /**
   * Set iso.
   *
   * @param string $iso
   *
   * @return GeoCountry
   */
  public function setIso(string $iso):GeoCountry
  {
    $this->iso = $iso;

    return $this;
  }
  /**
   * Get iso.
   *
   * @return string
   */
  public function getIso():string
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
  public function setTarget(string $target):GeoCountry
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
  public function setL10nName(string $l10nName):GeoCountry
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
   * Set continent.
   *
   * @param GeoContinent $continent
   *
   * @return GeoCountries
   */
  public function setContinent(GeoContinent $continent):GeoCountry
  {
    $this->continent = $continent;

    return $this;
  }

  /**
   * Get continent.
   *
   * @return GeoContinent
   */
  public function getContinent():GeoContinent
  {
    return $this->continent;
  }
}

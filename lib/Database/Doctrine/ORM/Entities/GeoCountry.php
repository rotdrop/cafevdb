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
  private $data;

  /**
   * @ORM\ManyToMany(targetEntity="GeoContinent", mappedBy="countries")
   */
  private $continents;

  public function __construct() {
    $this->arrayCTOR();
    $this->continents = new ArrayCollection;
  }

  /**
   * Set iso.
   *
   * @param string $iso
   *
   * @return GeoCountries
   */
  public function setIso($iso)
  {
    $this->iso = $iso;

    return $this;
  }
  /**
   * Get iso.
   *
   * @return string
   */
  public function getIso()
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
   * Set data.
   *
   * @param string $data
   *
   * @return GeoCountries
   */
  public function setData($data)
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return string
   */
  public function getData()
  {
    return $this->data;
  }
}

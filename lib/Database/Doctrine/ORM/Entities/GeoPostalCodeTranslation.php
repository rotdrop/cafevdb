<?php
/**
 * Orchestra member, musician and project management application.
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
 * GeoPostalCodeTranslation
 *
 * @ORM\Table(name="GeoPostalCodeTranslations")
 * @ORM\Entity
 * @Gedmo\Loggable(enabled=false)
 */
class GeoPostalCodeTranslation implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="GeoPostalCode", inversedBy="translations")
   * @ORM\Id
   */
  private $geoPostalCode;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, options={"fixed" = true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private $target;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $translation;

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set geoPostalCode.
   *
   * @param int $geoPostalCode
   *
   * @return GeoPostalCodeTranslation
   */
  public function setGeoPostalCode($geoPostalCode)
  {
    $this->geoPostalCode = $geoPostalCode;

    return $this;
  }

  /**
   * Get geoPostalCode.
   *
   * @return int
   */
  public function getGeoPostalCode()
  {
    return $this->geoPostalCode;
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
}

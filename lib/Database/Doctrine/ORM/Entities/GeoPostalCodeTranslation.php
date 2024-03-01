<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024 Claus-Justus Heine
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

/**
 * GeoPostalCodeTranslation
 *
 * @ORM\Table(name="GeoPostalCodeTranslations")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @Gedmo\Loggable(enabled=false)
 * @ORM\HasLifecycleCallbacks
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

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
  }

  /**
   * Set geoPostalCode.
   *
   * @param null|GeoPostalCode $geoPostalCode
   *
   * @return GeoPostalCodeTranslation
   */
  public function setGeoPostalCode(?GeoPostalCode $geoPostalCode):GeoPostalCodeTranslation
  {
    $this->geoPostalCode = $geoPostalCode;

    return $this;
  }

  /**
   * Get geoPostalCode.
   *
   * @return null|GeoPostalCode
   */
  public function getGeoPostalCode():?GeoPostalCode
  {
    return $this->geoPostalCode;
  }

  /**
   * Set target.
   *
   * @param null|string $target Language target.
   *
   * @return GeoPostalCodeTranslation
   */
  public function setTarget(?string $target):GeoPostalCodeTranslation
  {
    $this->target = $target;

    return $this;
  }

  /**
   * Get target.
   *
   * @return null|string
   */
  public function getTarget():?string
  {
    return $this->target;
  }

  /**
   * Set translation.
   *
   * @param null|string $translation
   *
   * @return GeoPostalCodeTranslation
   */
  public function setTranslation(?string $translation):GeoPostalCodeTranslation
  {
    $this->translation = $translation;

    return $this;
  }

  /**
   * Get translation.
   *
   * @return string
   */
  public function getTranslation():?string
  {
    return $this->translation;
  }
}

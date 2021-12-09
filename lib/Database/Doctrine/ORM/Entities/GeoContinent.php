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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * GeoContinents
 *
 * @ORM\Table(name="GeoContinents")
 * @ORM\Entity
 * @Gedmo\Loggable(enabled=false)
 */
class GeoContinent implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, nullable=false, options={"fixed":true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private $code;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, nullable=false, options={"fixed":true, "collation"="ascii_general_ci"})
   * @ORM\Id
   */
  private $target;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $l10nName;

  /**
   * @ORM\OneToMany(targetEntity="GeoCountry", mappedBy="continent", indexBy="iso", fetch="EXTRA_LAZY")
   * @ORM\OrderBy({"l10nName" = "ASC"})
   */
  private $countries;

  public function __construct() {
    $this->arrayCTOR();
    $this->countries = new ArrayCollection;
  }

  /**
   * Set code.
   *
   * @param string $code
   *
   * @return GeoContinent
   */
  public function setCode(string $code):GeoContinent
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
   * Set target.
   *
   * @param string $target
   *
   * @return GeoContinent
   */
  public function setTarget($target):GeoContinent
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
   * @param string $translatoin
   *
   * @return GeoContinents
   */
  public function setL10nName($l10nName):GeoContinent
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
   * Set countries.
   *
   * @param string $translatoin
   *
   * @return GeoContinents
   */
  public function setCountries(Collection $countries):GeoContinent
  {
    $this->countries = $countries;

    return $this;
  }

  /**
   * Get countries.
   *
   * @return Collection
   */
  public function getCountries():Collection
  {
    return $this->countries;
  }
}

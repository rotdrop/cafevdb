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

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * GeoContinents
 *
 * @ORM\Table(name="GeoContinents")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @ORM\HasLifecycleCallbacks
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

  /** {@inheritdoc} */
  public function __construct()
  {
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
   * @param null|string $target Two-letter language target code.
   *
   * @return GeoContinent
   */
  public function setTarget(?string $target):GeoContinent
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
   * @param null|string $l10nName Continent name in $target language.
   *
   * @return GeoContinents
   */
  public function setL10nName(?string $l10nName):GeoContinent
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
   * @param Collection $countries Country collection.
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

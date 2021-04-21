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

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Instrumente
 *
 * @ORM\Table(name="InstrumentFamilies")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentFamiliesRepository")
 * @Gedmo\TranslationEntity(class="TableFieldTranslation")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 */
class InstrumentFamily implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TranslatableTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private ?int $id = null;

  /**
   * @var string
   *
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=255, nullable=false, unique=true)
   */
  private string $family;

  /**
   * @ORM\ManyToMany(targetEntity="Instrument", mappedBy="families", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $instruments;

  public function __construct() {
    $this->arrayCTOR();
    $this->instruments = new ArrayCollection();
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
   * Set family.
   *
   * @param string $family
   *
   * @return InstrumentFamily
   */
  public function setFamily(string $family):InstrumentFamily
  {
    $this->family = $family;

    return $this;
  }

  /**
   * Get family.
   *
   * @return string
   */
  public function getFamily():string
  {
    return $this->family;
  }

  /**
   * Set instruments.
   *
   * @param bool $instruments
   *
   * @return InstrumentFamily
   */
  public function setInstruments($instruments):InstrumentFamily
  {
    $this->instruments = $instruments;

    return $this;
  }

  /**
   * Get instruments.
   *
   * @return Collection
   */
  public function getInstruments():Collection
  {
    return $this->instruments;
  }

  /**
   * Get the usage count, i.e. the number of instruments which belong
   * to this family.
   */
  public function usage():int
  {
    return $this->instruments->count();
  }

}

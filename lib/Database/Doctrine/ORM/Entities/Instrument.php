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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Instrumente
 *
 * @ORM\Table(name="Instruments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InstrumentsRepository")
 * @Gedmo\TranslationEntity(class="TableFieldTranslation")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 */
class Instrument implements \ArrayAccess
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
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private string $name;

  /**
   * @var int
   *
   * @ORM\Column(type="smallint", nullable=false, options={"comment"="Orchestral Ordering"})
   */
  private int $sortOrder;

  /**
   * @ORM\ManyToMany(targetEntity="InstrumentFamily", inversedBy="instruments", fetch="EXTRA_LAZY")
   * @ORM\JoinTable(
   *   joinColumns={@ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")},
   *   inverseJoinColumns={@ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")}
   * )
   */
  private $families;

  /**
   * @ORM\OneToMany(targetEntity="MusicianInstrument", mappedBy="instrument", fetch="EXTRA_LAZY")
   */
  private $musicianInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="instrument", fetch="EXTRA_LAZY")
   */
  private $projectInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrumentationNumber", mappedBy="instrument", fetch="EXTRA_LAZY")
   */
  private $projectInstrumentationNumbers;

  public function __construct() {
    $this->arrayCTOR();
    $this->families = new ArrayCollection();
    $this->musicianInstruments = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
    $this->projectInstrumentationNumbers = new ArrayCollection();
  }

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return Instrument
   */
  public function setId(int $id):Instrument
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId():int
  {
    return $this->id;
  }

  /**
   * Set name.
   *
   * @param string $name
   *
   * @return Instrument
   */
  public function setName(string $name):Instrument
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   */
  public function getName():string
  {
    return $this->name;
  }

  /**
   * Set familie.
   *
   * @param Collection $families
   *
   * @return Instrument
   */
  public function setFamilies(Collection $families):Instrument
  {
    $this->families = $families;

    return $this;
  }

  /**
   * Get families.
   *
   * @return Collection
   */
  public function getFamilies():Collection
  {
    return $this->families;
  }

  /**
   * Set sortOrder.
   *
   * @param int $sortOrder
   *
   * @return Instrument
   */
  public function setSortOrder($sortOrder):Instrument
  {
    $this->sortOrder = $sortOrder;

    return $this;
  }

  /**
   * Get sortOrder.
   *
   * @return int
   */
  public function getSortOrder():int
  {
    return $this->sortOrder;
  }

  /**
   * Set musicianInstruments.
   *
   * @param bool $musicianInstruments
   *
   * @return Instrument
   */
  public function setMusicianInstruments($musicianInstruments):Instrument
  {
    $this->musicianInstruments = $musicianInstruments;

    return $this;
  }

  /**
   * Get musicianInstruments.
   *
   * @return Collection
   */
  public function getMusicianInstruments():Collection
  {
    return $this->musicianInstruments;
  }

  /**
   * Set projectInstruments.
   *
   * @param Collection $projectInstruments
   *
   * @return Instrument
   */
  public function setProjectInstruments(Collection $projectInstruments):Instrument
  {
    $this->projectInstruments = $projectInstruments;

    return $this;
  }

  /**
   * Get projectInstruments.
   *
   * @return Collection
   */
  public function getProjectInstruments():Collection
  {
    return $this->projectInstruments;
  }

  /**
   * Set projectInstrumentationNumbers.
   *
   * @param Collection $projectInstrumentationNumbers
   *
   * @return Instrumente
   */
  public function setProjectInstrumentationNumbers(Collection $projectInstrumentationNumbers):Instrument
  {
    $this->projectInstrumentationNumbers = $projectInstrumentationNumbers;

    return $this;
  }

  /**
   * Get projectInstrumentationNumbers.
   *
   * @return Collection
   */
  public function getProjectInstrumentationNumbers():Collection
  {
    return $this->projectInstrumentationNumbers;
  }

  /**
   * Get the usage count, i.e. the number of instruments which belong
   * to this family.
   */
  public function usage():int
  {
    return $this->musicianInstruments->count()
      + $this->projectInstruments->count()
      + $this->projectInstrumentationNumbers->count();
  }
}

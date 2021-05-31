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

use Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as Multiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as DataType;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * ProjectParticipantFieldsDataOptions
 *
 * @ORM\Table(name="ProjectParticipantFieldsDataOptions")
 * @ORM\Entity
 * @Gedmo\TranslationEntity(class="TableFieldTranslation")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 *
 * Soft deletion is necessary in case the ProjectPayments table
 * already contains entries.
 */
class ProjectParticipantFieldDataOption implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TranslatableTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  /**
   * Link back to ProjectParticipantField
   *
   * @ORM\ManyToOne(targetEntity="ProjectParticipantField", inversedBy="dataOptions")
   * @ORM\Id
   */
  private $field;

  /**
   * @var \Ramsey\Uuid\UuidInterface
   *
   * @ORM\Column(type="uuid_binary")
   * @ORM\Id
   */
  private $key;

  /**
   * @var string
   *
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $label;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $data;

  /**
   * @var float
   * Optional value of a deposit for monetary options.
   *
   * @ORM\Column(type="float", nullable=true)
   */
  private $deposit;

  /**
   * @var int
   * Limit on number of group members for
   * Multiplicity::GROUPSOFPEOPLE, Multiplicity::GROUPOFPEOPLE fields.
   *
   * @ORM\Column(type="bigint", nullable=true)
   */
  private $limit;

  /**
   * @var string
   *
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=4096, nullable=true)
   */
  private $tooltip;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDatum", mappedBy="dataOption", indexBy="musician_id", cascade={"persist"}, orphanRemoval=true, fetch="EXTRA_LAZY")
   * @Gedmo\SoftDeleteableCascade(delete=false, undelete=true)
   */
  private $fieldData;

  /**
   * @var ProjectPayment
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="receivableOption")
   */
  private $payments;

  public function __construct() {
    $this->arrayCTOR();
    $this->fieldData = new ArrayCollection();
    $this->payments = new ArrayCollection();
  }

  /**
   * Set field.
   *
   * @param ProjectParticipantField $field
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setField($field):ProjectParticipantFieldDataOption
  {
    $this->field = $field;

    return $this;
  }

  /**
   * Get field.
   *
   * @return ProjectParticipantField
   */
  public function getField():ProjectParticipantField
  {
    return $this->field;
  }

  /**
   * Set key.
   *
   * @param string|UuidInterface $key
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setKey($key):ProjectParticipantFieldDataOption
  {
    if (empty($key = Uuid::asUuid($key))) {
      throw new \Exception("UUID DATA: ".$key);
    }
    $this->key = $key;

    return $this;
  }

  /**
   * Get key.
   *
   * @return UuidInterface
   */
  public function getKey()
  {
    return $this->key;
  }

  /**
   * Set label.
   *
   * @param null|string $label
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setLabel(?string $label):ProjectParticipantFieldDataOption
  {
    $this->label = $label;

    return $this;
  }

  /**
   * Get label.
   *
   * @return string|null
   */
  public function getLabel():?string
  {
    return $this->label;
  }

  /**
   * Set data.
   *
   * @param null|string $data
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setData(?string $data):ProjectParticipantFieldDataOption
  {
    $this->data = $data;

    return $this;
  }

  /**
   * Get data.
   *
   * @return null|string
   */
  public function getData():?string
  {
    return $this->data;
  }

  /**
   * Set deposit.
   *
   * @param null|string $float
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setDeposit(?float $deposit):ProjectParticipantFieldDataOption
  {
    $this->deposit = $deposit;

    return $this;
  }

  /**
   * Get deposit.
   *
   * @return null|float
   */
  public function getDeposit():?float
  {
    return $this->deposit;
  }

  /**
   * Set tooltip.
   *
   * @param string|null $tooltip
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setTooltip(?string $tooltip):ProjectParticipantFieldDataOption
  {
    $this->tooltip = $tooltip;

    return $this;
  }

  /**
   * Get tooltip.
   *
   * @return string
   */
  public function getTooltip():?string
  {
    return $this->tooltip;
  }

  /**
   * Set limit.
   *
   * @param int $limit
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setLimit($limit):ProjectParticipantFieldDataOption
  {
    $this->limit = $limit;

    return $this;
  }

  /**
   * Get limit.
   *
   * @return int
   */
  public function getLimit()
  {
    return $this->limit;
  }

  /**
   * Set fieldData.
   *
   * @param Collection $fieldData
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setFieldData($fieldData):ProjectParticipantFieldDataOption
  {
    $this->fieldData = $fieldData;

    return $this;
  }

  /**
   * Get fieldData.
   *
   * @return Collection
   */
  public function getFieldData():Collection
  {
    return $this->fieldData;
  }

  /**
   * Filter field-data by musician
   *
   * @param Musician $musician
   */
  public function getMusicianFieldData(Musician $musician):Collection
  {
    return $this->fieldData->matching(
      DBUtil::criteriaWhere([ 'musician' => $musician ])
    );
  }

  /**
   * Return the number of ProjectParticipantFieldDatum entities and
   * ProjectPayment entities attatched to this option.
   */
  public function usage():int
  {
    return $this->fieldData->count()
      + $this->payments->count();
  }
}

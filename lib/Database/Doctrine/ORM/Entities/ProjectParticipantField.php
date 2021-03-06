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

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;

/**
 * ProjectParticipantFields
 *
 * @ORM\Table(name="ProjectParticipantFields")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectParticipantFieldsRepository")
 * @Gedmo\TranslationEntity(class="TableFieldTranslation")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 *
 */
class ProjectParticipantField implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TranslatableTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\GetByUuidTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue
   */
  private $id = null;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="participantFields", fetch="EXTRA_LAZY")
   */
  private $project;

  /**
   * @var int|null
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $displayOrder = null;

  /**
   * @var string
   *
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var Types\EnumParticipantFieldMultiplicity
   *
   * @ORM\Column(type="EnumParticipantFieldMultiplicity", nullable=false)
   */
  private $multiplicity;

  /**
   * @var EnumParticipantFieldDataType
   *
   * @ORM\Column(type="EnumParticipantFieldDataType", nullable=false, options={"default"="text"})
   */
  private $dataType = 'text';

  /**
   * @var ProjectParticipantFieldDataOption
   *
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDataOption", mappedBy="field", indexBy="key", cascade={"persist"}, orphanRemoval=true)
   * @ORM\OrderBy({"label" = "ASC", "key" = "ASC"})
   * @Gedmo\SoftDeleteableCascade(delete=true, undelete=true)
   */
  private $dataOptions;

  /**
   * @var \DateTimeImmutable
   *
   * @ORM\Column(type="date_immutable", nullable=true, options={"comment"="Due-date for financial fields."})
   */
  private $dueDate = null;

  /**
   * @var \DateTimeImmutable
   *
   * @ORM\Column(type="date_immutable", nullable=true, options={"comment"="Due-date of deposit for financial fields."})
   */
  private $depositDueDate = null;

  /**
   * @var null|ProjectParticipantFieldDataOption
   *
   * @ORM\OneToOne(targetEntity="ProjectParticipantFieldDataOption")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="id", referencedColumnName="field_id"),
   *   @ORM\JoinColumn(name="default_value", referencedColumnName="key", nullable=true)
   * )
   */
  private $defaultValue = null;

  /**
   * @var string
   *
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=4096, nullable=true)
   */
  private $tooltip = null;

  /**
   * @var string
   *
   * @Gedmo\Translatable
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Tab to display the field in. If empty, then the project tab is used."})
   */
  private $tab = null;

  /**
   * @var bool|null
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $encrypted = false;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true, options={"comment"="If non-empty restrict the visbility to this comma separated list of user-groups."})
   */
  private $readers = null;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true, options={"comment"="Empty or comma separated list of groups allowed to change the field."})
   */
  private $writers = null;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDatum", mappedBy="field", fetch="EXTRA_LAZY")
   */
  private $fieldData;

  public function __construct() {
    $this->arrayCTOR();
    $this->fieldData = new ArrayCollection();
    $this->dataOptions = new ArrayCollection();
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
   * Set id.
   *
   * @param int $id
   *
   * @return ProjectParticipantField
   */
  public function setId(int $id):ProjectParticipantField
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Set project.
   *
   * @param Project $project
   *
   * @return ProjectParticipantField
   */
  public function setProject($project):ProjectParticipantField
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject():Project
  {
    return $this->project;
  }

  /**
   * Set dataOption.
   *
   * @param Collection $dataOptions
   *
   * @return ProjectParticipantField
   */
  public function setDataOptions($dataOptions):ProjectParticipantField
  {
    $this->dataOptions = $dataOptions;

    return $this;
  }

  /**
   * Get dataOption.
   *
   * @return Collection
   */
  public function getDataOptions():Collection
  {
    return $this->dataOptions;
  }

  /**
   * Get the options without UUID zero
   *
   * @param bool $includeDeleted Whether or not to include soft-deleted options.
   *
   * @return Collection
   */
  public function getSelectableOptions($includeDeleted = false):Collection
  {
    // this unfortunately just does not work.
    // return $this->dataOptions->matching(DBUtil::criteriaWhere([ '!key' => Uuid::NIL, 'deleted' => null, ]));
    return $this->dataOptions->filter(function($option) {
      /** @var ProjectParticipantFieldDataOption $option */
      return ($includeDeleted || empty($option->getDeleted()))
        && $option->getKey() != Uuid::nil();
    });
  }

  /**
   * Get the special option holding management data if present.
   *
   * @return null|ProjectParticipantFieldDataOption
   */
  public function getManagementOption():?ProjectParticipantFieldDataOption
  {
    return $this->getDataOption(Uuid::NIL);
  }

  /**
   * Get one specific option
   *
   * @param mixed $key Everything which can be converted to an UUID by
   * Uuid::asUuid().
   *
   * @return null|ProjectParticipantFieldDataOption
   */
  public function getDataOption($key):?ProjectParticipantFieldDataOption
  {
    return $this->getByUuid($this->dataOptions, $key, 'key');
  }

  /**
   * Set fieldData.
   *
   * @param Collection $fieldData
   *
   * @return ProjectParticipantField
   */
  public function setFieldData($fieldData):ProjectParticipantField
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
   * Return the number of data items associated with this field.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->dataOptions->count();
  }

  /**
   * Set displayOrder.
   *
   * @param int|null $displayOrder
   *
   * @return ProjectParticipantField
   */
  public function setDisplayOrder($displayOrder):ProjectParticipantField
  {
    $this->displayOrder = $displayOrder;

    return $this;
  }

  /**
   * Get displayOrder.
   *
   * @return int|null
   */
  public function getDisplayOrder()
  {
    return $this->displayOrder;
  }

  /**
   * Set name.
   *
   * @param string $name
   *
   * @return ProjectParticipantField
   */
  public function setName($name):ProjectParticipantField
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Set multiplicity.
   *
   * @param EnumParticipantFieldMultiplicity|string $multiplicity
   *
   * @return ProjectParticipantField
   */
  public function setMultiplicity($multiplicity):ProjectParticipantField
  {
    $this->multiplicity = new Types\EnumParticipantFieldMultiplicity($multiplicity);

    return $this;
  }

  /**
   * Get multiplicity.
   *
   * @return EnumParticipantFieldMultiplicity
   */
  public function getMultiplicity():Types\EnumParticipantFieldMultiplicity
  {
    return $this->multiplicity;
  }

  /**
   * Set dataType.
   *
   * @param EnumParticipantFieldDataType|string $dataType
   *
   * @return ProjectParticipantField
   */
  public function setDataType($dataType):ProjectParticipantField
  {
    $this->dataType = new Types\EnumParticipantFieldDataType($dataType);

    return $this;
  }

  /**
   * Get dataType.
   *
   * @return EnumParticipantFieldDataType
   */
  public function getDataType():Types\EnumParticipantFieldDataType
  {
    return $this->dataType;
  }

  /**
   * Set dueDate.
   *
   * @param string|null|\DateTimeInterface $dueDate
   *
   * @return ProjectParticipantField
   */
  public function setDueDate($dueDate):ProjectParticipantField
  {
    $this->dueDate = self::convertToDateTime($dueDate);
    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return \DateTimeImmutable|null
   */
  public function getDueDate():?\DateTimeImmutable
  {
    return $this->dueDate;
  }

  /**
   * Set depositDueDate.
   *
   * @param string|null|\DateTimeInterface $depositDueDate
   *
   * @return ProjectParticipantField
   */
  public function setDepositDueDate($depositDueDate):ProjectParticipantField
  {
    $this->depositDueDate = self::convertToDateTime($depositDueDate);
    return $this;
  }

  /**
   * Get depositDueDate.
   *
   * @return \DateTimeImmutable|null
   */
  public function getDepositDueDate():?\DateTimeImmutable
  {
    return $this->depositDueDate;
  }

  /**
   * Set defaultValue.
   *
   * @param string $defaultValue
   *
   * @return ProjectParticipantField
   */
  public function setDefaultValue($defaultValue):ProjectParticipantField
  {
    $this->defaultValue = $defaultValue;

    return $this;
  }

  /**
   * Get defaultValue.
   *
   * @return string
   */
  public function getDefaultValue()
  {
    return $this->defaultValue;
  }

  /**
   * Set tooltip.
   *
   * @param string $tooltip
   *
   * @return ProjectParticipantField
   */
  public function setTooltip($tooltip):ProjectParticipantField
  {
    $this->tooltip = $tooltip;

    return $this;
  }

  /**
   * Get tooltip.
   *
   * @return string
   */
  public function getTooltip()
  {
    return $this->tooltip;
  }

  /**
   * Set tab.
   *
   * @param string $tab
   *
   * @return ProjectParticipantField
   */
  public function setTab($tab):ProjectParticipantField
  {
    $this->tab = $tab;

    return $this;
  }

  /**
   * Get tab.
   *
   * @return string
   */
  public function getTab()
  {
    return $this->tab;
  }

  /**
   * Set encrypted.
   *
   * @param bool|null $encrypted
   *
   * @return ProjectParticipantField
   */
  public function setEncrypted($encrypted):ProjectParticipantField
  {
    $this->encrypted = $encrypted;

    return $this;
  }

  /**
   * Get encrypted.
   *
   * @return bool|null
   */
  public function getEncrypted()
  {
    return $this->encrypted;
  }

  /**
   * Set readers.
   *
   * @param string|null $readers
   *
   * @return ProjectParticipantField
   */
  public function setReaders($readers):ProjectParticipantField
  {
    $this->readers = $readers;

    return $this;
  }

  /**
   * Get readers.
   *
   * @return string|null
   */
  public function getReaders()
  {
    return $this->readers;
  }

  /**
   * Set writers.
   *
   * @param string|null $writers
   *
   * @return ProjectParticipantField
   */
  public function setWriters($writers):ProjectParticipantField
  {
    $this->writers = $writers;

    return $this;
  }

  /**
   * Get writers.
   *
   * @return string|null
   */
  public function getWriters()
  {
    return $this->writers;
  }
}

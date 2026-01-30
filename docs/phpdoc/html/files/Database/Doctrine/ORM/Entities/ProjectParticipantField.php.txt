<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

use DateTimeInterface;

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTimeImmutable;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Types as DBALTypes;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

/**
 * ProjectParticipantFields
 */
#[ORM\Table(name: 'ProjectParticipantFields')]
#[ORM\Index(fields: ['id', 'project'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectParticipantFieldsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\TranslationEntity(class: 'TableFieldTranslation')]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\ProjectParticipantFieldEntityListener::class])]
class ProjectParticipantField implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\AutoIncrementTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TranslatableTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;
  use CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\GetByUuidTrait;

  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'participantFields', fetch: 'EXTRA_LAZY')]
  private Project $project;

  #[Gedmo\Translatable(untranslated: 'untranslatedName')]
  #[ORM\Column(type: 'string', length: 128, nullable: false)]
  private string $name;

  /**
   * Untranslated variant of self:$name, filled automatically by
   * Gedmo\Translatable
   */
  private ?string $untranslatedName;

  #[ORM\Column(type: DBALTypes::ENUM, nullable: false)]
  private FieldMultiplicity $multiplicity;

  #[ORM\Column(type: DBALTypes::ENUM, nullable: false, options: ['default' => FieldDataType::TEXT])]
  private FieldDataType $dataType;

  /**
   * @var Collection<UuidInterface, ProjectParticipantFieldDataOption>
   */
  #[ORM\OneToMany(targetEntity: ProjectParticipantFieldDataOption::class, mappedBy: 'field', indexBy: 'key', cascade: ['persist'], orphanRemoval: true)]
  #[ORM\OrderBy(['label' => 'ASC', 'key' => 'ASC'])]
  #[Gedmo\SoftDeleteableCascade(delete: true, undelete: true)]
  private Collection $dataOptions;

  #[ORM\Column(type: 'date_immutable', nullable: true, options: ['comment' => 'Due-date for financial fields.'])]
  private ?DateTimeImmutable $dueDate = null;

  #[ORM\Column(type: 'date_immutable', nullable: true, options: ['comment' => 'Due-date of deposit for financial fields.'])]
  private ?DateTimeImmutable $depositDueDate = null;

  #[ORM\JoinColumn(name: 'id', referencedColumnName: 'field_id')]
  #[ORM\JoinColumn(name: 'default_value', referencedColumnName: 'key', nullable: true)]
  #[ORM\OneToOne(targetEntity: ProjectParticipantFieldDataOption::class, cascade: ['persist'])]
  private ?ProjectParticipantFieldDataOption $defaultValue = null;

  /**
   * Only for receivables and liabilities. The balancing account for
   * double-entry accounting. Currently this is just the full path of the
   * GnuCash account, separated by colons. The other account is implied by
   * project name, musician name and potentially the receivables generator.
   *
   * @todo Quite hard-coded.
   */
  #[ORM\Column(type: 'string', length: 1024, nullable: true)]
  private ?string $balancingAccount = null;

  /**
   * @var string
   */
  #[Gedmo\Translatable(untranslated: 'untranslatedTooltip')]
  #[ORM\Column(type: 'string', length: 4096, nullable: true)]
  private ?string $tooltip = null;

  /**
   * Untranslated variant of self::$tooltip, filled automatically by
   * Gedmo\Translatable
   */
  private ?string $untranslatedTooltip = null;

  #[Gedmo\Translatable(untranslated: 'untranslatedTab')]
  #[ORM\Column(type: 'string', length: 256, nullable: true, options: ['comment' => 'Tab to display the field in. If empty, then the project tab is used.'])]
  private ?string $tab = null;

  /**
   * Untranslated variant of self::$tab, filled automatically by
   * Gedmo\Translatable
   */
  private ?string $untranslatedTab = null;

  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $displayOrder = null;

  /**
   * If non-null show the field only in the respective view, either
   * "participants" or "associates". If null show the field in either view.
   */
  #[ORM\Column(type: DBALTypes::ENUM, nullable: false, options: ['default' => ParticipationContext::UNRESTRICTED])]
  private ParticipationContext $participationContext;

  #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0])]
  private ?bool $encrypted = false;

  /**
   * A bit-field which determines whether this field is exported to the
   * corresponding participant for use in the cafevdbmembers-app.
   */
  #[ORM\Column(type: DBALTypes::ENUM, nullable: false, options: ['default' => Types\EnumAccessPermission::NONE->value])]
  private Types\EnumAccessPermission $participantAccess = Types\EnumAccessPermission::NONE;

  /**
   * @var Collection<ProjectParticipantFieldDatum>
   */
  #[ORM\OneToMany(targetEntity: ProjectParticipantFieldDatum::class, mappedBy: 'field', fetch: 'EXTRA_LAZY')]
  private Collection $fieldData;

  #[ORM\OneToOne(targetEntity: ProjectEvent::class, mappedBy: 'absenceField')]
  private ?ProjectEvent $projectEvent = null;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(?Project $project = null)
  {
    $this->__wakeup();
    if ($project !== null) {
      $this->project = project;
    }
    $this->dataOptions = new ArrayCollection();
    $this->dataType = FieldDataType::TEXT;
    $this->defaultValue = null;
    $this->fieldData = new ArrayCollection();
    $this->participantAccess = Types\EnumAccessPermission::NONE;
    $this->participationContext = ParticipationContext::UNRESTRICTED;
    $this->participationContext = ParticipationContext::UNRESTRICTED;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function __clone()
  {
    $this->setId(null);
    $oldProject = $this->project;
    $oldAccess = $this->participantAccess;
    $oldDataOptions = $this->dataOptions;
    $oldDefaultValue = $this->defaultValue;
    $this->__construct();
    $this->project = $oldProject;
    $this->participantAccess = $oldAccess;
    foreach ($oldDataOptions as $oldDataOption) {
      $dataOption = clone $oldDataOption;
      $dataOption->setField($this);
      $this->dataOptions->set((string)$dataOption->getKey(), $dataOption);
      if ($oldDataOption == $oldDefaultValue) {
        $this->defaultValue = $dataOption;
      }
    }
  }

  /** {@inheritdoc} */
  public function __wakeup()
  {
    $this->arrayCTOR();
  }

  /**
   * Set project.
   *
   * @param null|int|Project $project
   *
   * @return ProjectParticipantField
   */
  public function setProject(mixed $project):ProjectParticipantField
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
  public function setDataOptions(Collection $dataOptions):ProjectParticipantField
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
  public function getSelectableOptions(bool $includeDeleted = false):Collection
  {
    // this unfortunately just does not work.
    // return $this->dataOptions->matching(DBUtil::criteriaWhere([ '!key' => Uuid::NIL, 'deleted' => null, ]));
    $filtered = $this->dataOptions->filter(function($option) use ($includeDeleted) {
      /** @var ProjectParticipantFieldDataOption $option */
      return ($includeDeleted || empty($option->getDeleted()))
        && (string)$option->getKey() != ProjectParticipantFieldDataOption::GENERATOR_KEY;
    });
    $iterator = $filtered->getIterator();
    $iterator->uasort(function(ProjectParticipantFieldDataOption $a, ProjectParticipantFieldDataOption $b) {
      $cmp = strcmp($a->getLabel(), $b->getLabel());
      return $cmp !== 0 ? $cmp : strcmp($a->getKey(), $b->getKey());
    });
    return new ArrayCollection(iterator_to_array($iterator));
  }

  /**
   * Search an option by its label.
   *
   * @param string $optionLabel
   *
   * @param bool $includeDeleted
   *
   * @return null|ProjectParticipantFieldDataOption
   */
  public function getOptionByLabel(string $optionLabel, bool $includeDeleted = false):?ProjectParticipantFieldDataOption
  {
    $criteria = [ 'label' => $optionLabel ];
    if (!$includeDeleted) {
      $criteria['deleted'] = null;
    }
    $matchingOptions = $this->dataOptions->matching(DBUtil::criteriaWhere($criteria));
    return $matchingOptions->count() == 0 ? null : $matchingOptions->first();
  }

  /**
   * Get the special option holding management data if present.
   *
   * @return null|ProjectParticipantFieldDataOption
   */
  public function getManagementOption():?ProjectParticipantFieldDataOption
  {
    return $this->getDataOption(ProjectParticipantFieldDataOption::GENERATOR_KEY);
  }

  /**
   * Get one specific option
   *
   * @param null|mixed $key Everything which can be converted to an UUID by
   * Uuid::asUuid() or null which will return just the first option if it
   * exists. The latter for convience for non-multiple options which just
   * contain a single option.
   *
   * @return null|ProjectParticipantFieldDataOption
   */
  public function getDataOption($key = null):?ProjectParticipantFieldDataOption
  {
    if ($key === null) {
      if (!empty($this->dataOptions) && $this->dataOptions->count() > 0) {
        return $this->dataOptions->first();
      } else {
        return null;
      }
    } else {
      return $this->getByUuid($this->dataOptions, $key, 'key');
    }
  }

  /**
   * Set fieldData.
   *
   * @param Collection $fieldData
   *
   * @return ProjectParticipantField
   */
  public function setFieldData(Collection $fieldData):ProjectParticipantField
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
   * Filter field-data by musician.
   *
   * @param Musician $musician
   *
   * @return Collection
   */
  public function getMusicianFieldData(Musician $musician):Collection
  {
    return $this->fieldData->matching(
      DBUtil::criteriaWhere([ 'musician' => $musician ])
    );
  }

  /**
   * Return the number of data items associated with this field.
   *
   * @return int
   */
  public function usage():int
  {
    // return $this->dataOptions->count();
    $usageCounter = 0;
    foreach ($this->dataOptions as $dataOption) {
      $usageCounter += $dataOption->usage();
    }
    if (!empty($this->projectEvent)) {
      ++$usageCounter;
    }
    return $usageCounter;
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
   * Set participationContext.
   *
   * @param null|string|ParticipationContext $participationContext On of self::ACCESS_NONE, self::ACCESS_READ, self::ACCESS_WRITE.
   *
   * @return ProjectParticipantField
   */
  public function setParticipationContext(null|string|ParticipationContext $participationContext):?ProjectParticipantField
  {
    if ($participationContext === null) {
      $this->participationContext = null;
    } elseif (ParticipationContext::get($participationContext) != $this->participationContext) {
      $this->participationContext = ParticipationContext::get($participationContext);
    }

    return $this;
  }

  /**
   * Get participationContext.
   *
   * @return ParticipationContext
   */
  public function getParticipationContext():ParticipationContext
  {
    return $this->participationContext;
  }

  /**
   * Set name.
   *
   * @param null|string $name
   *
   * @return ProjectParticipantField
   */
  public function setName(?string $name):ProjectParticipantField
  {
    $this->name = $name;
    if ($this->getLocale() == ConfigConstants::DEFAULT_LOCALE) {
      $this->untranslatedName = $this->name;
    }
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
   * Get untranslatedName.
   *
   * @return string
   */
  public function getUntranslatedName()
  {
    if ($this->isFileSystemContext()) {
      return $this->name;
    }
    return $this->untranslatedName;
  }

  /**
   * Set multiplicity.
   *
   * @param FieldMultiplicity|string $multiplicity
   *
   * @return ProjectParticipantField
   */
  public function setMultiplicity(string|FieldMultiplicity $multiplicity): ProjectParticipantField
  {
    $this->multiplicity = FieldMultiplicity::get($multiplicity);

    return $this;
  }

  /**
   * Get multiplicity.
   *
   * @return FieldMultiplicity
   */
  public function getMultiplicity(): FieldMultiplicity
  {
    return $this->multiplicity;
  }

  /**
   * Set dataType.
   *
   * @param string|FieldDataType $dataType
   *
   * @return ProjectParticipantField
   */
  public function setDataType(string|FieldDataType $dataType): ProjectParticipantField
  {
    $this->dataType = FieldDataType::get($dataType);
    return $this;
  }

  /**
   * Get dataType.
   *
   * @return EnumParticipantFieldDataType
   */
  public function getDataType(): FieldDataType
  {
    return $this->dataType;
  }

  /**
   * Set dueDate.
   *
   * @param string|null|DateTimeInterface $dueDate
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
   * @return DateTimeImmutable|null
   */
  public function getDueDate():?DateTimeImmutable
  {
    return $this->dueDate;
  }

  /**
   * Set depositDueDate.
   *
   * @param string|null|DateTimeInterface $depositDueDate
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
   * @return DateTimeImmutable|null
   */
  public function getDepositDueDate():?DateTimeImmutable
  {
    return $this->depositDueDate;
  }

  /**
   * Set defaultValue.
   *
   * @param null|ProjectParticipantFieldDataOption $defaultValue
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
   * @return null|ProjectParticipantFieldDataOption
   */
  public function getDefaultValue():?ProjectParticipantFieldDataOption
  {
    return $this->defaultValue;
  }

  /**
   * Set the balancingAccount.
   *
   * @param null|string $balancingAccount
   *
   * @return ProjectParticipantFieldBalancingAccountOption
   */
  public function setBalancingAccount(?string $balancingAccount):ProjectParticipantField
  {
    $this->balancingAccount = $balancingAccount;

    return $this;
  }

  /**
   * Get the balancingAccount.
   *
   * @return null|string
   */
  public function getBalancingAccount(): ?string
  {
    return $this->balancingAccount ?? null;
  }

  /**
   * Set tooltip.
   *
   * @param null|string $tooltip
   *
   * @return ProjectParticipantField
   */
  public function setTooltip(?string $tooltip):ProjectParticipantField
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
   * Get untranslatedTooltip.
   *
   * @return string
   */
  public function getUntranslatedTooltip()
  {
    return $this->untranslatedTooltip;
  }

  /**
   * Set tab.
   *
   * @param null|string $tab
   *
   * @return ProjectParticipantField
   */
  public function setTab(?string $tab):ProjectParticipantField
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
   * Get untranslatedTab.
   *
   * @return string
   */
  public function getUntranslatedTab()
  {
    return $this->untranslatedTab;
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
   * Set participantAccess.
   *
   * @param string|Types\EnumAccessPermission $participantAccess
   *
   * @return ProjectParticipantField
   */
  public function setParticipantAccess(string|Types\EnumAccessPermission $participantAccess):ProjectParticipantField
  {
    $this->participantAccess = Types\EnumAccessPermission::get($participantAccess);

    return $this;
  }

  /**
   * Get participantAccess.
   *
   * @return Types\EnumAccessPermission
   */
  public function getParticipantAccess(): Types\EnumAccessPermission
  {
    return $this->participantAccess;
  }

  /**
   * Set projectEvent.
   *
   * @param null|ProjectEvent $projectEvent
   *
   * @return ProjectParticipantField
   */
  public function setProjectEvent(?ProjectEvent $projectEvent):ProjectParticipantField
  {
    $this->projectEvent = $projectEvent;
    if (!empty($projectEvent) && $this->participantAccess == Types\EnumAccessPermission::NONE) {
      $this->setParticipantAccess(Types\EnumAccessPermission::READ);
    }

    return $this;
  }

  /**
   * Get projectEvent.
   *
   * @return null|ProjectEvent
   */
  public function getProjectEvent():?ProjectEvent
  {
    return $this->projectEvent;
  }

  /** @return bool Whether this field links to the cloud-file-systen. */
  public function isFileSystemContext():bool
  {
    return $this->dataType == FieldDataType::CLOUD_FOLDER || $this->dataType == FieldDataType::CLOUD_FILE;
  }

  /**
   * Remove 'name' from the set of translatable fields if it is the base of
   * file- or folder-names and thus should not change on a per-user basis.
   *
   * @param array $fields The array of annotated translatable fields.
   *
   * @return array The array of translatable fields based on the state of the
   * entity. This must be a sub-set of the input array.
   */
  public function filterTranslatableFields(array $fields):array
  {
    if ($this->isFileSystemContext()) {
      return array_filter($fields, fn($field) => $field !== 'name');
    }
    return $fields;
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return $this->name . '@' . $this->id . '[' . $this->dataType->value . ':' . $this->multiplicity->value . ']';
  }
}

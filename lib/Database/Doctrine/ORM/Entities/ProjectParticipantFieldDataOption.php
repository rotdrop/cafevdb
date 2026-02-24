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

use OCA\CAFEVDB\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType as MonetaryDatabaseType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Settings\ConfigConstants;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

/**
 * ProjectParticipantFieldsDataOptions
 */
#[ORM\Table(name: DatabaseTables::PROJECT_PARTICIPANT_FIELDS_OPTIONS_TABLE)]
#[ORM\Index(columns: ['key'])]
#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\TranslationEntity(class: 'TableFieldTranslation', idToString: ['key' => 'BIN2UUID(%s)'])]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\ProjectParticipantFieldDataOptionEntityListener::class])]
class ProjectParticipantFieldDataOption implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TranslatableTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  public const GENERATOR_KEY = Uuid::NIL;
  public const GENERATOR_LABEL = '__generator__';

  /**
   * Link back to ProjectParticipantField
   */
  #[ORM\ManyToOne(targetEntity: ProjectParticipantField::class, inversedBy: 'dataOptions')]
  #[ORM\Id]
  private ProjectParticipantField $field;

  #[ORM\Column(type: 'uuid_binary')]
  #[ORM\Id]
  private UuidInterface $key;

  #[Gedmo\Translatable(untranslated: 'untranslatedLabel')]
  #[ORM\Column(type: 'string', length: 128, nullable: true)]
  private ?string $label = null;

  /**
   * Untranslated variant of self:$label, filled automatically by
   * Gedmo\Translatable
   */
  private ?string $untranslatedLabel = null;

  /**
   * Multi-purpose field.
   *
   * - for FieldMultiplicity::RECURRING the generator option (Uuid::NIL) stores
   *   the name of the PHP generator class.
   * - the InstrumentInsuranceReceivablesGenertor uses this to store the year
   *   and the broker.
   * - the (yet unused) PeriodicReceivablesGenerator stores the timestamp of
   *   the birth of its receivables.
   */
  #[ORM\Column(type: 'string', length: 1024, nullable: true)]
  private ?string $data = null;

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
   * Optional value of a deposit for monetary options.
   */
  #[ORM\Column(type: MonetaryDatabaseType::NAME, nullable: true)]
  private ?MonetaryNumberType $deposit = null;

  /**
   * Limit on number of group members for
   * FieldMultiplicity::GROUPSOFPEOPLE, FieldMultiplicity::GROUPOFPEOPLE
   * fields. Also misused as starting date for recurring receivables
   * generators.
   */
  #[ORM\Column(type: 'bigint', nullable: true)]
  private ?int $limit = null;

  #[Gedmo\Translatable]
  #[ORM\Column(type: 'string', length: 4096, nullable: true)]
  private ?string $tooltip = null;

  /** @var Collection<int, ProjectParticipantFieldDatum> */
  #[ORM\OneToMany(targetEntity: ProjectParticipantFieldDatum::class, mappedBy: 'dataOption', indexBy: 'musician_id', cascade: ['persist'], orphanRemoval: true, fetch: 'EXTRA_LAZY')]
  #[Gedmo\SoftDeleteableCascade(delete: false, undelete: true)]
  private Collection $fieldData;

  /**
   * @var Collection<ProjectPayment>
   */
  #[ORM\OneToMany(targetEntity: ProjectPayment::class, mappedBy: 'receivableOption')]
  private Collection $payments;

  /**
   * @var Collection<InvoiceItem>
   */
  #[ORM\OneToMany(targetEntity: InvoiceItem::class, mappedBy: 'receivableOption')]
  private Collection $invoiceItems;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->__wakeup();
    $this->fieldData = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->invoiceItems = new ArrayCollection();
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function __clone()
  {
    $oldKey = $this->key;
    $this->__construct();
    $this->key = (string)$oldKey == self::GENERATOR_KEY
      ? $oldKey
      : Uuid::create();
  }

  /** {@inheritdoc} */
  public function __wakeup()
  {
    $this->arrayCTOR();
  }

  /**
   * Set field.
   *
   * @param null|int|ProjectParticipantField $field
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setField(mixed $field):ProjectParticipantFieldDataOption
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
   * Initialize the key field if it is not yet set. We assume that UUIDs are
   * really unique, so just create one and be gone.
   *
   * @return void
   */
  private function ensureKeyNotNull(): void
  {
    $this->key = $this->key ?? Uuid::create();
  }

  /**
   * Set key.
   *
   * @param string|UuidInterface $key
   *
   * @return self
   */
  public function setKey(string|UuidInterface $key): self
  {
    $key = Uuid::asUuid($key);
    if (empty($key)) {
      throw new Exceptions\DatabaseException("UUID DATA: ".$key);
    }
    $this->key = $key;

    return $this;
  }

  /**
   * Get key.
   *
   * @return UuidInterface
   */
  public function getKey(): UuidInterface
  {
    $this->ensureKeyNotNull();
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
    if ($this->getLocale() == ConfigConstants::DEFAULT_LOCALE) {
      $this->untranslatedLabel = $this->label;
    }
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
   * Set untranslatedLabel.
   *
   * @param null|string $untranslatedLabel
   *
   * @return ProjectParticipantFieldDataOption
   *
   * @throws Exceptions\DatabaseReadonlyException
   */
  public function setUntranslatedLabel(?string $untranslatedLabel):ProjectParticipantFieldDataOption
  {
    throw new Exceptions\DatabaseReadonlyException('The property "untranslatedLabel" cannot be set, it is read-only.');
    return $this;
  }

  /**
   * Get untranslatedLabel.
   *
   * @return string|null
   */
  public function getUntranslatedLabel():?string
  {
    return $this->untranslatedLabel;
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
  public function getData(): ?string
  {
    return $this->data ?? null;
  }

  /**
   * Set the balancingAccount.
   *
   * @param null|string $balancingAccount
   *
   * @return ProjectParticipantFieldBalancingAccountOption
   */
  public function setBalancingAccount(?string $balancingAccount):ProjectParticipantFieldDataOption
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
    return $this->balancingAccount ?? ($this->field->getBalancingAccount() ?? null);
  }

  /**
   * Set deposit.
   *
   * @param null|int|float|string|RationalNumber $deposit
   *
   * @return self
   */
  public function setDeposit(null|int|float|string|RationalNumber $deposit): self
  {
    if ($deposit !== null) {
      $deposit = MonetaryNumberType::create($deposit);
    }
    $this->deposit = $deposit;

    return $this;
  }

  /**
   * Get deposit.
   *
   * @return ?MonetaryNumberType
   */
  public function getDeposit(): ?MonetaryNumberType
  {
    return $this->deposit ?? null;
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
   * @param null|int $limit
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function setLimit(?int $limit):ProjectParticipantFieldDataOption
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
  public function setFieldData(Collection $fieldData):ProjectParticipantFieldDataOption
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
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return ProjectParticipantPaymentsOption
   */
  public function setPayments(Collection $payments):ProjectParticipantFieldDataOption
  {
    $this->payments = $payments;

    return $this;
  }

  /**
   * Get payments.
   *
   * @return Collection
   */
  public function getPayments():Collection
  {
    return $this->payments;
  }

  /**
   * Set invoiceItems.
   *
   * @param Collection $invoiceItems
   *
   * @return ProjectParticipantInvoiceItemsOption
   */
  public function setInvoiceItems(Collection $invoiceItems):ProjectParticipantFieldDataOption
  {
    $this->invoiceItems = $invoiceItems;

    return $this;
  }

  /**
   * Get invoiceItems.
   *
   * @return Collection
   */
  public function getInvoiceItems():Collection
  {
    return $this->invoiceItems;
  }

  /**
   * Filter field-data by musician.
   *
   * @param Musician $musician
   *
   * @return Collection
   *
   * @todo Why does this return a collection? There should be zero or one data
   * item.
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
   *
   * @return int
   */
  public function usage():int
  {
    return $this->fieldData->count() + $this->payments->count() + $this->invoiceItems->count();
  }

  /** @return bool Whether this field links to the cloud-file-systen. */
  public function isFileSystemContext():bool
  {
    return $this->field->getDataType() == FieldDataType::CLOUD_FILE
      && $this->field->getMultiplicity() != FieldMultiplicity::SIMPLE;
  }

  /**
   * Remove 'label' from the set of translatable fields if it is the base of
   * file- or folder-names and thus should not change on a per-user basis.
   *
   * @param array $fields The array of annotated translatable fields.
   *
   * @return array The array of translatable fields based on the state of the
   * entity. This must be a sub-set of the input array.
   */
  public function filterTranslatableFields(array $fields):array
  {
    if (($this->field instanceof ProjectParticipantField) && $this->isFileSystemContext()) {
      // Field name is used as file-system name, so keep it "constant", do not translate
      return array_filter($fields, fn($field) => $field !== 'label');
    }
    if ((string)$this->key == self::GENERATOR_KEY || $this->label == self::GENERATOR_LABEL) {
      return array_filter($fields, fn($field) => $field !== 'label');
    }
    return $fields;
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return $this->label . ':' . $this->key . (empty($this->field) ? '' : '@' . $this->field->getName() . ':' . $this->field->getId());
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use RuntimeException;
use Throwable;

use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

/**
 * ProjectParticipantFieldsData
 */
#[ORM\Table(name: 'ProjectParticipantFieldsData')]
#[ORM\Index(fields: ['field', 'project'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectParticipantFieldDataRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
class ProjectParticipantFieldDatum implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  const PAYMENT_REFERENCE_SEPARATOR = CompositePayment::SUBJECT_OPTION_SEPARATOR;

  #[ORM\ManyToOne(targetEntity: ProjectParticipantField::class, inversedBy: 'fieldData', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private ProjectParticipantField $field;

  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'participantFieldsData', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Project $project;

  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'projectParticipantFieldsData', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Musician $musician;

  #[ORM\Column(type: 'uuid_binary')]
  #[ORM\Id]
  private UuidInterface $optionKey;

  #[ORM\Column(type: 'text', length: 16777215, nullable: true, options: ['default' => null])]
  private ?string $optionValue = null;

  /**
   * Optional value of a deposit for monetary options. This is
   * unused if the deposit is fixed by single- or multi-select
   * options. Supported range is IIIII.DD which is plenty at the time of this
   * writing.
   */
  #[ORM\Column(type: 'decimal_rational_monetary', nullable: true)]
  private ?RationalNumber $deposit = null;

  #[ORM\JoinColumn(name: 'field_id', referencedColumnName: 'field_id')]
  #[ORM\JoinColumn(name: 'option_key', referencedColumnName: 'key')]
  #[ORM\ManyToOne(targetEntity: ProjectParticipantFieldDataOption::class, inversedBy: 'fieldData', fetch: 'EXTRA_LAZY')]
  private ProjectParticipantFieldDataOption $dataOption;

  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id')]
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id')]
  #[ORM\ManyToOne(targetEntity: ProjectParticipant::class, inversedBy: 'participantFieldsData', fetch: 'EXTRA_LAZY')]
  private ProjectParticipant $projectParticipant;

  /**
   * @var Collection<ProjectPayment>
   */
  #[ORM\OneToMany(targetEntity: ProjectPayment::class, mappedBy: 'receivable')]
  private Collection $payments;

  /**
   * @var Collection<InvoiceItem>
   */
  #[ORM\OneToMany(targetEntity: InvoiceItem::class, mappedBy: 'receivable')]
  private Collection $invoiceItems;

  /**
   * Optional. ATM only used for particular auto-generated monetary fields.
   */
  #[ORM\OneToOne(targetEntity: DatabaseStorageFile::class, cascade: ['all'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
  private ?DatabaseStorageFile $supportingDocument = null;

  /** TBD. */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->payments = new ArrayCollection();
    $this->invoiceItems = new ArrayCollection();
  }

  /**
   * Set project.
   *
   * @param int|null|Project $project
   *
   * @return ProjectParticipantProjectsData
   */
  public function setProject($project):ProjectParticipantFieldDatum
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
   * Set musician.
   *
   * @param int|null|Musician $musician
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setMusician($musician):ProjectParticipantFieldDatum
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician():Musician
  {
    return $this->musician;
  }

  /**
   * Set projectParticipant.
   *
   * @param ProjectParticipant $projectParticipant
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setProjectParticipant(ProjectParticipant $projectParticipant):ProjectParticipantFieldDatum
  {
    $this->projectParticipant = $projectParticipant;
    $this->musician = $projectParticipant->getMusician();
    $this->project = $projectParticipant->getProject();

    return $this;
  }

  /**
   * Get projectParticipant.
   *
   * @return ProjectParticipant
   */
  public function getProjectParticipant():ProjectParticipant
  {
    return $this->projectParticipant;
  }

  /**
   * Set field.
   *
   * @param int|null|ProjectParticipantField $field
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setField($field):ProjectParticipantFieldDatum
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
   * Set dataOption.
   *
   * @param ProjectParticipantFieldDataOption $dataOption
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setDataOption(ProjectParticipantFieldDataOption $dataOption):ProjectParticipantFieldDatum
  {
    $this->dataOption = $dataOption;
    $this->field = $dataOption->getField();
    $this->optionKey = $dataOption->getKey();

    return $this;
  }

  /**
   * Get dataOption.
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function getDataOption():ProjectParticipantFieldDataOption
  {
    return $this->dataOption;
  }

  /**
   * Set optionValue.
   *
   * @param null|string|RationalNumber $optionValue RationalNumber is only
   * allowed for monetary fields.
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setOptionValue(null|string|RationalNumber $optionValue):ProjectParticipantFieldDatum
  {
    if ($optionValue instanceof RationalNumber) {
      $scale = ($this->field->getDataType() == FieldDataType::LIABILITIES || $this->field->getDataType() == FieldDataType::RECEIVABLES)
        ? 2
        : -1;
      $this->optionValue = $optionValue->toDecimal($scale);
    } else {
      $this->optionValue = $optionValue;
    }

    return $this;
  }

  /**
   * Get optionValue.
   *
   * @return null|string
   */
  public function getOptionValue():?string
  {
    return $this->optionValue;
  }

  /**
   * Set optionKey.
   *
   * @param string|UuidInterface $optionKey
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setOptionKey($optionKey):ProjectParticipantFieldDatum
  {
    $uuid = Uuid::asUuid($optionKey);
    if (empty($uuid)) {
      throw new RuntimeException('Empty option key data.');
    }
    if ($uuid == ProjectParticipantFieldDataOption::GENERATOR_KEY) {
      throw new RuntimeException('Generator options must not be linked to field data.');
    }
    $this->optionKey = $uuid;

    return $this;
  }

  /**
   * Get optionKey.
   *
   * @return UuidInterface
   */
  public function getOptionKey()
  {
    return $this->optionKey;
  }

  /**
   * Set deposit.
   *
   * @param null|int|float|string|RationalNumber $deposit
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setDeposit(null|int|float|string|RationalNumber $deposit):ProjectParticipantFieldDatum
  {
    if ($deposit !== null) {
      $deposit = RationalNumber::create($deposit);
    }
    $this->deposit = $deposit;

    return $this;
  }

  /**
   * Get deposit.
   *
   * @return null|RationalNumber
   */
  public function getDeposit():?RationalNumber
  {
    return $this->deposit ?? null;
  }

  /**
   * Set supportingDocument.
   *
   * @param null|DatabaseStorageFile $supportingDocument
   *
   * @return ProjectParticipantFieldDatum
   */
  public function setSupportingDocument(?DatabaseStorageFile $supportingDocument):ProjectParticipantFieldDatum
  {
    $this->supportingDocument = $supportingDocument;

    return $this;
  }

  /**
   * Get supportingDocument.
   *
   * @return null|DatabaseStorageFile
   */
  public function getSupportingDocument():?DatabaseStorageFile
  {
    return $this->supportingDocument;
  }

  /**
   * Set payments.
   *
   * @param null|Collection $payments
   *
   * @return PaymentsParticipantPaymentssData
   */
  public function setPayments(?Collection $payments):ProjectParticipantFieldDatum
  {
    $this->payments = $payments;

    return $this;
  }

  /**
   * Get payments.
   *
   * @return Payments
   */
  public function getPayments():?Collection
  {
    return $this->payments;
  }

  /**
   * Set invoiceItems.
   *
   * @param null|Collection $invoiceItems
   *
   * @return InvoiceItemsParticipantInvoiceItemssData
   */
  public function setInvoiceItems(?Collection $invoiceItems):ProjectParticipantFieldDatum
  {
    $this->invoiceItems = $invoiceItems;

    return $this;
  }

  /**
   * Get invoiceItems.
   *
   * @return InvoiceItems
   */
  public function getInvoiceItems():?Collection
  {
    return $this->invoiceItems;
  }

  /**
   * Generate a data-item referring to the default value of the given
   * field. If the field does not have a default return \null.
   *
   * @param ProjectParticipantField $field
   *
   * @param ProjectParticipant $participant
   *
   * @return null|ProjectParticipantFieldDatum
   */
  public static function fromDefaultValue(ProjectParticipantField $field, ProjectParticipant $participant):?ProjectParticipantFieldDatum
  {
    $defaultValue = $field->getDefaultValue();
    if ($defaultValue === null) {
      return null;
    }
    $fieldContext = $field->getParticipationContext();
    $participantContext = $participant->getParticipationContext();
    if ($fieldContext != ParticipationContext::UNRESTRICTED
        && $participantContext != ParticipationContext::UNRESTRICTED
        && $fieldContext != $participantContext) {
      return null;
    }

    $datum = (new ProjectParticipantFieldDatum)
      ->setField($field)
      ->setProjectParticipant($participant)
      ->setDataOption($defaultValue);
    switch ($field->getMultiplicity()) {
      case FieldMultiplicity::SIMPLE:
        // value is stored in the the data item
        $datum->setOptionValue($defaultValue->getData());
        $datum->setDeposit($defaultValue->getDeposit());
        break;
      default:
        break;
    }
    $field->getFieldData()->add($datum);
    $defaultValue->getFieldData()->set($participant->getMusician()->getId(), $datum);

    return $datum;
  }

  /**
   * The amount to pay for this service-fee option.
   *
   * Only meaningful if
   * ProjectParticipantFieldDatum::getField()::getDataType() equals
   * FieldDataType::RECEIVABLES, FieldDataType::LIABILITIES.
   *
   * For FieldDataType::LIABILITIES the amount is negated.
   *
   * @return RationalNumber
   */
  public function amountPayable():RationalNumber
  {
    switch ($this->field->getMultiplicity()) {
      case FieldMultiplicity::SINGLE:
      case FieldMultiplicity::MULTIPLE:
      case FieldMultiplicity::PARALLEL:
      case FieldMultiplicity::GROUPSOFPEOPLE:
        $storedValue = $this->dataOption->getData();
        try {
          $value = RationalNumber::fromDecimal($storedValue);
        } catch (Throwable $t) {
          throw new RuntimeException('Stored value cannot be converted to decimal: "' . $storedValue . '".');
        }
        break;
      case FieldMultiplicity::GROUPOFPEOPLE:
        // value in management option of $field
        $managementOption = $this->field->getManagementOption();
        if (empty($managementOption)) {
          throw new RuntimeException('Unable to access management option for obtaining the field value.');
        }
        $storedValue = $managementOption->getData();
        try {
          $value = RationalNumber::fromDecimal($storedValue);
        } catch (Throwable $t) {
          throw new RuntimeException('Stored value cannot be converted to decimal: "' . $storedValue . '".');
        }
        break;
      case FieldMultiplicity::SIMPLE:
      case FieldMultiplicity::RECURRING:
        if (empty($this->optionValue)) {
          $value = RationalNumber::zero();
        } else {
          $storedValue = $this->optionValue;
          try {
            $value = RationalNumber::fromDecimal($storedValue);
          } catch (Throwable $t) {
            throw new RuntimeException('Stored value cannot be converted to decimal: "' . $storedValue . '".');
          }
        }
        break;
      default:
        throw new RuntimeException('Unhandled multiplicity tag: "' . (string)$this->field->getMultiplicity() . '".');
    }
    if ($this->field->getDataType() == FieldDataType::LIABILITIES) {
      $value = $value->neg();
    }
    return $value;
  }

  /**
   * The height of the deposit to pay, if any.
   *
   * Only meaningful if
   * ProjectParticipantFieldDatum::getField()::getDataType() equals
   * FieldDataType::RECEIVABLES, FieldDataType::LIABILITIES.
   *
   * For FieldDataType::LIABILITIES the amount is negated.
   *
   * @return null|RationalNumber
   */
  public function depositAmount():?RationalNumber
  {
    $value = null;
    switch ($this->field->getMultiplicity()) {
      case FieldMultiplicity::SINGLE:
      case FieldMultiplicity::MULTIPLE:
      case FieldMultiplicity::PARALLEL:
      case FieldMultiplicity::GROUPSOFPEOPLE:
        $value = $this->dataOption->getDeposit();
        break;
      case FieldMultiplicity::GROUPOFPEOPLE:
        // value in management option of $field
        $managementOption = $this->field->getManagementOption();
        if (empty($managementOption)) {
          throw new RuntimeException('Unable to access management option for obtaining the field value.');
        }
        $value = $managementOption->getDeposit();
        break;
      case FieldMultiplicity::SIMPLE:
        $value = $this->getDeposit();
        break;
      case FieldMultiplicity::RECURRING:
        break;
      default:
        throw new RuntimeException('Unhandled multiplicity tag: "' . (string)$this->field->getMultiplicity() . '".');
    }
    if ($value !== null && $this->field->getDataType() == FieldDataType::LIABILITIES) {
      $value->negEq();
    }
    return $value;
  }

  /**
   * The amount already paid as stored in the ProjectPayment entities.
   *
   * Only meaningful if
   * ProjectParticipantFieldDatum::getField()::getDataType() is
   * 'service-fee'.
   *
   * @return RationalNumber
   */
  public function amountPaid():RationalNumber
  {
    // sum up the values of all related payments
    return $this->payments->reduce(
      fn(RationalNumber $accumulator, ProjectPayment $payment) => $accumulator->add($payment->getAmount()),
      RationalNumber::zero(),
    );
  }

  /**
   * The amount already invoiced as stored in the InvoiceItem entities. Should
   * typically be 0 (no invoice) or equal to the total amount.
   *
   * Only meaningful if this is a monetary field.
   *
   * @return RationalNumber
   */
  public function amountInvoiced():RationalNumber
  {
    // sum up the values of all related invoice items
    return $this->invoiceItems->reduce(
      fn(RationalNumber $accumulator, InvoiceItem $item) => $accumulator->add($item->getAmount()),
      RationalNumber::zero(),
    );
  }

  /**
   * Get the balancingAccount.
   *
   * @return null|string
   */
  public function getBalancingAccount(): ?string
  {
    return $this->dataOption->getBalancingAccount();
  }

  /**
   * Suggestion for a reference field for debit notes or money
   * transfers. Constructed from the labels of the associated
   * ProjectParticipantField and ProjectParticipantFieldDataOption
   * entities.
   *
   * @return string
   */
  public function paymentReference():string
  {
    // construct something nice from the various label fields:
    // - name of ProjectParticipantField
    // - label of ProjectParticipantFieldDataOption
    $fieldName = $this->field->getName();
    $optionLabel = $this->dataOption->getLabel();
    if (empty($fieldName)) {
      return $optionLabel;
    }
    if (empty($optionLabel) || $optionLabel === 'null' || $fieldName == $optionLabel) {
      return $fieldName;
    }
    return $fieldName . self::PAYMENT_REFERENCE_SEPARATOR . $optionLabel;
  }

  /**
   * Return the number of linked ProjectPayment entities.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->payments->count() + $this->invoiceItems->count();
  }

  /**
   * Return the effective option value, either by fetching it from the
   * option or from the own value field. This will not retrieve
   * referenced objects like files or collections of people.
   *
   * @return string
   */
  public function getEffectiveValue()
  {
    switch ($this->field->getMultiplicity()) {
      case FieldMultiplicity::SIMPLE:
      case FieldMultiplicity::RECURRING:
        return $this->optionValue;
        break;
      case FieldMultiplicity::GROUPOFPEOPLE:
      case FieldMultiplicity::GROUPSOFPEOPLE:
      case FieldMultiplicity::MULTIPLE:
      case FieldMultiplicity::SINGLE:
        return $this->dataOption->getData();
        break;
      case FieldMultiplicity::PARALLEL:
        if ($this->field->getDataType() == FieldDataType::CLOUD_FILE
            || $this->field->getDataType() == FieldDataType::DB_FILE) {
          return $this->optionValue;
        } else {
          return $this->dataOption->getData();
        }
        break;
    }
    // perhaps this should throw ...
    return null;
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    $optionName = ($this->dataOption instanceof ProjectParticipantFieldDataOption)
      ? $this->dataOption->getLabel()
      : '?';
    $fieldName =  ($this->field instanceof ProjectParticipantField)
      ? (string)$this->field
      : '?';
    $personName = ($this->projectParticipant instanceof ProjectParticipant)
      ? (string)$this->projectParticipant
      : '?';

    return $this->optionValue . '@[' . $fieldName . ' -> ' . $optionName  . ' -> ' . $personName . ']';
  }
}

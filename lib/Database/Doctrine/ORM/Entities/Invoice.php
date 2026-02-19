<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

use Closure;
use DateTimeInterface;
use InvalidArgumentException;

use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTimeImmutable;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\DefaultExpression\CurrentDate;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * Invoices collects a couple of InvoiceItems of the same Musician.
 */
#[ORM\Table(name: DatabaseTables::INVOICES_TABLE)]
#[ORM\UniqueConstraint(columns: ['notification_message_id'])]
#[ORM\UniqueConstraint(columns: ['invoice_number'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InvoicesRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
class Invoice implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\AutoIncrementTrait;
  use CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait; // filename of supporting document.

  public const INVOICE_NUMBER_SEPARATOR = '/';
  /**
   * @var array
   *
   * The fields which are using in order to autogenerate the slug field.
   */
  public const INVOICE_NUMBER_FIELDS = [ 'project', 'balanceDocumentsFolder' ];

  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  #[Gedmo\Slug(
    fields: [], // self-referencing. Does it work?
    updatable: true,
    unique: true,
    uniqueInitialSuffix: true,
    // style: 'camel',
    separator: self::INVOICE_NUMBER_SEPARATOR,
  )]
  #[Gedmo\SlugHandler(
    class: CAFEVDB\Listeners\Sluggable\InvoiceNumberHandler::class,
  )]
  private string $invoiceNumber;

  /**
   * This will -- must be -- a member of the executive borad. But the plan is
   * to tie address-fields to convenience methods of the Musician entity,
   * so better use it here.
   */
  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'originatedInvoices')]
  private Musician $originator;

  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'invoices', fetch: 'EXTRA_LAZY')]
  private Musician $debitor;

  /**
   * The total amount for the bank transaction. This must equal the
   * sum of the self::$invoiceItems collection.
   *
   * @todo If this is always the sum and thus can be computed, why then this
   * field?
   */
  #[ORM\Column(type: 'decimal_rational_monetary', nullable: false, options: ['default' => '0.00'])]
  private RationalNumber $amount;

  /**
   * This should be set to the date of the actual sending out of the invoice.
   */
  #[ORM\Column(type: 'date_immutable', nullable: false, options: ['default' => new CurrentDate()])]
  private DateTimeImmutable $invoiceDate;

  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private ?DateTimeImmutable $dueDate;

  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private ?DateTimeImmutable $balancedDate;

  /**
   * Subject of the bank transaction.
   */
  #[ORM\Column(type: 'string', length: 1024, nullable: false)]
  private string $subject;

  /**
   * Free-text lead-in of the invoice letter.
   *
   * @todo Should be remove and replaced by a table listing just the
   * individual invoice items which just should be enough.
   */
  #[ORM\Column(type: 'text', nullable: true)]
  private ?string $purpose;

  /**
   * @var Collection<InvoiceItem>
   */
  #[ORM\OneToMany(targetEntity: InvoiceItem::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
  private Collection $invoiceItems;

  /**
   * There may be an associated debit-note. If so: this it is.
   */
  #[ORM\ManyToOne(targetEntity: SepaBulkTransaction::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')] // Promote any changes to the sepa transaction.
  #[Gedmo\Timestampable(on: ['update', 'create', 'delete'], timestampField: 'updated')]
  private ?SepaBulkTransaction $sepaTransaction = null;

  /**
   * The bank account used for this payment.
   */
  #[ORM\JoinColumn(name: 'debitor_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'bank_account_sequence', referencedColumnName: 'sequence', nullable: true)]
  #[ORM\ManyToOne(targetEntity: SepaBankAccount::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private ?SepaBankAccount $sepaBankAccount = null;

  /**
   * The debit-mandate used for this payment, if any.
   */
  #[ORM\JoinColumn(name: 'debitor_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'debit_mandate_sequence', referencedColumnName: 'sequence', nullable: true)]
  #[ORM\ManyToOne(targetEntity: SepaDebitMandate::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private ?SepaDebitMandate $sepaDebitMandate = null;

  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'invoices', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
  #[ORM\JoinColumn(nullable: false)]
  private Project $project;

  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false)]
  #[ORM\JoinColumn(name: 'debitor_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\ManyToOne(targetEntity: ProjectParticipant::class, inversedBy: 'invoices', fetch: 'EXTRA_LAZY')]
  private ProjectParticipant $projectParticipant;

  #[ORM\ManyToOne(targetEntity: DatabaseStorageFolder::class, fetch: 'EXTRA_LAZY')]
  private ?DatabaseStorageFolder $balanceDocumentsFolder = null;

  #[ORM\OneToOne(targetEntity: DatabaseStorageFile::class, cascade: ['all'])]
  private ?DatabaseStorageFile $writtenInvoice = null;

  /**
   * The legal reason for the taxation (sales tax)
   */
  #[ORM\ManyToOne(targetEntity: TaxationStatutorySource::class, inversedBy: 'invoices', cascade: ['persist'])]
  #[ORM\JoinColumn(nullable: false)]
  private TaxationStatutorySource $taxationStatutorySource;

  /**
   * Pre notification email sent out to the recipients.
   */
  #[ORM\JoinColumn(name: 'notification_message_id', referencedColumnName: 'message_id', nullable: true)]
  #[ORM\OneToOne(targetEntity: SentEmail::class, inversedBy: 'invoice')]
  private ?SentEmail $notificationEmail = null;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->invoiceItems = new ArrayCollection;
    $this->setAmount(0);
  }

  /**
   * Set invoiceItems.
   *
   * @param Collection $invoiceItems
   *
   * @return Invoice
   */
  public function setInvoiceItems(Collection $invoiceItems):Invoice
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
   * Set amount.
   *
   * @param int|float|string|RationalNumber $amount
   *
   * @return ProjectPayment
   */
  public function setAmount(int|float|string|RationalNumber $amount):Invoice
  {
    $this->amount = RationalNumber::create($amount);

    return $this;
  }

  /**
   * Get amount.
   *
   * @return RationalNumber
   */
  public function getAmount():RationalNumber
  {
    return $this->amount;
  }

  /**
   * Return the sum of the amounts of the individual payments, which
   * should sum up to $this->amount, of course.
   *
   * @return RationalNumber
   */
  public function sumInvoiceItemsAmount():RationalNumber
  {
    return $this->invoiceItems->reduce(
      fn(RationalNumber $accumulator, InvoiceItem $item) => $accumulator->add($item->getAmount()),
      RationalNumber::zero(),
    );
  }

  /**
   * Check whether the data is internally consistent, in particular if the
   * invoice items sum up to the total sum.
   *
   * @return bool
   */
  public function isConsistent():bool
  {
    if ($this->amount != $this->sumInvoiceItemsAmount()) {
      return false;
    }
    return true;
  }

  /**
   * Set debitor.
   *
   * @param null|int|Musician $debitor
   *
   * @return Invoice
   */
  public function setDebitor($debitor):Invoice
  {
    $this->debitor = $debitor;

    return $this;
  }

  /**
   * Get debitor.
   *
   * @return Musician
   */
  public function getDebitor():?Musician
  {
    return $this->debitor;
  }

  /**
   * Set originator.
   *
   * @param Musician $originator
   *
   * @return Invoice
   */
  public function setOriginator(Musician $originator):Invoice
  {
    $this->originator = $originator;

    return $this;
  }

  /**
   * Get originator.
   *
   * @return null|Musician
   */
  public function getOriginator():?Musician
  {
    return $this->originator;
  }

  /**
   * Set project.
   *
   * @param null|int|Project $project
   *
   * @return Invoice
   */
  public function setProject($project):Invoice
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject():?Project
  {
    return $this->project;
  }

  /**
   * Set projectParticipant.
   *
   * @param null|ProjectParticipant $projectParticipant
   *
   * @return Invoice
   */
  public function setProjectParticipant(ProjectParticipant $projectParticipant):Invoice
  {
    $this->projectParticipant = $projectParticipant;

    return $this;
  }

  /**
   * Get projectParticipant.
   *
   * @return ProjectParticipant
   */
  public function getProjectParticipant():?ProjectParticipant
  {
    return $this->projectParticipant;
  }

  /**
   * Set invoiceDate.
   *
   * @param \DateTime|null $invoiceDate
   *
   * @return Invoice
   */
  public function setInvoiceDate($invoiceDate = null):Invoice
  {
    $this->invoiceDate = self::convertToDateTime($invoiceDate);

    return $this;
  }

  /**
   * Get invoiceDate.
   *
   * @return \DateTime|null
   */
  public function getInvoiceDate():?DateTimeInterface
  {
    return $this->invoiceDate;
  }

  /**
   * Set dueDate.
   *
   * @param \DateTime|null $dueDate
   *
   * @return Invoice
   */
  public function setDueDate($dueDate = null):Invoice
  {
    $this->dueDate = self::convertToDateTime($dueDate);

    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return \DateTime|null
   */
  public function getDueDate():?DateTimeInterface
  {
    return $this->dueDate;
  }

  /**
   * Set balancedDate.
   *
   * @param \DateTime|null $balancedDate
   *
   * @return Invoice
   */
  public function setBalancedDate($balancedDate = null):Invoice
  {
    $this->balancedDate = self::convertToDateTime($balancedDate);

    return $this;
  }

  /**
   * Get balancedDate.
   *
   * @return \DateTime|null
   */
  public function getBalancedDate():?DateTimeInterface
  {
    return $this->balancedDate;
  }

  /**
   * Set subject.
   *
   * @param null|string $subject
   *
   * @return Invoice
   */
  public function setSubject(?string $subject):Invoice
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return null|string
   */
  public function getSubject():?string
  {
    return $this->subject;
  }

  /**
   * Set purpose.
   *
   * @param null|string $purpose
   *
   * @return Invoice
   */
  public function setPurpose(?string $purpose):Invoice
  {
    $this->purpose = $purpose;

    return $this;
  }

  /**
   * Get purpose.
   *
   * @return null|string
   */
  public function getPurpose():?string
  {
    return $this->purpose;
  }

  /**
   * Set invoiceNumber.
   *
   * @param null|string $invoiceNumber
   *
   * @return Invoice
   */
  public function setInvoiceNumber(?string $invoiceNumber):Invoice
  {
    $this->invoiceNumber = $invoiceNumber;

    return $this;
  }

  /**
   * Get invoiceNumber.
   *
   * @return null|string
   */
  public function getInvoiceNumber():?string
  {
    return $this->invoiceNumber;
  }

  /**
   * Set debitNote.
   *
   * @param SepaDebitNote|null $debitNote
   *
   * @return Invoice
   */
  public function setDebitNote($debitNote):Invoice
  {
    $this->debitNote = $debitNote;

    return $this;
  }

  /**
   * Get debitNote.
   *
   * @return SepaDebitNote|null
   */
  public function getDebitNote()
  {
    return $this->debitNote;
  }

  /**
   * Set sepaBankAccount.
   *
   * @param string|null $sepaBankAccount
   *
   * @return Invoice
   */
  public function setSepaBankAccount(?SepaBankAccount $sepaBankAccount):Invoice
  {
    $this->sepaBankAccount = $sepaBankAccount;

    return $this;
  }

  /**
   * Get sepaBankAccount.
   *
   * @return SepaBankAccount|null
   */
  public function getSepaBankAccount():?SepaBankAccount
  {
    return $this->sepaBankAccount;
  }

  /**
   * Set sepaDebitMandate.
   *
   * @param string|null $sepaDebitMandate
   *
   * @return Invoice
   */
  public function setSepaDebitMandate(?SepaDebitMandate $sepaDebitMandate):Invoice
  {
    $this->sepaDebitMandate = $sepaDebitMandate;

    return $this;
  }

  /**
   * Get sepaDebitMandate.
   *
   * @return SepaDebitMandate|null
   */
  public function getSepaDebitMandate():?SepaDebitMandate
  {
    return $this->sepaDebitMandate;
  }

  /**
   * Set sepaTransaction.
   *
   * @param string|null $sepaTransaction
   *
   * @return Invoice
   */
  public function setSepaTransaction(?SepaBulkTransaction $sepaTransaction):Invoice
  {
    $this->sepaTransaction = $sepaTransaction;

    return $this;
  }

  /**
   * Get sepaTransaction.
   *
   * @return SepaTransaction|null
   */
  public function getSepaTransaction():?SepaBulkTransaction
  {
    return $this->sepaTransaction;
  }

  /**
   * Set notificationEmail.
   *
   * @param null|SentEmail $notificationEmail
   *
   * @return Invoice
   */
  public function setNotificationEmail(?SentEmail $notificationEmail):Invoice
  {
    if ($notificationEmail !== null) {
      $notificationEmail->setInvoice($this); // we are the owner ...
    }
    $this->notificationEmail = $notificationEmail;

    return $this;
  }

  /**
   * Get notificationEmail.
   *
   * @return null|SentEmail
   */
  public function getNotificationEmail():?SentEmail
  {
    return $this->notificationEmail;
  }

  /**
   * Set balanceDocumentsFolder.
   *
   * @param DatabaseStorageFolder $balanceDocumentsFolder
   *
   * @return InvoiceItem
   */
  public function setBalanceDocumentsFolder(?DatabaseStorageFolder $balanceDocumentsFolder):Invoice
  {
    if (!empty($this->balanceDocumentsFolder)) {
      /** @var InvoiceItem $part */
      foreach ($this->invoiceItems as $part) {
        if ($part->getBalanceDocumentsFolder() == $this->balanceDocumentsFolder) {
          $part->setBalanceDocumentsFolder(null);
        }
      }
      if (!empty($this->writteninvoice)) {
        $fileName = $this->getPaymentRecordFileName($this, $this->writteninvoice->getExtension());

        $this->balanceDocumentsFolder->removeDocument($this->writteninvoice->getFile(), $fileName);
      }
    }

    $this->balanceDocumentsFolder = $balanceDocumentsFolder;

    if (!empty($this->balanceDocumentsFolder)) {
      if (!empty($this->writteninvoice)) {
        $fileName = $this->getPaymentRecordFileName($this, $this->writteninvoice->getExtension());
        $this->balanceDocumentsFolder->addDocument($this->writteninvoice->getFile(), $fileName);
      }
      /** @var InvoiceItem $part */
      foreach ($this->invoiceItems as $part) {
        if (empty($part->getBalanceDocumentsFolder())) {
          $part->setBalanceDocumentsFolder($this->balanceDocumentsFolder);
        }
      }
    }

    return $this;
  }

  /**
   * Get balanceDocumentsFolder.
   *
   * @return ?DatabaseStorageFolder
   */
  public function getBalanceDocumentsFolder():?DatabaseStorageFolder
  {
    return $this->balanceDocumentsFolder;
  }

  /**
   * @return null|DatabaseStorageFile
   */
  public function getWrittenInvoice():?DatabaseStorageFile
  {
    return $this->writtenInvoice ?? null;
  }

  /**
   * @param null|DatabaseStorageFile $writtenInvoice
   *
   * @return Invoice
   */
  public function setWrittenInvoice(?DatabaseStorageFile $writtenInvoice):Invoice
  {
    $this->writtenInvoice = $writtenInvoice;

    return $this;
  }

  /**
   * @return null|TaxationStatutorySource
   */
  public function getTaxationStatutorySource():?TaxationStatutorySource
  {
    return $this->taxationStatutorySource ?? null;
  }

  /**
   * @param TaxationStatutorySource $TaxationStatutorySource
   *
   * @return TaxExemptionNotice
   */
  public function setTaxationStatutorySource(TaxationStatutorySource $taxationStatutorySource):Invoice
  {
    if ($taxationStatutorySource->getTaxType() != Types\EnumTaxType::SALES) {
      throw new InvalidArgumentException(
        'The tax-type has to be "'
        . Types\EnumTaxType::SALES
        . '", not "'
        . $taxationStatutorySource->getTaxType()
        . '".',
      );
    }
    $this->taxationStatutorySource = $taxationStatutorySource;

    return $this;
  }

  /**
   * Update the stored payment-subject by calling
   * Invoice::generateSubject().
   *
   * @param null|Closure $transliterate See generateSubject().
   *
   * @return Invoice
   */
  public function updateSubject(?Closure $transliterate = null):Invoice
  {
    $this->subject = $this->generateSubject($transliterate);
    return $this;
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    return $this->toArray();
  }

  /**
   * Generate a suitable invoice number from the project and possibly the
   * supporting documents folder sequence number.
   *
   * @return string
   */
  public function generateInvoiceNumber():string
  {
    if ($this->balanceDocumentsFolder) {
      $invoiceNumber = $this->balanceDocumentsFolder->getName();
    } else {
      $invoiceNumber = $this->project->getName();
      if ($this->project->getType() == ProjectType::PERMANENT) {
        $invoiceNumber .= self::INVOICE_NUMBER_SEPARATOR . (new DateTimeImmutable)->format('Y');
      }
      $invoiceNumber .= self::INVOICE_NUMBER_SEPARATOR . 'XXX';
    }

    return $invoiceNumber;
  }

  /**
   * Return a "usage" count to tag the invoice undeleteable after it has been
   * issued. It can be revoked (soft deleted) then but it will stay in the
   * database.
   *
   * @return int
   */
  public function usage():int
  {
    $usageCount = 0;
    if (!empty($this->notificationEmail)) {
      ++$usageCount;
    }
    if (!empty($this->writtenInvoice)) {
      ++$usageCount;
    }
    if (!empty($this->sepaTransaction) && $this->sepaTransaction->getSubmitDate() !== null) {
      // has been used by a debit-note
      ++$usageCount;
    }
    return $usageCount;
  }

    /** {@inheritdoc} */
  #[ORM\PreFlush]
  public function preFlush(Event\PreFlushEventArgs $event)
  {
  }

  /** {@inheritdoc} */
  #[ORM\PreUpdate]
  public function preUpate(Event\PreUpdateEventArgs $event)
  {
    if ($this->inUse()) {
      // forbid changing the invoice number if the invoice has already been sent out or attached
      if ($event->hasChangedField('invoiceNumber')) {
        $event->setNewValue('invoiceNumber', $event->getOldValue('invoiceNumber'));
      }
    }
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return 'invoice of ' . $this->amount . ' € due at ' . $this->dueDate->format('Y-m-d') . ' by ' . $this->debitor->getPublicName(true);
  }
}

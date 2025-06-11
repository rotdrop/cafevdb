<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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
use UnexpectedValueException;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Common\Util;

/**
 * Invoices collects a couple of InvoiceItems of the same Musician.
 */
#[ORM\Table(name: 'Invoices')]
#[ORM\UniqueConstraint(columns: ['notification_email_id'])]
#[ORM\UniqueConstraint(columns: ['invoice_number'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InvoicesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Invoice implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait; // filename of supporting document.

  /**
   * @var int
   */
  #[ORM\Column(type: 'integer', nullable: false)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'IDENTITY')]
  private $id;

  /**
   * @var Musician
   *
   * This will -- must be -- a member of the executive borad. But the plan is
   * to tie address-fields to convenience methods of the Musician entity,
   * so better use it here.
   */
  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'originatedInvoices')]
  private Musician $originator;

  /**
   * @var Musician
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'invoices', fetch: 'EXTRA_LAZY')]
  private $debitor;

  /**
   * @var float
   *
   * The total amount for the bank transaction. This must equal the
   * sum of the self::$invoiceItems collection.
   *
   * @todo If this is always the sum and thus can be computed, why then this
   * field?
   */
  #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: false, options: ['default' => '0.00'])]
  private $amount = '0.00';

  /**
   * @var \DateTimeImmutable|null
   */
  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private $dueDate;

  /**
   * @var \DateTimeImmutable|null
   */
  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private $balancedDate;

  /**
   * @var string
   * Subject of the bank transaction.
   */
  #[ORM\Column(type: 'string', length: 255, nullable: false)]
  private $invoiceNumber;

  /**
   * @var string
   * Subject of the bank transaction.
   */
  #[ORM\Column(type: 'string', length: 1024, nullable: false)]
  private $subject;

  /**
   * @var Collection
   */
  #[ORM\OneToMany(targetEntity: InvoiceItem::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
  private $invoiceItems;

  /**
   * @var SepaBulkTransaction
   *
   * There may be an associated debit-note. If so: this it is.
   */
  #[ORM\ManyToOne(targetEntity: SepaBulkTransaction::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')] // Promote any changes to the sepa transaction.
  #[Gedmo\Timestampable(on: ['update', 'create', 'delete'], timestampField: 'updated')]
  private $sepaTransaction = null;

  /**
   * @var SepaBankAccount
   *
   * The bank account used for this payment.
   */
  #[ORM\JoinColumn(name: 'debitor_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'bank_account_sequence', referencedColumnName: 'sequence', nullable: true)]
  #[ORM\ManyToOne(targetEntity: SepaBankAccount::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private $sepaBankAccount;

  /**
   * @var SepaDebitMandate
   *
   * The debit-mandate used for this payment, if any.
   */
  #[ORM\JoinColumn(name: 'debitor_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'debit_mandate_sequence', referencedColumnName: 'sequence', nullable: true)]
  #[ORM\ManyToOne(targetEntity: SepaDebitMandate::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private $sepaDebitMandate;

  /**
   * @var Project
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'invoices', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
  private $project;

  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false)]
  #[ORM\JoinColumn(name: 'debitor_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\ManyToOne(targetEntity: ProjectParticipant::class, fetch: 'EXTRA_LAZY')]
  private $projectParticipant;

  /**
   * @var DatabaseStorageFolder
   */
  #[ORM\ManyToOne(targetEntity: DatabaseStorageFolder::class, fetch: 'EXTRA_LAZY')]
  private $balanceDocumentsFolder;

  /**
   * @var DatabaseStorageFile
   */
  #[ORM\OneToOne(targetEntity: DatabaseStorageFile::class, cascade: ['all'], orphanRemoval: true)]
  private DatabaseStorageFile $writtenInvoice;

  /**
   * @var SentEmail
   *
   * Pre notification email sent out to the recipients.
   */
  #[ORM\JoinColumn(name: 'notification_email_id', referencedColumnName: 'message_id', nullable: true)]
  #[ORM\OneToOne(targetEntity: SentEmail::class, inversedBy: 'invoice')]
  private $notificationEmail;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->invoiceItems = new ArrayCollection;
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
   * @param float|null $amount
   *
   * @return InvoiceItem
   */
  public function setAmount(?float $amount):Invoice
  {
    $this->amount = $amount;

    return $this;
  }

  /**
   * Get amount.
   *
   * @return float
   */
  public function getAmount():float
  {
    return $this->amount;
  }

  /**
   * Return the sum of the amounts of the individual payments, which
   * should sum up to $this->amount, of course.
   *
   * @return float
   */
  public function sumInvoiceItemsAmount():float
  {
    $totalAmount = 0.0;
    /** @var InvoiceItem $invoiceItem */
    foreach ($this->invoiceItems as $invoiceItem) {
      $totalAmount += $invoiceItem->getAmount();
    }
    return $totalAmount;
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
  public function setProjectParticipant(?ProjectParticipant $projectParticipant):Invoice
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
   * Set notificationMessageId.
   *
   * @param null|string $notificationMessageId
   *
   * @return Invoice
   */
  public function setNotificationMessageId(?string $notificationMessageId):Invoice
  {
    $this->notificationMessageId = $notificationMessageId;

    return $this;
  }

  /**
   * Get notificationMessageId.
   *
   * @return null|string
   */
  public function getNotificationMessageId():?string
  {
    return $this->notificationMessageId;
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
   * Set donationReceipt.
   *
   * @param null|string $donationReceipt
   *
   * @return Invoice
   */
  public function setDonationReceipt(?string $donationReceipt):Invoice
  {
    $this->donationReceipt = $donationReceipt;

    return $this;
  }

  /**
   * Get donationReceipt.
   *
   * @return null|string
   */
  public function getDonationReceipt()
  {
    return $this->donationReceipt;
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
      // if (!empty($this->supportingDocument)) {
      //   $fileName = $this->getPaymentRecordFileName($this, $this->supportingDocument->getExtension());

      //   $this->balanceDocumentsFolder->removeDocument($this->supportingDocument->getFile(), $fileName);
      // }
    }

    $this->balanceDocumentsFolder = $balanceDocumentsFolder;

    if (!empty($this->balanceDocumentsFolder)) {
      // if (!empty($this->supportingDocument)) {
      //   $fileName = $this->getPaymentRecordFileName($this, $this->supportingDocument->getExtension());
      //   $this->balanceDocumentsFolder->addDocument($this->supportingDocument->getFile(), $fileName);
      // }
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
    return $this->writtenInvoice;
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

    /** {@inheritdoc} */
  public function __toString():string
  {
    return 'invoice of ' . $this->amount . ' € due at ' . $this->dueDate->format('Y-m-d') . ' by ' . $this->debitor->getPublicName(true);
  }
}

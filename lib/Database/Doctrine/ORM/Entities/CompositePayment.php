<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
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
 * CompositePayments collect a couple of ProjectPayments of the same
 * Musician. In GnuCash this would be a "split transactions". The transaction
 * parts are ProjectPayment entities. Composite-payments may contain payments
 * for different projects.
 *
 * @ORM\Table(
 *    name="CompositePayments",
 *    uniqueConstraints={
 *      @ORM\UniqueConstraint(columns={"notification_message_id"})
 *    }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\CompositePaymentsRepository")
 * @ORM\EntityListeners({"\OCA\CAFEVDB\Listener\CompositePaymentEntityListener"})
 * @ORM\HasLifecycleCallbacks
 */
class CompositePayment implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait; // filename of supporting document.

  const SUBJECT_PREFIX_LIMIT = 16;
  const SUBJECT_PREFIX_SEPARATOR = ' / ';
  const SUBJECT_GROUP_SEPARATOR = ' / ';
  const SUBJECT_ITEM_SEPARATOR = ', ';
  const SUBJECT_OPTION_SEPARATOR = ': ';

  const SUBJECT_FIELD_LENGTH = 1024;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var float
   *
   * The total amount for the bank transaction. This must equal the
   * sum of the self:$projectPayments collection.
   *
   * @todo If this is always the sum and thus can be computed, why then this
   * field?
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $amount = '0.00';

  /**
   * @var \DateTimeImmutable|null
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $dateOfReceipt;

  /**
   * @var string
   * Subject of the bank transaction.
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $subject;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="compositePayment", cascade={"persist","remove"}, fetch="EXTRA_LAZY")
   */
  private $projectPayments;

  /**
   * @var SepaBulkTransaction
   *
   * @ORM\ManyToOne(targetEntity="SepaBulkTransaction", inversedBy="payments", fetch="EXTRA_LAZY")
   *
   * Promote any changes to the sepa transaction.
   * @Gedmo\Timestampable(on={"update","create","delete"}, timestampField="updated")
   */
  private $sepaTransaction = null;

  /**
   * @var SepaBankAccount
   *
   * The bank account used for this payment.
   *
   * @ORM\ManyToOne(targetEntity="SepaBankAccount", inversedBy="payments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="bank_account_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $sepaBankAccount;

  /**
   * @var SepaDebitMandate
   *
   * The debit-mandate used for this payment, if any.
   *
   * @ORM\ManyToOne(targetEntity="SepaDebitMandate",
   *                inversedBy="payments",
   *                fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="debit_mandate_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $sepaDebitMandate;

  /**
   * @var string
   *
   * This is the unique message id from the email sent to the payees. However,
   * the connectivity between the sent-emails table and the CompositePayments
   * table unfortunately is as of yet broken, so we keep stray notification
   * message ids as is and add a new association which is filled with the
   * existing entities in a migration step.
   *
   * @ORM\Column(type="string", length=512, nullable=true)
   *
   * @todo Check why this is not a relation to the SentEmail entity.
   */
  private $notificationMessageId;

  /**
   * @var SentEmail
   *
   * Pre notification email sent out to the recipients.
   *
   * @ORM\OneToOne(targetEntity="SentEmail", inversedBy="compositePayment")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="pre_notification_message_id", referencedColumnName="message_id", nullable=true),
   * )
   */
  private $preNotificationEmail;

  /**
   * @var Project
   *
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="compositePayments", cascade={"persist"}, fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(nullable=false)
   */
  private $project;

  /**
   * @var Musician
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="payments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(nullable=false)
   */
  private $musician;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectParticipant", inversedBy="payments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id", nullable=false),
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false)
   * )
   */
  private $projectParticipant;

  /**
   * @var DatabaseStorageFile
   *
   * Optional. In case an additional overview document needs to be added in
   * addition to the individual supporting documents of the project payments.
   *
   * @ORM\OneToOne(targetEntity="DatabaseStorageFile", fetch="EXTRA_LAZY", cascade={"all"}, orphanRemoval=true)
   *
   * @todo Support more than one supporting document.
   */
  private $supportingDocument;

  /**
   * @var DatabaseStorageFolder
   *
   * @ORM\ManyToOne(targetEntity="DatabaseStorageFolder", fetch="EXTRA_LAZY")
   */
  private $balanceDocumentsFolder;

  /**
   * @var DonationReceipt
   *
   * @ORM\OneToOne(targetEntity="DonationReceipt", mappedBy="donation")
   */
  private $donationReceipt;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->projectPayments = new ArrayCollection;
    $this->balanceDocumentsFolders = new ArrayCollection;
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
   * Set projectPayments.
   *
   * @param Collection $projectPayments
   *
   * @return CompositePayment
   */
  public function setProjectPayments(Collection $projectPayments):CompositePayment
  {
    $this->projectPayments = $projectPayments;

    return $this;
  }

  /**
   * Get projectPayments.
   *
   * @return Collection
   */
  public function getProjectPayments():Collection
  {
    return $this->projectPayments;
  }

  /**
   * Set amount.
   *
   * @param float|null $amount
   *
   * @return ProjectPayment
   */
  public function setAmount(?float $amount):CompositePayment
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
  public function sumPaymentsAmount():float
  {
    $totalAmount = 0.0;
    /** @var ProjectPayment $payment */
    foreach ($this->payments as $payment) {
      $totalAmount += $payment->getAmount();
    }
    return $totalAmount;
  }

  /**
   * Set musician.
   *
   * @param null|int|Musician $musician
   *
   * @return CompositePayment
   */
  public function setMusician($musician):CompositePayment
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician():?Musician
  {
    return $this->musician;
  }

  /**
   * Set project.
   *
   * @param null|int|Project $project
   *
   * @return CompositePayment
   */
  public function setProject($project):CompositePayment
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
   * @return CompositePayment
   */
  public function setProjectParticipant(?ProjectParticipant $projectParticipant):CompositePayment
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
   * Set dateOfReceipt.
   *
   * @param \DateTime|null $dateOfReceipt
   *
   * @return CompositePayment
   */
  public function setDateOfReceipt($dateOfReceipt = null):CompositePayment
  {
    $this->dateOfReceipt = self::convertToDateTime($dateOfReceipt);

    return $this;
  }

  /**
   * Get dateOfReceipt.
   *
   * @return \DateTime|null
   */
  public function getDateOfReceipt():?DateTimeInterface
  {
    return $this->dateOfReceipt;
  }

  /**
   * Set subject.
   *
   * @param null|string $subject
   *
   * @return CompositePayment
   */
  public function setSubject(?string $subject):CompositePayment
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
   * Set debitNote.
   *
   * @param SepaDebitNote|null $debitNote
   *
   * @return CompositePayment
   */
  public function setDebitNote($debitNote):CompositePayment
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
   * @return CompositePayment
   */
  public function setSepaBankAccount(?SepaBankAccount $sepaBankAccount):CompositePayment
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
   * @return CompositePayment
   */
  public function setSepaDebitMandate(?SepaDebitMandate $sepaDebitMandate):CompositePayment
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
   * @return CompositePayment
   */
  public function setSepaTransaction(?SepaBulkTransaction $sepaTransaction):CompositePayment
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
   * @return CompositePayment
   */
  public function setNotificationMessageId(?string $notificationMessageId):CompositePayment
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
   * Set preNotificationEmail.
   *
   * @param null|SentEmail $preNotificationEmail
   *
   * @return CompositePayment
   */
  public function setPreNotificationEmail(?SentEmail $preNotificationEmail):CompositePayment
  {
    if ($preNotificationEmail !== null) {
      if (empty($this->notificationMessageId)) {
        $this->notificationMessageId = $preNotificationEmail->getMessageId();
      } elseif ($this->notificationMessageId != $preNotificationEmail->getMessageId()) {
        throw new UnexpectedValueException(
          'Legacy notification message id "' . $this->notificationMessageId . '" '
          . 'differs from pre-notification email "' . $preNotificationEmail->getSubject() . '" '
          . 'with message-id "' . $preNotificationEmail->getMessageId() . '".'
        );
      }
      $preNotificationEmail->setCompositePayment($this); // we are the owner ...
    }
    $this->preNotificationEmail = $preNotificationEmail;

    return $this;
  }

  /**
   * Get preNotificationEmail.
   *
   * @return null|SentEmail
   */
  public function getPreNotificationEmail():?SentEmail
  {
    return $this->preNotificationEmail;
  }

  /**
   * Set donationReceipt.
   *
   * @param null|string $donationReceipt
   *
   * @return CompositePayment
   */
  public function setDonationReceipt(?string $donationReceipt):CompositePayment
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
   * Set supportingDocument.
   *
   * @param null|DatabaseStorageFile $supportingDocument
   *
   * @return CompositePayment
   */
  public function setSupportingDocument(?DatabaseStorageFile $supportingDocument):CompositePayment
  {
    if (!empty($this->balanceDocumentsFolder) && !empty($this->supportingDocument)) {
      $fileName = $this->getPaymentRecordFileName($this, $this->supportingDocument->getExtension());
      $this->balanceDocumentsFolder->removeDocument($this->supportingDocument->getFile(), $fileName);
    }

    $this->supportingDocument = $supportingDocument;

    if (!empty($this->balanceDocumentsFolder) && !empty($this->supportingDocument)) {
      $fileName = $this->getPaymentRecordFileName($this, $this->supportingDocument->getExtension());
      $this->balanceDocumentsFolder->addDocument($this->supportingDocument->getFile(), $fileName);
    }

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
   * Set balanceDocumentsFolder.
   *
   * @param DatabaseStorageFolder $balanceDocumentsFolder
   *
   * @return ProjectPayment
   */
  public function setBalanceDocumentsFolder(?DatabaseStorageFolder $balanceDocumentsFolder):CompositePayment
  {
    if (!empty($this->balanceDocumentsFolder)) {
      /** @var ProjectPayment $part */
      foreach ($this->projectPayments as $part) {
        if ($part->getBalanceDocumentsFolder() == $this->balanceDocumentsFolder) {
          $part->setBalanceDocumentsFolder(null);
        }
      }
      if (!empty($this->supportingDocument)) {
        $fileName = $this->getPaymentRecordFileName($this, $this->supportingDocument->getExtension());

        $this->balanceDocumentsFolder->removeDocument($this->supportingDocument->getFile(), $fileName);
      }
    }

    $this->balanceDocumentsFolder = $balanceDocumentsFolder;

    if (!empty($this->balanceDocumentsFolder)) {
      if (!empty($this->supportingDocument)) {
        $fileName = $this->getPaymentRecordFileName($this, $this->supportingDocument->getExtension());
        $this->balanceDocumentsFolder->addDocument($this->supportingDocument->getFile(), $fileName);
      }

      /** @var ProjectPayment $part */
      foreach ($this->projectPayments as $part) {
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
   * @return Count the number of donations contained in this composite.
   */
  public function countDonations():int
  {
    return $this->projectPayments->reduce(
      fn(bool $accumulator, ProjectPayment $payment) => $accumulator + (int)$payment->getIsDonation(),
      0,
    );
  }

  /**
   * @return float The sum of all contained donation parts.
   */
  public function getDonationAmount():float
  {
    return $this->projectPayments->reduce(
      fn(float $accumulator, ProjectPayment $payment) => $accumulator + (int)$payment->getIsDonation() * $payment->getAmount(),
      0.0,
    );
  }

  /**
   * @return float The sum of all contained non-donation parts.
   */
  public function getNonDonationAmount():float
  {
    return $this->projectPayments->reduce(
      fn(float $accumulator, ProjectPayment $payment) => $accumulator + (int)(!$payment->getIsDonation()) * $payment->getAmount(),
      0.0,
    );
  }

  /**
   * @return Collection The ProjectPayment entities which are donations.
   */
  public function getDonations():Collection
  {
    return $this->projectPayments->filter(fn(ProjectPayment $payment) => $payment->getIsDonation());
  }

  /**
   * @return Collection The ProjectPayment entities which aren't donations.
   */
  public function getNonDonations():Collection
  {
    return $this->projectPayments->filter(fn(ProjectPayment $payment) => !$payment->getIsDonation());
  }

  /**
   * Automatic subject generation from receivables and linked
   * DatabaseStorageFolder's. The routine applies the supplied
   * transliteration routine such that the result only contains valid
   * characters for a potential bank transaction data-set. It also tries to
   * group and compactify payments which carry the same prefix assuming the
   * particluar subjects result from
   * ProjectParticipantFieldDatum::paymentReference().
   *
   * @param null|Closure $transliterate A transliteration routine with the
   * signature function(string $x):string. It defaults to the identity if not
   * specified.
   *
   * @return string
   */
  public function generateSubject(?Closure $transliterate = null):string
  {
    if (empty($transliterate)) {
      $transliterate = fn(string $x) => $x;
    }

    $projectName = $this->project->getName();
    $projectYear = $this->project->getYear();
    if (substr($projectName, -4) == (string)$projectYear) {
      $projectName = substr($projectName, 0, -4);
      $yearSeparator = '';
    } else {
      $yearSeparator = '-';
    }

    $projectName = Util::dashesToCamelCase(
      $transliterate(strtolower(Util::camelCaseToDashes($projectName, separator: ' '))),
      capitalizeFirstCharacter: true,
      dashes: ' ',
    );

    // collect the DatabaseStorageFolder's
    $balanceDocuments = [];
    if (!empty($this->balanceDocumentsFolder)) {
      $folderName = $this->balanceDocumentsFolder->getName();
      if (!empty($folderName)) {
        $balanceDocuments[$folderName] = true;
      }
    }
    /** @var ProjectPayment $projectPayment */
    foreach ($this->projectPayments as $projectPayment) {
      $documentFolder = $projectPayment->getBalanceDocumentsFolder();
      $folderName = !empty($documentFolder) ? $documentFolder->getName() : null;
      if (!empty($folderName)) {
        $balanceDocuments[$folderName] = true;
      }
    }

    $yearSequences = [];
    foreach (array_keys($balanceDocuments) as $folderName) {
      $sequence = substr($folderName, -3);
      $year = substr($folderName, -8, 4);
      $yearSequences[$year] = $yearSequences[$year] ?? [];
      $yearSequences[$year][] = $sequence;
    }
    ksort($yearSequences);

    $balanceSequences = [];
    if (count($yearSequences) == 1) {
      $projectYear = array_keys($yearSequences)[0];
      $projectName .= $yearSeparator . ($projectYear % 100);
      $sequences = $yearSequences[$projectYear];
      sort($sequences, SORT_NUMERIC);
      $len = (int)log10(end($sequences)) + 1;
      foreach ($sequences as $sequence) {
        $balanceSequences[] = sprintf('%0' . $len . 'd', $sequence);
      }
    } else {
      foreach ($yearSequences as $year => $sequences) {
        sort($sequences, SORT_NUMERIC);
        $len = (int)log10(end($sequences)) + 1;
        foreach ($sequences as $sequence) {
          $balanceSequences[] = ($year % 100) . '-' . sprintf('%0' . $len . 'd', $sequence);
        }
      }
    }

    $subjectPrefix = $projectName;
    if (count($balanceSequences) >= 1) {
      $subjectPrefix .= '-' . implode(trim(self::SUBJECT_PREFIX_SEPARATOR), $balanceSequences);
    }

    $subjects = $this->projectPayments
      ->map(fn(ProjectPayment $payment) => $transliterate($payment->getSubject()))
      ->toArray();
    natsort($subjects);

    // try to compactify the composite subject by attempting to group similar subjects
    $oldPrefix = false;
    $postfix = [];
    $purpose = '';
    foreach ($subjects as $subject) {
      $parts = array_map(
        fn($part) => Util::dashesToCamelCase(
          preg_replace('/([^0-9]|^)[0-9]{2}([0-9]{2})([^0-9]|$)/', '\1\2\3', $part),
          capitalizeFirstCharacter: true,
          dashes: ' -_',
        ),
        Util::explode(trim(self::SUBJECT_OPTION_SEPARATOR), $subject, Util::TRIM|Util::OMIT_EMPTY_FIELDS|Util::ESCAPED),
      );
      $prefix = $parts[0];
      if (count($parts) < 2 || $oldPrefix != $prefix) {
        $purpose .= implode(self::SUBJECT_ITEM_SEPARATOR, $postfix);
        if (strlen($purpose) > 0) {
          $purpose .= self::SUBJECT_GROUP_SEPARATOR;
        }
        $purpose .= $prefix;
        if (count($parts) >= 2) {
          $purpose .= self::SUBJECT_OPTION_SEPARATOR;
          $oldPrefix = $prefix;
        } else {
          $oldPrefix = false;
        }
        $postfix = [];
      }
      if (count($parts) >= 2) {
        $postfix = array_merge($postfix, array_splice($parts, 1));
      }
    }
    if (!empty($postfix)) {
      $purpose .= implode(self::SUBJECT_ITEM_SEPARATOR, $postfix);
    }
    $purpose = $transliterate(Util::unescapeDelimiter($purpose, trim(self::SUBJECT_OPTION_SEPARATOR)));

    $subject = $subjectPrefix . self::SUBJECT_PREFIX_SEPARATOR . $purpose;

    if (strlen($subject) > self::SUBJECT_FIELD_LENGTH) {
      $subject = substr(
        Util::shortenCamelCaseString($subject, self::SUBJECT_FIELD_LENGTH, minLen: 4),
        0,
        self::SUBJECT_FIELD_LENGTH,
      );
    }

    return $subject;
  }

  /**
   * Update the stored payment-subject by calling
   * CompositePayment::generateSubject().
   *
   * @param null|Closure $transliterate See generateSubject().
   *
   * @return CompositePayment
   */
  public function updateSubject(?Closure $transliterate = null):CompositePayment
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
    return 'payment of ' . $this->amount . ' € at ' . $this->dateOfReceipt->format('Y-m-d') . ' by ' . $this->musician->getPublicName(true);
  }
}

<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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

use \Closure;

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
 * @ORM\Table(name="CompositePayments")
 *    uniqueConstraints={@ORM\UniqueConstraint(columns={"notification_message_id"})}
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\CompositePaymentsRepository")
 *
 * @ORM\HasLifecycleCallbacks
 */
class CompositePayment implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait; // filename of supporting document.

  const SUBJECT_PREFIX_LIMIT = 16;
  const SUBJECT_PREFIX_SEPARATOR = ' / ';
  const SUBJECT_GROUP_SEPARATOR = ' / ';
  const SUBJECT_ITEM_SEPARATOR = ', ';
  const SUBJECT_OPTION_SEPARATOR = ': ';

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
   * This is the unique message id from the email sent to the payees.
   *
   * @ORM\Column(type="string", length=512, nullable=true)
   */
  private $notificationMessageId;

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
  public function getDateOfReceipt()
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

    // collect the DatabaseStorageFolder's
    $balanceDocuments = [ $this->balanceDocumentsFolder ];
    /** @var ProjectPayment $projectPayment */
    foreach ($this->projectPayments as $projectPayment) {
      $balanceDocuments[] = $projectPayment->getBalanceDocumentsFolder();
    }
    $balanceSequences =  array_map(
      fn(DatabaseStorageFolder $document) => substr($document->getName() ?? '000', -3),
      array_filter($balanceDocuments),
    );
    sort($balanceSequences, SORT_NUMERIC);

    $projectName = $this->project->getName();
    $projectYear = $this->project->getYear();
    if (substr($projectName, -4) == (string)$projectYear) {
      $projectName = substr($projectName, 0, -4) . ($projectYear % 100);
    }

    $projectName = Util::dashesToCamelCase(
      $transliterate(strtolower(Util::camelCaseToDashes($projectName, separator: ' '))),
      capitalizeFirstCharacter: true,
      dashes: ' ',
    );

    $subjectPrefix = Util::shortenCamelCaseString($projectName, self::SUBJECT_PREFIX_LIMIT, minLen: 2);

    if (count($balanceSequences) >= 1) {
      $sequenceSuffix = sprintf('%03d', array_shift($balanceSequences));
      foreach ($balanceSequences as $sequence) {
        $sequenceSuffix .= trim(self::SUBJECT_PREFIX_SEPARATOR) . $sequence; // without padding
      }
      $subjectPrefix .= '-' . $sequenceSuffix;
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
      $parts = Util::explode(trim(self::SUBJECT_OPTION_SEPARATOR), $subject, Util::TRIM|Util::OMIT_EMPTY_FIELDS|Util::ESCAPED);
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

  /**
   * @var null|array
   *
   * The array of changed field values.
   */
  private $preUpdateValue = [];

  /**
   * {@inheritdoc}
   *
   * @ORM\PreUpdate
   */
  public function preUpdate(Event\PreUpdateEventArgs $event)
  {
    $field = 'notificationMessageId';
    if ($event->hasChangedField($field)) {
      /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
      // $entityManager = EntityManager::getDecorator($event->getEntityManager());
      $oldValue = $event->getOldValue($field);
      // $entityManager->dispatchEvent(new Events\PreChangeUserIdSlug($entityManager, $this, $oldValue, $event->getNewValue($field)));
      $this->preUpdateValue[$field] = $oldValue;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @ORM\PostUpdate
   */
  public function postUpdate(Event\LifecycleEventArgs $event)
  {
    $field = 'notificationMessageId';
    if (array_key_exists($field, $this->preUpdateValue)) {
      /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
      $entityManager = EntityManager::getDecorator($event->getEntityManager());
      $entityManager->dispatchEvent(new Events\PostChangeCompositePaymentNotificationMessageId($entityManager, $this, $this->preUpdateValue[$field]));
      unset($this->preUpdateValue[$field]);
    }
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    return $this->toArray();
  }
}

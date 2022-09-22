<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Common\Util;

/**
 * CompositePayments collect a couple of ProjectPayments of the same
 * Musician. In GnuCash this would be a "split transactions". The transaction
 * parts are ProjectPayment entities. Composite-payments may contain payments
 * for different projects.
 *
 * @ORM\Table(name="CompositePayments")
 *    uniqueConstraints={@ORM\UniqueConstraint(columns={"notification_message_id"})}
 * @ORM\Entity
 */
class CompositePayment implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\TimestampableEntity;

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
   * @Gedmo\Timestampable(on={"update","create","delete"}, timestampField="sepaTransactionDataChanged")
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
   */
  private $project;

  /**
   * @var Musician
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="payments", fetch="EXTRA_LAZY")
   * @Gedmo\Timestampable(on={"update","change","create","delete"}, field="supportingDocument", timestampField="paymentsChanged")
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
   * @var EncryptedFile
   *
   * Optional. In case an additional overview document needs to be added in
   * addition to the individual supporting documents of the project payments.
   *
   * @ORM\OneToOne(targetEntity="EncryptedFile", fetch="EXTRA_LAZY", cascade={"all"})
   *
   * @todo Support more than one supporting document.
   */
  private $supportingDocument;

  /**
   * @var ProjectBalanceSupportingDocument
   *
   * @ORM\ManyToOne(targetEntity="ProjectBalanceSupportingDocument", inversedBy="compositePayments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *   @ORM\JoinColumn(name="balance_document_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $projectBalanceSupportingDocument;

  public function __construct() {
    $this->arrayCTOR();
    $this->projectPayments = new ArrayCollection;
    $this->projectBalanceSupportingDocuments = new ArrayCollection;
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
   * @param int $musician
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
   * @param int $project
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
   * @param string $notificationMessageId
   *
   * @return CompositePayment
   */
  public function setNotificationMessageId($notificationMessageId):CompositePayment
  {
    $this->notificationMessageId = $notificationMessageId;

    return $this;
  }

  /**
   * Get notificationMessageId.
   *
   * @return string
   */
  public function getNotificationMessageId()
  {
    return $this->notificationMessageId;
  }

  /**
   * Set supportingDocument.
   *
   * @param null|EncryptedFile $supportingDocument
   *
   * @return CompositePayment
   */
  public function setSupportingDocument(?EncryptedFile $supportingDocument):CompositePayment
  {
    if (!empty($this->projectBalanceSupportingDocument) && !empty($this->supportingDocument)) {
      $this->projectBalanceSupportingDocument->removeDocument($this->supportingDocument);
    }

    if (!empty($this->supportingDocument)) {
      $this->supportingDocument->unlink();
    }

    $this->supportingDocument = $supportingDocument;

    if (!empty($this->supportingDocument)) {
      $this->supportingDocument->link();
    }

    if (!empty($this->projectBalanceSupportingDocument) && !empty($this->supportingDocument)) {
      $this->projectBalanceSupportingDocument->addDocument($this->supportingDocument);
    }

    return $this;
  }

  /**
   * Get supportingDocument.
   *
   * @return null|EncryptedFile
   */
  public function getSupportingDocument():?EncryptedFile
  {
    return $this->supportingDocument;
  }

  /**
   * Set projectBalanceSupportingDocument.
   *
   * @param ProjectBalanceSupportingDocument $projectBalanceSupportingDocument
   *
   * @return ProjectPayment
   */
  public function setProjectBalanceSupportingDocument(?ProjectBalanceSupportingDocument $projectBalanceSupportingDocument):CompositePayment
  {
    if (!empty($this->projectBalanceSupportingDocument) && !empty($this->supportingDocument)) {
      $this->projectBalanceSupportingDocument->removeDocument($this->supportingDocument);
    }

    $this->projectBalanceSupportingDocument = $projectBalanceSupportingDocument;

    if (!empty($this->projectBalanceSupportingDocument) && !empty($this->supportingDocument)) {
      $this->projectBalanceSupportingDocument->addDocument($this->supportingDocument);
    }

    return $this;
  }

  /**
   * Get projectBalanceSupportingDocument.
   *
   * @return ?ProjectBalanceSupportingDocument
   */
  public function getProjectBalanceSupportingDocument():?ProjectBalanceSupportingDocument
  {
    return $this->projectBalanceSupportingDocument;
  }

  /**
   * Automatic subject generation from receivables and linked
   * ProjectBalanceSupportingDocument's. The routine applies the supplied
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

    // collect the ProjectBalanceSupportingDocument's
    $balanceDocuments = [ $this->projectBalanceSupportingDocument ];
    /** @var ProjectPayment $projectPayment */
    foreach ($this->projectPayments as $projectPayment) {
      $balanceDocuments[] = $projectPayment->getProjectBalanceSupportingDocument();
    }
    $balanceSequences =  array_map(
      fn(ProjectBalanceSupportingDocument $document) => $document->getSequence(),
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

  /** \JsonSerializable interface */
  public function jsonSerialize():array
  {
    return $this->toArray();
  }
}

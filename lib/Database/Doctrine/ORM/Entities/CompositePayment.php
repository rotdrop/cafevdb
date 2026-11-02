<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2026 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\CamelCaseString;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM as AppORM;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Service\Finance\SepaTransliterator;
use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Common\RationalNumber;
use OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Doctrine\DBAL\Types\DecimalRationalMonetaryType as MonetaryDatabaseType;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTimeImmutable;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * CompositePayments collect a couple of ProjectPayments of the same
 * ProjectParticipant. In GnuCash this would be a "split transactions". The
 * transaction parts are ProjectPayment entities which is just a "named joint
 * table" in order to avoid ManyToMany and add some stuff.
 *
 * So: there are no other per-person payments, each payment thus is tied to a
 * person and a project. For "unprojected payments" either the club members
 * project has to be used or the executive board project. External parties
 * also have first to be added to a project as "associates", then they are
 * also "Musician" entities (thow the name is misleading) and then can be used
 * as ordinary participants for payments.
 */
#[ORM\Table(name: DatabaseTables::COMPOSITE_PAYMENTS_TABLE)]
#[ORM\UniqueConstraint(columns: ['pre_notification_message_id'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\CompositePaymentsRepository::class)]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\CompositePaymentEntityListener::class])]
#[ORM\HasLifecycleCallbacks]
class CompositePayment implements \ArrayAccess, \JsonSerializable
{
  use AppORM\Traits\ArrayTrait;
  use AppORM\Traits\AutoIncrementTrait;
  use AppORM\Traits\DateTimeTrait;
  use AppORM\Traits\FactoryTrait;
  use AppORM\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait; // filename of supporting document.

  const SUBJECT_PREFIX_LIMIT = 16;
  const SUBJECT_PREFIX_SEPARATOR = ' / ';
  const SUBJECT_GROUP_SEPARATOR = ' / ';
  const SUBJECT_ITEM_SEPARATOR = ', ';
  const SUBJECT_OPTION_SEPARATOR = ': ';

  const SUBJECT_FIELD_LENGTH = 1024;

  /**
   * The total amount for the bank transaction. This must equal the
   * sum of the self:$projectPayments collection.
   *
   * @todo If this is always the sum and thus can be computed, why then this
   * field?
   */
  #[ORM\Column(type: MonetaryDatabaseType::NAME, nullable: false, options: ['default' => '0.00'])]
  private MonetaryNumberType $amount;

  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private ?DateTimeImmutable $dateOfReceipt = null;

  /**
   * Subject of the bank transaction.
   */
  #[ORM\Column(type: 'string', length: 1024, nullable: false)]
  private string $subject;

  /**
   * @var Collection<ProjectPayment>
   */
  #[ORM\OneToMany(targetEntity: ProjectPayment::class, mappedBy: 'compositePayment', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
  private Collection $projectPayments;

  #[ORM\ManyToOne(targetEntity: SepaBulkTransaction::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')] // Promote any changes to the sepa transaction.
  #[Gedmo\Timestampable(on: ['update', 'create', 'delete'], timestampField: 'updated')]
  private ?SepaBulkTransaction $sepaTransaction = null;

  /**
   * The bank account used for this payment.
   */
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'bank_account_sequence', referencedColumnName: 'sequence', nullable: true)]
  #[ORM\ManyToOne(targetEntity: SepaBankAccount::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private ?SepaBankAccount $sepaBankAccount = null;

  /**
   * The debit-mandate used for this payment, if any.
   */
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'debit_mandate_sequence', referencedColumnName: 'sequence', nullable: true)]
  #[ORM\ManyToOne(targetEntity: SepaDebitMandate::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private ?SepaDebitMandate $sepaDebitMandate = null;

  /**
   * This is the unique message id from the email sent to the payees. However,
   * the connectivity between the sent-emails table and the CompositePayments
   * table unfortunately is as of yet broken, so we keep stray notification
   * message ids as is and add a new association which is filled with the
   * existing entities in a migration step.
   *
   * @todo Check why this is not a relation to the SentEmail entity.
   */
  #[ORM\Column(type: 'string', length: 512, nullable: true)]
  private ?string $notificationMessageId = null;

  /**
   * Pre notification email sent out to the recipients.
   */
  #[ORM\JoinColumn(name: 'pre_notification_message_id', referencedColumnName: 'message_id', nullable: true)]
  #[ORM\OneToOne(targetEntity: SentEmail::class, inversedBy: 'compositePayment')]
  private ?SentEmail $preNotificationEmail = null;

  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'compositePayments', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
  private Project $project;

  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private Musician $musician;

  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false)]
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\ManyToOne(targetEntity: ProjectParticipant::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private ProjectParticipant $projectParticipant;

  /**
   * Optional. In case an additional overview document needs to be added in
   * addition to the individual supporting documents of the project payments.
   *
   * @todo Support more than one supporting document.
   */
  #[ORM\OneToOne(targetEntity: DatabaseStorageFile::class, fetch: 'EXTRA_LAZY', cascade: ['all'], orphanRemoval: true)]
  private ?DatabaseStorageFile $supportingDocument = null;

  #[ORM\ManyToOne(targetEntity: DatabaseStorageFolder::class, fetch: 'EXTRA_LAZY')]
  private ?DatabaseStorageFolder $balanceDocumentsFolder = null;

  /**
   * @var DonationReceipt
   */
  #[ORM\OneToOne(targetEntity: DonationReceipt::class, mappedBy: 'donation')]
  private ?DonationReceipt $donationReceipt = null;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->projectPayments = new ArrayCollection;
    $this->setAmount(0);
  }

  /**
   * Set projectPayments.
   *
   * @param Collection $projectPayments
   *
   * @return CompositePayment
   */
  public function setProjectPayments(Collection $projectPayments): CompositePayment
  {
    $this->projectPayments = $projectPayments;

    return $this;
  }

  /**
   * @param ProjectPayment $projectPayment
   *
   * @return self
   */
  public function addProjectPayment(ProjectPayment $projectPayment): self
  {
    if (!$this->projectPayments->contains($projectPayment)) {
      $this->projectPayments->add($projectPayment);
    }
    if ($projectPayment->getCompositePayment() !== $this) {
      $projectPayment->setCompositePayment($this);
    }

    return $this;
  }

  /**
   * Get projectPayments.
   *
   * @return Collection
   */
  public function getProjectPayments(): Collection
  {
    return $this->projectPayments;
  }

  /**
   * Set amount.
   *
   * @param int|float|string|RationalNumber $amount
   *
   * @return self
   */
  public function setAmount(int|float|string|RationalNumber $amount): self
  {
    $this->amount = MonetaryNumberType::create($amount);

    return $this;
  }

  /**
   * Get amount.
   *
   * @return MonetaryNumberType
   */
  public function getAmount(): MonetaryNumberType
  {
    return $this->amount;
  }

  /**
   * Return the sum of the amounts of the individual payments, which
   * should sum up to $this->amount, of course.
   *
   * @return MonetaryNumberType
   */
  public function sumPaymentsAmount(): MonetaryNumberType
  {
    return $this->projectPayments->reduce(
      fn(MonetaryNumberType $accumulator, ProjectPayment $payment) => $accumulator->add($payment->getAmount()),
      MonetaryNumberType::zero(),
    );
  }

  /**
   * Set musician.
   *
   * @param null|int|Musician $musician
   *
   * @return CompositePayment
   */
  public function setMusician($musician): CompositePayment
  {
    $this->musician = $musician;
    $musician->addPayment($this);

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician(): ?Musician
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
  public function setProject($project): CompositePayment
  {
    $this->project = $project;
    $project->addCompositePayment($this);

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject(): ?Project
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
  public function setProjectParticipant(?ProjectParticipant $projectParticipant): CompositePayment
  {
    $this->projectParticipant = $projectParticipant;
    $this->setProject($projectParticipant->getProject());
    $this->setMusician($projectParticipant->getMusician());

    return $this;
  }

  /**
   * Get projectParticipant.
   *
   * @return ProjectParticipant
   */
  public function getProjectParticipant(): ?ProjectParticipant
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
  public function setDateOfReceipt($dateOfReceipt = null): CompositePayment
  {
    $this->dateOfReceipt = self::convertToDateTime($dateOfReceipt);

    return $this;
  }

  /**
   * Get dateOfReceipt.
   *
   * @return \DateTime|null
   */
  public function getDateOfReceipt(): ?DateTimeInterface
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
  public function setSubject(?string $subject): CompositePayment
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return ?string
   */
  public function getSubject(): ?string
  {
    return $this->subject ?? null;
  }

  /**
   * Set debitNote.
   *
   * @param SepaDebitNote|null $debitNote
   *
   * @return CompositePayment
   */
  public function setDebitNote($debitNote): CompositePayment
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
  public function setSepaBankAccount(?SepaBankAccount $sepaBankAccount): CompositePayment
  {
    $this->sepaBankAccount = $sepaBankAccount;
    $sepaBankAccount->addPayment($this);

    return $this;
  }

  /**
   * Get sepaBankAccount.
   *
   * @return SepaBankAccount|null
   */
  public function getSepaBankAccount(): ?SepaBankAccount
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
  public function setSepaDebitMandate(?SepaDebitMandate $sepaDebitMandate): CompositePayment
  {
    $this->sepaDebitMandate = $sepaDebitMandate;
    $sepaDebitMandate->addPayment($this);

    return $this;
  }

  /**
   * Get sepaDebitMandate.
   *
   * @return SepaDebitMandate|null
   */
  public function getSepaDebitMandate(): ?SepaDebitMandate
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
  public function setSepaTransaction(?SepaBulkTransaction $sepaTransaction): CompositePayment
  {
    $this->sepaTransaction = $sepaTransaction;
    $sepaTransaction->addPayment($this);

    return $this;
  }

  /**
   * Get sepaTransaction.
   *
   * @return SepaTransaction|null
   */
  public function getSepaTransaction(): ?SepaBulkTransaction
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
  public function setNotificationMessageId(?string $notificationMessageId): CompositePayment
  {
    $this->notificationMessageId = $notificationMessageId;

    return $this;
  }

  /**
   * Get notificationMessageId.
   *
   * @return null|string
   */
  public function getNotificationMessageId(): ?string
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
  public function setPreNotificationEmail(?SentEmail $preNotificationEmail): CompositePayment
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
  public function getPreNotificationEmail(): ?SentEmail
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
  public function setDonationReceipt(?string $donationReceipt): CompositePayment
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
  public function setSupportingDocument(?DatabaseStorageFile $supportingDocument): CompositePayment
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
  public function getSupportingDocument(): ?DatabaseStorageFile
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
  public function setBalanceDocumentsFolder(?DatabaseStorageFolder $balanceDocumentsFolder): CompositePayment
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
  public function getBalanceDocumentsFolder(): ?DatabaseStorageFolder
  {
    return $this->balanceDocumentsFolder;
  }

  /**
   * @return Count the number of donations contained in this composite.
   */
  public function countDonations(): int
  {
    return $this->projectPayments->reduce(
      fn(bool $accumulator, ProjectPayment $payment) => $accumulator + (int)$payment->getIsDonation(),
      0,
    );
  }

  /**
   * @return MonetaryNumberType The sum of all contained donation parts.
   */
  public function getDonationAmount():  MonetaryNumberType
  {
    return $this->projectPayments->reduce(
      fn(MonetaryNumberType $accumulator, ProjectPayment $payment)
      =>
      $payment->getIsDonation() ? $accumulator->add($payment->getAmount()) : $accumulator,
      MonetaryNumberType::zero(),
    );
  }

  /**
   * @return MonetaryNumberType The sum of all contained non-donation parts.
   */
  public function getNonDonationAmount():  MonetaryNumberType
  {
    return $this->projectPayments->reduce(
      fn(MonetaryNumberType $accumulator, ProjectPayment $payment)
      =>
      $payment->getIsDonation() ? $accumulator : $accumulator->add($payment->getAmount()),
      MonetaryNumberType::zero(),
    );
  }

  /**
   * @return Collection The ProjectPayment entities which are donations.
   */
  public function getDonations(): Collection
  {
    return $this->projectPayments->filter(fn(ProjectPayment $payment) => $payment->getIsDonation());
  }

  /**
   * @return Collection The ProjectPayment entities which aren't donations.
   */
  public function getNonDonations(): Collection
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
   * @param int $maxLength Maximum length allowed, longer subjects will be
   * "compressed".
   *
   * @param null|Closure $transliterate A transliteration routine with the
   * signature function(string $x): string. It defaults to the identity fn($x)
   * => $x if null.
   *
   * @return array
   */
  public function generateSubject(
    int $maxLength = self::SUBJECT_FIELD_LENGTH,
    ?Closure $transliterate = null,
  ): string {
    $transliterate = $transliterate ?? fn(string $x) => $x;

    $parts = $this->generateSubjectParts();

    $subject = $transliterate($parts['prefix'] .
      self::SUBJECT_PREFIX_SEPARATOR .
      implode(
        self::SUBJECT_GROUP_SEPARATOR,
        array_map(
          function(array $part) {
            return $subject = $part['prefix'] .
              (!empty($part['postfix'])
               ? self::SUBJECT_OPTION_SEPARATOR . implode(self::SUBJECT_ITEM_SEPARATOR, $part['postfix'])
               : '');
          },
          $parts['processed'],
        ),
      ));
    if (strlen($subject) > $maxLength) {
      $camelCaseString = \OCP\Server::get(CamelCaseString::class);
      $subject = substr(
        $camelCaseString->shorten($subject, $maxLength, minLen: 4),
        0,
        $maxLength,
      );
    }
    return $subject;
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
   * @return array
   */
  public function generateSubjectParts(): array
  {
    $projectName = $this->project->getName();
    $projectYear = $this->project->getYear();
    if (substr($projectName, -4) == (string)$projectYear) {
      $projectName = substr($projectName, 0, -4);
      $yearSeparator = '';
    } else {
      $yearSeparator = '-';
    }

    $projectName = CamelCaseString::dashesToCamelCase(
      strtolower(CamelCaseString::camelCaseToDashes($projectName, separator: ' ')),
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
      ->map(fn(ProjectPayment $payment) => $payment->getSubject())
      ->toArray();
    natsort($subjects);

    $purposeArray = [
      'prefix' => $subjectPrefix,
      'originalSubjects' => $subjects,
      'processed' => [],
    ];

    // try to compactify the composite subject by attempting to group similar subjects
    $oldPrefix = false;
    $postfix = [];
    $purpose = '';
    foreach ($subjects as $subject) {
      $parts = array_map(
        function(string $part) {
          // This should remain human-readable, therefore "camel-casing"
          // should only be performed on "real words".
          $part = trim(Util::normalizeSpaces($part));
          $words = explode(' ', $part);
          $result = '';
          $prevIsWord = true;
          // print_r($words);
          foreach ($words as $word) {
            // Difficult, sequences of 4 digits may as well form part of important booking ids.
            // $word = preg_replace('/(?<!\d)\d{2}(\d{2})(?!\d)/u', '\1', $word);

            // Another attempt: DD:DD(:DD) -- treat it as an unimportant
            // time-stamp and remove it altogether.
            // $word = preg_replace('/\s+\d{2}:\d{2}(:\d{2})?/', '', $word);
            $word = preg_replace('/^\\d{2}:\\d{2}(:\\d{2})?$/u', '', $word);
            $isWord = preg_match('/^(?:\p{L}|[-_])+$/u', $word);
            // echo 'WORD "' . $word . '" IS WORD ' . (int)$isWord . ' PREV IS WORD ' . (int)$prevIsWord . PHP_EOL;
            if ($isWord) {
              $word = CamelCaseString::dashesToCamelCase($word, capitalizeFirstCharacter: true, dashes: '-_');
              if (!$prevIsWord) {
                $result .= ' ';
              }
              $prevIsWord = true;
            } elseif ($word === '') {
              // unchanged
            } else {
              $result .= ' ';
              $prevIsWord = false;
            }
            $result .= $word;
          }
          // echo 'RESULT "' . $result . '"' . PHP_EOL;
          return $result;
        },
        Util::explode(
          trim(self::SUBJECT_OPTION_SEPARATOR),
          $subject,
          Util::TRIM|Util::OMIT_EMPTY_FIELDS|Util::ESCAPED,
          limit: 2,
        ),
      );
      // print_r($parts);
      $prefix = $parts[0];
      if (count($parts) < 2 || $oldPrefix != $prefix) {
        if ($oldPrefix !== false) {
          $purposeArray['processed'][] = [
            'prefix' => $oldPrefix,
            'postfix' => array_unique($postfix),
          ];
        }
        $purpose .= implode(self::SUBJECT_ITEM_SEPARATOR, array_unique($postfix));
        if (strlen($purpose) > 0) {
          $purpose .= self::SUBJECT_GROUP_SEPARATOR;
        }
        $purpose .= $prefix;
        if (count($parts) >= 2) {
          $purpose .= self::SUBJECT_OPTION_SEPARATOR;
          $oldPrefix = $prefix;
        } else {
          $purposeArray['processed'][] = [
            'prefix' => $prefix,
            'postfix' => [],
          ];
          $oldPrefix = false;
        }
        $postfix = [];
      }
      if (count($parts) >= 2) {
        $postfix = array_merge($postfix, array_splice($parts, 1));
      }
    }
    if ($oldPrefix !== false) {
      $purposeArray['processed'][] = [
        'prefix' => $oldPrefix,
        'postfix' => array_unique($postfix),
      ];
    }

    return $purposeArray;
  }

  /**
   * Update the stored payment-subject by calling
   * CompositePayment::generateSubject().
   *
   * @param int $maxLength Maximum length allowed, longer subjects will be
   * "compressed".
   *
   * @param ?Closure $transliterate See generateSubject().
   *
   * @return CompositePayment
   */
  public function updateSubject(
    int $maxLength = self::SUBJECT_FIELD_LENGTH,
    ?Closure $transliterate = null,
  ): CompositePayment {
    $this->setSubject($this->generateSubject(maxLength: $maxLength, transliterate: $transliterate));

    return $this;
  }

  /**
   * Return the maximum due-date of all underlying receivables.
   *
   * @return DateTimeInterface
   */
  public function getReceivablesDueDate(): DateTimeInterface
  {
    return max(
      $this->projectPayments->map(
        fn(ProjectPayment $payment) => $payment->getReceivable()->getField()->getDueDate(),
      )->toArray(),
    );
  }

  /** {@inheritdoc} */
  public function jsonSerialize(): array
  {
    return $this->toArray();
  }

    /** {@inheritdoc} */
  public function __toString(): string
  {
    return 'payment of ' . $this->amount . ' € at ' . $this->dateOfReceipt->format('Y-m-d') . ' by ' . $this->musician->getPublicName(true);
  }
}

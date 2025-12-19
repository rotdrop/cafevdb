<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024, 2025 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumSepaTransaction;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTimeImmutable;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Types as DBALTypes;

/**
 * SepaBulkTransaction
 *
 * This actually models a batch collection
 */
#[ORM\Table(name: 'SepaBulkTransactions')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'sepa_transaction', type: DBALTypes::ENUM, enumType: EnumSepaTransaction::class)]
#[ORM\DiscriminatorMap(['debit_note' => 'SepaDebitNote', 'bank_transfer' => 'SepaBankTransfer'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBulkTransactionsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\SepaBulkTransactionEntityListener::class])]
class SepaBulkTransaction implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\DateTimeTrait;

  #[ORM\Column(type: 'integer', nullable: false)]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'IDENTITY')]
  protected int $id;

  /**
   * @var Collection<DatabaseStorageFile>
   *
   * CSV-files with export tables.
   */
  #[ORM\JoinTable(name: 'SepaBulkTransactionData')]
  #[ORM\InverseJoinColumn(unique: true)]
  #[ORM\ManyToMany(targetEntity: DatabaseStorageFile::class, fetch: 'EXTRA_LAZY', cascade: ['persist'], orphanRemoval: true)]
  protected Collection $sepaTransactionData;

  /**
   * @var Collection<DatabaseStorageFile>
   *
   * CSV-Files with account software balancing accounts (GnuCash(.
   */
  #[ORM\JoinTable(name: 'SepaBulkTransactionBalancingData')]
  #[ORM\InverseJoinColumn(unique: true)]
  #[ORM\ManyToMany(targetEntity: DatabaseStorageFile::class, fetch: 'EXTRA_LAZY', cascade: ['persist'], orphanRemoval: true)]
  protected Collection $balancingItemsData;

  /**
   * Latest date before which the debit notes have to be submitted to
   * the bank in order to match the $dueDate.
   */
  #[ORM\Column(type: 'date_immutable', nullable: false)]
  protected DateTimeImmutable $submissionDeadline;

  /**
   * The date when the bulk-transfer data actually was submitted to the bank.
   */
  #[ORM\Column(type: 'date_immutable', nullable: true)]
  protected ?DateTimeImmutable $submitDate;

  /**
   * The date when the money should arrive.
   */
  #[ORM\Column(type: 'date_immutable', nullable: false)]
  protected DateTimeImmutable $dueDate;

  #[ORM\Column(type: 'string', length: 256, nullable: true, options: ['comment' => 'Cloud Calendar Object URI'])]
  protected ?string $submissionEventUri;

  #[ORM\Column(type: 'string', length: 256, nullable: true, options: ['comment' => 'Cloud Calendar Object UID'])]
  protected ?string $submissionEventUid;

  #[ORM\Column(type: 'string', length: 256, nullable: true, options: ['comment' => 'Cloud Calendar Object URI'])]
  protected ?string $submissionTaskUri;

  #[ORM\Column(type: 'string', length: 256, nullable: true, options: ['comment' => 'Cloud Calendar Object UID'])]
  protected ?string $submissionTaskUid;

  #[ORM\Column(type: 'string', length: 256, nullable: true, options: ['comment' => 'Cloud Calendar Object URI'])]
  protected ?string $dueEventUri;

  #[ORM\Column(type: 'string', length: 256, nullable: true, options: ['comment' => 'Cloud Calendar Object UID'])]
  protected ?string $dueEventUid;

  /**
   * @var Collection<string, CompositePayment>
   */
  #[ORM\OneToMany(targetEntity: CompositePayment::class, indexBy: 'musician_id', mappedBy: 'sepaTransaction', orphanRemoval: true, cascade: ['all'], fetch: 'EXTRA_LAZY')]
  protected Collection $payments;

  /**
   * @var Collection<string, SentEmail>
   */
  #[ORM\OneToMany(targetEntity: SentEmail::class, indexBy: 'message_id', mappedBy: 'sepaBulkTransaction')]
  protected Collection $preNotificationEmails;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->sepaTransactionData = new ArrayCollection();
    $this->balancingItemsData = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->preNotificationEmails = new ArrayCollection();
  }
  // phpcs:enable

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
   * Set sepaTransactionData.
   *
   * @param Collection $sepaTransactionData
   *
   * @return SepaBulkTransaction
   */
  public function setSepaTransactionData(Collection $sepaTransactionData):SepaBulkTransaction
  {
    $this->sepaTransactionData = $sepaTransactionData;

    return $this;
  }

  /**
   * Get sepaTransactionData.
   *
   * @return Collection
   */
  public function getSepaTransactionData():Collection
  {
    return $this->sepaTransactionData;
  }

  /**
   * @param DatabaseStorageFile $data
   *
   * @return SepaBulkTransaction
   */
  public function addTransactionData(DatabaseStorageFile $data):SepaBulkTransaction
  {
    if (!$this->sepaTransactionData->contains($data)) {
      $this->sepaTransactionData->add($data);
    }
    return $this;
  }

  /**
   * @param DatabaseStorageFile $data
   *
   * @return SepaBulkTransaction
   */
  public function removeTransactionData(DatabaseStorageFile $data):SepaBulkTransaction
  {
    if ($this->sepaTransactionData->contains($data)) {
      $this->sepaTransactionData->removeElement($data);
    }
    return $this;
  }

  /**
   * Set balancingItemsData.
   *
   * @param Collection $balancingItemsData
   *
   * @return SepaBulkTransaction
   */
  public function setBalancingItemsData(Collection $balancingItemsData):SepaBulkTransaction
  {
    $this->balancingItemsData = $balancingItemsData;

    return $this;
  }

  /**
   * Get balancingItemsData.
   *
   * @return Collection
   */
  public function getBalancingItemsData():Collection
  {
    return $this->balancingItemsData;
  }

  /**
   * @param DatabaseStorageFile $data
   *
   * @return SepaBulkTransaction
   */
  public function addBalancingItemsData(DatabaseStorageFile $data):SepaBulkTransaction
  {
    if (!$this->balancingItemsData->contains($data)) {
      $this->balancingItemsData->add($data);
    }
    return $this;
  }

  /**
   * @param DatabaseStorageFile $data
   *
   * @return SepaBulkTransaction
   */
  public function removeBalancingItemsData(DatabaseStorageFile $data):SepaBulkTransaction
  {
    if ($this->balancingItemsData->contains($data)) {
      $this->balancingItemsData->removeElement($data);
    }
    return $this;
  }

  /**
   * Set submissionDeadline.
   *
   * @param DateTimeInterface $submissionDeadline
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionDeadline(?DateTimeInterface $submissionDeadline):SepaBulkTransaction
  {
    $this->submissionDeadline = $submissionDeadline;

    return $this;
  }

  /**
   * Get submissionDeadline.
   *
   * @return null|DateTimeInterface
   */
  public function getSubmissionDeadline():?DateTimeInterface
  {
    return $this->submissionDeadline;
  }

  /**
   * Set submitDate.
   *
   * @param string|int|\DateTimeInterface $submitDate
   *
   * @return SepaBulkTransaction
   */
  public function setSubmitDate($submitDate):SepaBulkTransaction
  {
    $this->submitDate = self::convertToDateTime($submitDate);

    return $this;
  }

  /**
   * Get submitDate.
   *
   * @return \DateTimeInterface|null
   */
  public function getSubmitDate():?DateTimeInterface
  {
    return $this->submitDate ?? null;
  }

  /**
   * Set dueDate.
   *
   * @param string|int|\DateTimeInterface $dueDate
   *
   * @return SepaBulkTransaction
   */
  public function setDueDate($dueDate):SepaBulkTransaction
  {
    $this->dueDate = self::convertToDateTime($dueDate);

    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return DateTimeInterface
   */
  public function getDueDate():?DateTimeInterface
  {
    return $this->dueDate;
  }

  /**
   * Set submissionEventUri.
   *
   * @param null|string $submissionEventUri
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionEventUri(?string $submissionEventUri):SepaBulkTransaction
  {
    $this->submissionEventUri = $submissionEventUri;

    return $this;
  }

  /**
   * Get submissionEventUri.
   *
   * @return null|string
   */
  public function getSubmissionEventUri():?string
  {
    return $this->submissionEventUri;
  }

  /**
   * Set submissionEventUid.
   *
   * @param null|string $submissionEventUid
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionEventUid(?string $submissionEventUid):SepaBulkTransaction
  {
    $this->submissionEventUid = $submissionEventUid;

    return $this;
  }

  /**
   * Get submissionEventUid.
   *
   * @return null|string
   */
  public function getSubmissionEventUid():?string
  {
    return $this->submissionEventUid;
  }

  /**
   * Set submissionTaskUri.
   *
   * @param null|string $submissionTaskUri
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionTaskUri(?string $submissionTaskUri):SepaBulkTransaction
  {
    $this->submissionTaskUri = $submissionTaskUri;

    return $this;
  }

  /**
   * Get submissionTaskUri.
   *
   * @return null|string
   */
  public function getSubmissionTaskUri():?string
  {
    return $this->submissionTaskUri;
  }

  /**
   * Set submissionTaskUid.
   *
   * @param null|string $submissionTaskUid
   *
   * @return SepaBulkTransaction
   */
  public function setSubmissionTaskUid(?string $submissionTaskUid):SepaBulkTransaction
  {
    $this->submissionTaskUid = $submissionTaskUid;

    return $this;
  }

  /**
   * Get submissionTaskUid.
   *
   * @return null|string
   */
  public function getSubmissionTaskUid():?string
  {
    return $this->submissionTaskUid;
  }

  /**
   * Set dueEventUri.
   *
   * @param null|string $dueEventUri
   *
   * @return SepaBulkTransaction
   */
  public function setDueEventUri(?string $dueEventUri):SepaBulkTransaction
  {
    $this->dueEventUri = $dueEventUri;

    return $this;
  }

  /**
   * Get dueEventUri.
   *
   * @return null|string
   */
  public function getDueEventUri():?string
  {
    return $this->dueEventUri;
  }

  /**
   * Set dueEventUid.
   *
   * @param null|string $dueEventUid
   *
   * @return SepaBulkTransaction
   */
  public function setDueEventUid(?string $dueEventUid):SepaBulkTransaction
  {
    $this->dueEventUid = $dueEventUid;

    return $this;
  }

  /**
   * Get dueEventUid.
   *
   * @return null|string
   */
  public function getDueEventUid():?string
  {
    return $this->dueEventUid;
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return SepaBulkTransaction
   */
  public function setPayments(Collection $payments):SepaBulkTransaction
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
   * Get the payment for the specified musician
   *
   * @param int|Muscian $musician Musician-id or entity.
   *
   * @return null|CompositePayment
   */
  public function getPayment($musician):?CompositePayment
  {
    $musicianId = ($musician instanceof Musician) ? $musician->getId() : $musician;
    if ($this->payments->containsKey($musicianId)) {
      return $this->payments->get($musicianId);
    }
    // need to search ...
    $payments = $this->payments->filter(fn(CompositePayment $payment) => $payment->getMusician()->getId() == $musicianId);
    if ($payments->count() === 1) {
      return $payments->first();
    }
    return null;
  }

  /**
   * Set preNotificationEmails.
   *
   * @param Collection $preNotificationEmails
   *
   * @return SepaBulkTransaction
   */
  public function setPreNotificationEmails(Collection $preNotificationEmails):SepaBulkTransaction
  {
    $this->preNotificationEmails = $preNotificationEmails;

    return $this;
  }

  /**
   * Get preNotificationEmails.
   *
   * @return Collection
   */
  public function getPreNotificationEmails():Collection
  {
    return $this->preNotificationEmails;
  }

  /**
   * Add a new email to the list of pre-notificiations
   *
   * @param SentEmail $sentEmail
   *
   * @return SepaBulkTransaction
   */
  public function addPreNotificationEmail(SentEmail $sentEmail):SepaBulkTransaction
  {
    $messageId = $sentEmail->getMessageId();
    if ($this->getPreNotificationEmail($messageId) === null) {
      $this->preNotificationEmails->set($messageId, $sentEmail);
    }
    return $this;
  }

  /**
   * Get the preNotificationEmail for the specified musician
   *
   * @param string $messageId
   *
   * @return null|SentEmail
   */
  public function getPreNotificationEmail(string $messageId):?SentEmail
  {
    if ($this->preNotificationEmails->containsKey($messageId)) {
      return $this->preNotificationEmails->get($messageId);
    }
    // need to search ...
    $preNotificationEmails = $this->preNotificationEmails->filter(fn(SentEmail $sentEmail) => $sentEmail->getMessageId() == $messageId);
    if ($preNotificationEmails->count() === 1) {
      return $preNotificationEmails->first();
    }
    return null;
  }

  /**
   * @return RationalNumber The sum of all contained split transactions.
   */
  public function totals():RationalNumber
  {
    return $this->payments->reduce(
      fn(RationalNumber $accumulator, CompositePayment $payment) => $accumulator->addEq($payment->getAmount()),
      RationalNumber::zero(),
    );
  }

  /**
   * Return the number of related CompositePayment entities.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->payments->count();
  }
}

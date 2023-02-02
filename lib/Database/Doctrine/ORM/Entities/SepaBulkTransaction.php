<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use \DateTimeInterface;
use \RuntimeException;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Database\EntityManager;

/**
 * SepaBulkTransaction
 *
 * This actually models a batch collection
 *
 * @ORM\Table(name="SepaBulkTransactions")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="sepa_transaction", enumType="EnumSepaTransaction", length=32)
 * @ORM\DiscriminatorMap({null="SepaBulkTransaction","debit_note"="SepaDebitNote", "bank_transfer"="SepaBankTransfer"})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBulkTransactionsRepository")
 * @ORM\EntityListeners({"\OCA\CAFEVDB\Listener\SepaBulkTransactionEntityListener"})
 *
 * @ORM\HasLifecycleCallbacks
 */
class SepaBulkTransaction implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use \OCA\RotDrop\Toolkit\Traits\DateTimeTrait;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var Collection
   *
   * @ORM\ManyToMany(targetEntity="DatabaseStorageFile", fetch="EXTRA_LAZY", cascade={"persist"}, orphanRemoval=true)
   * @ORM\JoinTable(
   *   name="SepaBulkTransactionData",
   *   inverseJoinColumns={
   *     @ORM\JoinColumn(unique=true)
   *   }
   * )
   *
   * Export sets for submission to the bank. There may be more than one export
   * set for a given transaction, but each export set can only belong to one
   * transaction. Export-data is generated on-the-fly by issuing the download
   * and tagged immutable once the transaction has been submitted to the bank.
   */
  private $sepaTransactionData;

  /**
   * @var \DateTimeImmutable
   *
   * Latest date before which the debit notes have to be submitted to
   * the bank in order to match the $dueDate.
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $submissionDeadline;

  /**
   * @var \DateTime|null
   * The date when the bulk-transfer data actually was submitted to the bank.
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $submitDate;

  /**
   * @var \DateTimeImmutable
   * The date when the money should arrive.
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $dueDate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object URI"})
   */
  private $submissionEventUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object UID"})
   */
  private $submissionEventUid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object URI"})
   */
  private $submissionTaskUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object UID"})
   */
  private $submissionTaskUid;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object URI"})
   */
  private $dueEventUri;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=true, options={"comment"="Cloud Calendar Object UID"})
   */
  private $dueEventUid;

  /**
   * @var ArrayCollection
   *
   * @ORM\OneToMany(targetEntity="CompositePayment", indexBy="musician_id", mappedBy="sepaTransaction", orphanRemoval=true, cascade={"all"}, fetch="EXTRA_LAZY")
   */
  private $payments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->sepaTransactionData = new ArrayCollection();
    $this->payments = new ArrayCollection();
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
    return $this->submitDate;
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
   * @return The sum of all contained split transactions.
   */
  public function totals():float
  {
    $totals = 0.0;
    /** @var CompositePayment $payment */
    foreach ($this->payments as $payment) {
      $totals += $payment->getAmount();
    }
    return $totals;
  }

  /**
   * Return the number of related ProjectPayment entities.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->payments->count();
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
    $field = 'submitDate';
    if ($event->hasChangedField($field)) {
      /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
      $oldValue = $event->getOldValue($field);
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
    $field = 'submitDate';
    if (array_key_exists($field, $this->preUpdateValue)) {
      /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
      $entityManager = EntityManager::getDecorator($event->getEntityManager());
      $entityManager->dispatchEvent(new Events\PostChangeSepaBulkTransactionSubmitDate(
        $entityManager, $this, $this->preUpdateValue[$field]));
      unset($this->preUpdateValue[$field]);
    }
  }
}

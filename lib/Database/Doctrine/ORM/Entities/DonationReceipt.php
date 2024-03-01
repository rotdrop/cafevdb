<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use JsonSerializable;
use ArrayAccess;

use Sabre\VObject\Component\VCard;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;
use OCA\CAFEVDB\Common\Uuid;

/**
 * Although a donation in principle is just a payment there is some meta-data
 * to take care of. This is maintained here.
 *
 * @ORM\Table(
 *   name="DonationReceipts",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(name="donation_receipt_unique", columns={
 *       "donation_id", "deleted",
 *   })}
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 * @ORM\HasLifecycleCallbacks
 */
class DonationReceipt implements JsonSerializable, ArrayAccess
{
  use CAFEVDB\Traits\UnusedTrait;
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * @var string
   *
   * Depending on the regulation and the corresponding tax exemption notice it
   * may or may not be legal to send out donation receipts by email. If not,
   * the notification template may be used to inform the donator about a
   * forthcoming snail-mail letter.
   */
  public const NOTIFICATION_EMAIL_TEMPLATE = 'donation-receipt-notification';

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private ?int $id = null;

  /**
   * @var CompositePayment
   *
   * The associated payment.
   *
   * @ORM\OneToOne(targetEntity="CompositePayment", inversedBy="donationReceipt")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="donation_id", referencedColumnName="id", nullable=false),
   * )
   */
  private CompositePayment $donation;

  /**
   * @var TaxExemptionNotice
   *
   * The associated notice of tax exemption with legalizes this donation
   * receipt.
   *
   * @ORM\ManyToOne(targetEntity="TaxExemptionNotice", inversedBy="donationReceipts")
   */
  private TaxExemptionNotice $taxExemptionNotice;

  /**
   * @var DatabaseStorageFile
   *
   * An electronic copy of the probably hand-signed original.
   *
   * @ORM\OneToOne(targetEntity="DatabaseStorageFile", fetch="EXTRA_LAZY", cascade={"all"}, orphanRemoval=true)
   */
  private $supportingDocument;

  /**
   * @var \DateTimeImmutable|null
   *
   * Date when this donation receipt has been sent out to the donator. If non
   * null the donation receipt must not be deleted.
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $mailingDate;

  /**
   * @var string
   *
   * Sending out donation receipts by email may not be allowed, but even if
   * not there may be an additional email to the donator notifying him or her
   * about the sending out of the donation receipt.
   *
   * @ORM\OneToOne(targetEntity="SentEmail", inversedBy="donationReceipt")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="notification_message_id", referencedColumnName="message_id", nullable=true),
   * )
   */
  private SentEmail $notificationMessage;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->__wakeup();
  }

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return DonationReceipt
   */
  public function setId(int $id):DonationReceipt
  {
    $this->id = $id;

    return $this;
  }

  /**
   * Get id.
   *
   * @return null|int
   */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * Set donation.
   *
   * @param CompositePayment $donation
   *
   * @return LegalPerson
   */
  public function setDonation(CompositePayment $donation):DonationReceipt
  {
    $this->donation = $donation;

    return $this;
  }

  /**
   * Get donation.
   *
   * @return CompositePayment
   */
  public function getDonation():CompositePayment
  {
    return $this->donation;
  }

  /**
   * Set taxExemptionNotice.
   *
   * @param TaxExemptionNotice $taxExemptionNotice
   *
   * @return LegalPerson
   */
  public function setTaxExemptionNotice(TaxExemptionNotice $taxExemptionNotice):DonationReceipt
  {
    $this->taxExemptionNotice = $taxExemptionNotice;

    return $this;
  }

  /**
   * Get taxExemptionNotice.
   *
   * @return TaxExemptionNotice
   */
  public function getTaxExemptionNotice():TaxExemptionNotice
  {
    return $this->taxExemptionNotice;
  }

  /**
   * Set supportingDocument.
   *
   * @param null|DatabaseStorageFile $supportingDocument
   *
   * @return CompositePayment
   */
  public function setSupportingDocument(?DatabaseStorageFile $supportingDocument):DonationReceipt
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
   * Set mailingDate.
   *
   * @param \DateTime|null $mailingDate
   *
   * @return CompositePayment
   */
  public function setMailingDate($mailingDate = null):DonationReceipt
  {
    $this->mailingDate = self::convertToDateTime($mailingDate);

    return $this;
  }

  /**
   * Get mailingDate.
   *
   * @return \DateTime|null
   */
  public function getMailingDate()
  {
    return $this->mailingDate;
  }

  /**
   * Set notificationMessage.
   *
   * @param null|SentEmail $notificationMessage
   *
   * @return LegalPerson
   */
  public function setNotificationMessage(?SentEmail $notificationMessage):DonationReceipt
  {
    if ($notificationMessage !== null) {
      $notificationMessage->setDonationReceipt($this);
    }
    $this->notificationMessage = $notificationMessage;

    return $this;
  }

  /**
   * Get notificationMessage.
   *
   * @return null|SentEmail
   */
  public function getNotificationMessage():?SentEmail
  {
    return $this->taxExemptionNotice;
  }

  /**
   * Get musician.
   *
   * @return Musician
   */
  public function getMusician():Musician
  {
    return $this->donation->getMusician();
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject():Project
  {
    return $this->donation->getProject();
  }

  /**
   * Get projectParticipant.
   *
   * @return ProjectParticipant
   */
  public function getProjectParticipant():?ProjectParticipant
  {
    return $this->donation->getProjectParticipant();
  }

  /**
   * Get dateOfReceipt.
   *
   * @return null|DateTimeInterface
   */
  public function getDateOfReceipt():?DateTimeInterface
  {
    return $this->donation->getDateOfReceipt();
  }

  /**
   * Flag this entity as in use and thus undeleteable returning a positive
   * value.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->mailingDate !== null;
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    $this->toArray();
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return 'legalPerson(' . $this->musician ? $this->musician->getPublicName() : $this->contact . ')';
  }
}

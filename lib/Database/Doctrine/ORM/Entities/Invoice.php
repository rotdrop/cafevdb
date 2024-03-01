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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Record notices of tax exemption from the corporate income tax (or other
 * taxes).
 *
 * @ORM\Table(
 *   name="Invoices",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"notification_message_id"})
 *   }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\InvoicesRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 * @ORM\HasLifecycleCallbacks
 */
class Invoice implements JsonSerializable, ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="string", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private string $id;

  /**
   * @var LegalPerson
   *
   * The victim of the invoice issued.
   *
   * @ORM\ManyToOne(targetEntity="LegalPerson", inversedBy="invoices")
   */
  private LegalPerson $debitor;

  /**
   * @var LegalPerson
   *
   * This will -- must be -- a member of the executive borad. But the plan is
   * to tie address-fields to convenience methods of the LegalPerson entity,
   * so better use it here.
   *
   * @ORM\ManyToOne(targetEntity="LegalPerson", inversedBy="originatedInvoices")
   */
  private LegalPerson $originator;

  /**
   * @var \DateTimeImmutable
   *
   * @ORM\Column(type="date_immutable")
   */
  private DateTimeInterface $dueDate;

  /**
   * @var float
   *
   * The total amount invoiced.
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false)
   */
  private float  $amount;

  /**
   * @var string
   *
   * Purpose of the invoice. A polite text for the notification of the
   * debitor.
   *
   * @ORM\Column(type="string", length=4096, nullable=false)
   */
  private string $purpose;

  /**
   * @var DatabaseStorageFile
   *
   * @ORM\OneToOne(targetEntity="DatabaseStorageFile", cascade={"all"}, orphanRemoval=true)
   */
  private DatabaseStorageFile $writtenInvoice;

  /**
   * @var string
   *
   * The email communicating the invoice to the debitor.
   *
   * @ORM\OneToOne(targetEntity="SentEmail")
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
   * @return LegalPerson
   */
  public function setId(int $id):LegalPerson
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
   * Set debitor.
   *
   * @param LegalPerson $debitor
   *
   * @return Invoice
   */
  public function setDebitor(LegalPerson $debitor):Invoice
  {
    $this->debitor = $debitor;

    return $this;
  }

  /**
   * Get debitor.
   *
   * @return null|LegalPerson
   */
  public function getDebitor():?LegalPerson
  {
    return $this->debitor;
  }

  /**
   * Set originator.
   *
   * @param LegalPerson $originator
   *
   * @return Invoice
   */
  public function setOriginator(LegalPerson $originator):Invoice
  {
    $this->originator = $originator;

    return $this;
  }

  /**
   * Get originator.
   *
   * @return null|LegalPerson
   */
  public function getOriginator():?LegalPerson
  {
    return $this->originator;
  }

  /**
   * Set dueDate.
   *
   * @param null|string|DateTimeInterface $dueDate
   *
   * @return InsuranceRate
   */
  public function setDueDate($dueDate):Invoice
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
   * Set amount.
   *
   * @param float|null $amount
   *
   * @return ProjectPayment
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
  public function getAmount():?float
  {
    return $this->amount;
  }

  /**
   * @return null|string
   */
  public function getPurpose():?string
  {
    return $this->purpose;
  }

  /**
   * @param string $purpose
   *
   * @return Invoice
   */
  public function setPurpose(string $purpose):Invoice
  {
    $this->purpose = $purpose;

    return $this;
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
   * @return null|SentEmail
   */
  public function getNotificationMessage():?DatabaseStorageFile
  {
    return $this->notificationMessage;
  }

  /**
   * @param null|SentEmail $notificationMessage
   *
   * @return Invoice
   */
  public function setNotificationMessage(?DatabaseStorageFile $notificationMessage):Invoice
  {
    $this->notificationMessage = $notificationMessage;

    return $this;
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    $this->toArray();
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return 'invoice('
      . $this->assessmentPeriodStart . '-' . $this->assessmentPeriodEnd
      . '@'
      . $this->taxType . ' tax'
      . ')';
  }
}

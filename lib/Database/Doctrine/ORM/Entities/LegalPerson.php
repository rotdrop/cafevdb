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
 * A "LegalPerson" is either a musician from our database or an address-book
 * entry. This entity is a thin join-table like entity which joins persons
 * either to musicians or to addressbook entries.
 *
 * Actually, the musician are exported to the cloud's addressbook so arguably
 * one could also just use addressbook entries. However, doing it this way
 * should be more robust.
 *
 * @ORM\Table(
 *   name="LegalPersons",
 *   uniqueConstraints={
 *     @ORM\UniqueConstraint(columns={"musician_id", "contact_uuid"})
 *   }
 * )
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 *
 * @todo Eventually there should be convenience functions which expose name
 * and address for the purpose of performing mail-merge operations.
 */
class LegalPerson implements JsonSerializable, ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private ?int $id = null;

  /**
   * @var Musician
   *
   * @ORM\OneToOne(targetEntity="Musician", inversedBy="legalPerson")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id", referencedColumnName="id", nullable=true),
   * )
   */
  private ?Musician $musician;

  /**
   * @var \OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface
   *
   * The link to the addressbook. If $musician is also set then this is at the
   * same time equal to the uuid entry of the musican.
   *
   * @ORM\Column(type="uuid_binary", nullable=false)
   */
  private UuidInterface $contactUuid;

  /**
   * @var Collection
   *
   * Collection of invoices issued to -- i.e. payable by -- this person.
   *
   * @ORM\OneToMany(targetEntity="Invoice", mappedBy="debitor")
   */
  private Collection $invoices;

  /**
   * @var Collection
   *
   * Collection of invoices issued by this person.
   *
   * @ORM\OneToMany(targetEntity="Invoice", mappedBy="originator")
   */
  private Collection $originatedInvoices;

  /**
   * @var VCard
   *
   * Cached addressbook entry.
   */
  private $contact;

  /** {@inheritdoc} */
  public function __construct(?Musician $musician = null, ?UuidInterface $uuid = null)
  {
    $this->__wakeup();
    $this->setContactUuid($uuid);
    $this->setMusician($musician);
    $this->invoices = new ArrayCollection;
    $this->invoicesOrignated = new ArrayCollection;
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
   * Set musician.
   *
   * @param Musician $musician
   *
   * @return LegalPerson
   */
  public function setMusician(Musician $musician):LegalPerson
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return null|Musician
   */
  public function getMusician():?Musician
  {
    return $this->musician;
  }

  /**
   * Set the uid of the associated addressbook entry.
   *
   * @param null|UuidInterface $contactUuid
   *
   * @return LegalPerson
   */
  public function setContactUuid(UuidInterface $contactUuid):LegalPerson
  {
    if ($contactUuid !== null) {
      $contactUuid = Uuid::asUuid($contactUuid);
      if (empty($contactUuid)) {
        throw new InvalidArgumentException("UUID DATA: ".$contactUuid);
      }
    }
    $this->contactUuid = $contactUuid;

    return $this;
  }

  /**
   * Get contact.
   *
   * @return null|UuidInterface
   */
  public function getContactUuid():?UuidInterface
  {
    return $this->contactUuid;
  }

  /**
   * Set the uid of the associated addressbook entry.
   *
   * @param vCard $contact
   *
   * @return LegalPerson
   */
  public function setContact(vCard $contact):LegalPerson
  {
    $this->contact = $contact;

    return $this;
  }

  /**
   * Get contact.
   *
   * @return null|vCard
   */
  public function getContact():?vCard
  {
    if (empty($this->contact)) {
      $this->contact = \OC::$server->get(\OCA\CAFEVDB\Service\ContactsService::class)->export($this->musician);
    }
    return $this->contact;
  }

  /**
   * Set the associated invoices collection.
   *
   * @param Collection $invoices
   *
   * @return LegalPerson
   */
  public function setInvoices(Collection $invoices):LegalPerson
  {
    $this->invoices  = $invoices;

    return $this;
  }

  /**
   * Get invoices.
   *
   * @return Collection
   */
  public function getInvoices():Collection
  {
    return $this->invoices;
  }

  /**
   * Set the associated originatedInvoices collection.
   *
   * @param Collection $originatedInvoices
   *
   * @return LegalPerson
   */
  public function setInvoicesOriginated(Collection $originatedInvoices):LegalPerson
  {
    $this->originatedInvoices  = $originatedInvoices;

    return $this;
  }

  /**
   * Get originatedInvoices.
   *
   * @return Collection
   */
  public function getInvoicesOriginated():Collection
  {
    return $this->originatedInvoices;
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

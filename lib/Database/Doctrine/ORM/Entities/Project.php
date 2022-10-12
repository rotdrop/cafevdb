<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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

use DateTimeImmutable;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * Projects
 *
 * @ORM\Table(name="Projects", uniqueConstraints={@ORM\UniqueConstraint(columns={"name"})})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Project implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true})
   */
  private $year;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   */
  private $name;

  /**
   * @var Types\EnumProjectTemporalType
   *
   * @ORM\Column(type="EnumProjectTemporalType", nullable=false, options={"default"="temporary"})
   */
  private $type = Types\EnumProjectTemporalType::TEMPORARY;

  /**
   * @var string
   *
   * The list-id of the mailing list for the members
   *
   * @ORM\Column(type="string", nullable=true, length="128", options={"collation"="ascii_general_ci"})
   */
  private $mailingListId;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrumentationNumber", mappedBy="project", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $instrumentationNumbers;

  /**
   * @ORM\OneToMany(targetEntity="ProjectWebPage", mappedBy="project", cascade={"persist"}, fetch="EXTRA_LAZY")
   * @todo this should cascade deletes
   */
  private $webPages;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantField", mappedBy="project", indexBy="id", fetch="EXTRA_LAZY")
   * @ORM\OrderBy({"displayOrder" = "DESC"})
   */
  private $participantFields;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDatum", mappedBy="project", fetch="EXTRA_LAZY")
   */
  private $participantFieldsData;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipant", mappedBy="project")
   */
  private $participants;

  /**
   * @ORM\OneToMany(targetEntity="SepaDebitMandate", mappedBy="project")
   */
  private $sepaDebitMandates;

  /**
   * @ORM\OneToMany(targetEntity="CompositePayment", mappedBy="project")
   */
  private $compositePayments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="project")
   */
  private $payments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="project")
   */
  private $participantInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectEvent", mappedBy="project")
   */
  private $calendarEvents;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="SentEmail", mappedBy="project")
   */
  private $sentEmail;

  /**
   * @var DatabaseStorageFolder
   *
   * @ORM\OneToOne(targetEntity="DatabaseStorageFolder", fetch="EXTRA_LAZY")
   */
  private $financialBalanceDocumentsFolder;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->instrumentationNumbers = new ArrayCollection();
    $this->webPages = new ArrayCollection();
    $this->participantFields = new ArrayCollection();
    $this->participantFieldsData = new ArrayCollection();
    $this->participants = new ArrayCollection();
    $this->participantInstruments = new ArrayCollection();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->compositePayments = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->sentEmail = new ArrayCollection();
  }

  /** {@inheritdoc} */
  public function __clone()
  {
    if ($this->id) {
      return;
    }
    $this->id = null;
    $oldInstrumentationNumbers = $this->instrumentationNumbers;
    $oldParticipantFields = $this->participantFields;
    $this->__construct();

    // clone all instrumentation numbers
    foreach ($oldInstrumentationNumbers as $oldInstrumentationNumber) {
      /** @var ProjectInstrumentationNumber $instrumentationNumber  */
      $instrumentationNumber = clone $oldInstrumentationNumber;
      $instrumentationNumber->setProject($this);
      $this->instrumentationNumbers->add($instrumentationNumber);
    }

    // clone all participant fields
    foreach ($oldParticipantFields as $oldParticipantField) {
      /** @var ProjectParticipantField $participantField */
      $participantField = clone $oldParticipantField;
      $participantField->setProject($this);
      $this->participantFields->add($participantField);
    }

    // anything else stays empty
  }

  /**
   * Set id.
   *
   * @param int $id Entity id.
   *
   * @return Project
   */
  public function setId(int $id):Project
  {
    $this->id = $id;

    return $this;
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
   * Set year.
   *
   * @param null|int $year
   *
   * @return Project
   */
  public function setYear(?int $year):Project
  {
    $this->year = $year;

    return $this;
  }

  /**
   * Get year.
   *
   * @return int
   */
  public function getYear()
  {
    return $this->year;
  }

  /**
   * Set name.
   *
   * @param null|string $name
   *
   * @return Project
   */
  public function setName(?string $name):Project
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Set type.
   *
   * @param EnumProjectTemporalType|string $type
   *
   * @return Project
   */
  public function setType($type):Project
  {
    $this->type = new Types\EnumProjectTemporalType($type);

    return $this;
  }

  /**
   * Get type.
   *
   * @return EnumProjectTemporalType
   */
  public function getType():Types\EnumProjectTemporalType
  {
    return $this->type;
  }

  /**
   * Set mailingListId.
   *
   * @param null|string $mailingListId
   *
   * @return Project
   */
  public function setMailingListId(?string $mailingListId):Project
  {
    $this->mailingListId = $mailingListId;
    return $this;
  }

  /**
   * Get mailingListId.
   *
   * @return null|string
   */
  public function getMailingListId():?string
  {
    return $this->mailingListId;
  }

  /**
   * Set webPages.
   *
   * @param Collection $webPages
   *
   * @return Project
   */
  public function setWebPages(Collection $webPages):Project
  {
    $this->webPages = $webPages;

    return $this;
  }

  /**
   * Get webPages.
   *
   * @return Collection
   */
  public function getWebPages()
  {
    return $this->webPages;
  }

  /**
   * Set participantFields.
   *
   * @param Collection $participantFields
   *
   * @return Project
   */
  public function setParticipantFields(Collection $participantFields):Project
  {
    $this->participantFields = $participantFields;

    return $this;
  }

  /**
   * Get participantFields.
   *
   * @return Collection
   */
  public function getParticipantFields():Collection
  {
    return $this->participantFields;
  }

  /**
   * Set participantFieldsData.
   *
   * @param Collection $participantFieldsData
   *
   * @return Project
   */
  public function setParticipantFieldsData(Collection $participantFieldsData):Project
  {
    $this->participantFieldsData = $participantFieldsData;

    return $this;
  }

  /**
   * Get participantFieldsData.
   *
   * @return Collection
   */
  public function getParticipantFieldsData():Collection
  {
    return $this->participantFieldsData;
  }

  /**
   * Set participants.
   *
   * @param Collection $participants
   *
   * @return Project
   */
  public function setParticipants(Collection $participants):Project
  {
    $this->participants = $participants;

    return $this;
  }

  /**
   * Get participants.
   *
   * @return Collection
   */
  public function getParticipants():Collection
  {
    return $this->participants;
  }

  /**
   * Set sepaDebitMandates.
   *
   * @param Collection $sepaDebitMandates
   *
   * @return Project
   */
  public function setSepaDebitMandates(Collection $sepaDebitMandates):Project
  {
    $this->sepaDebitMandates = $sepaDebitMandates;

    return $this;
  }

  /**
   * Get sepaDebitMandates.
   *
   * @return Collection
   */
  public function getSepaDebitMandates():Collection
  {
    return $this->sepaDebitMandates;
  }

  /**
   * Set instrumentationNumbers.
   *
   * @param Collection $instrumentationNumbers
   *
   * @return Project
   */
  public function setInstrumentationNumbers(Collection $instrumentationNumbers):Project
  {
    $this->instrumentationNumbers = $instrumentationNumbers;

    return $this;
  }

  /**
   * Get instrumentationNumbers.
   *
   * @return Collection
   */
  public function getInstrumentationNumbers()
  {
    return $this->instrumentationNumbers;
  }

  /**
   * Set payments.
   *
   * @param ArrayCollection $payments
   *
   * @return Project
   */
  public function setPayments(Collection $payments):Project
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
   * Set sentEmail.
   *
   * @param Collection $sentEmail
   *
   * @return Project
   */
  public function setSentEmail(Collection $sentEmail):Project
  {
    $this->sentEmail = $sentEmail;

    return $this;
  }

  /**
   * Get sentEmail.
   *
   * @return Collection
   */
  public function getSentEmail():Collection
  {
    return $this->sentEmail;
  }

  /**
   * Get financialBalanceSupportingDocumentsChanged time-stamp.
   *
   * @return \DateTimeInterface
   */
  public function getFinancialBalanceSupportingDocumentsChanged():?\DateTimeInterface
  {
    if (!empty($this->financialBalanceDocumentsFolder)) {
      return $this->financialBalanceDocumentsFolder->getUpdated();
    }
    return new DateTimeImmutable('@1');
  }

  /**
   * Set financialBalanceDocumentsFolder.
   *
   * @param DatabaseStorageFolder $financialBalanceDocumentsFolder
   *
   * @return Project
   */
  public function setFinancialBalanceDocumentsFolder(DatabaseStorageFolder $financialBalanceDocumentsFolder):Project
  {
    $this->financialBalanceDocumentsFolder = $financialBalanceDocumentsFolder;

    return $this;
  }

  /**
   * Get financialBalanceDocumentsFolder.
   *
   * @return null|DatabaseStorageFolder
   */
  public function getFinancialBalanceDocumentsFolder():?DatabaseStorageFolder
  {
    return $this->financialBalanceDocumentsFolder;
  }

  /**
   * Recurse to the balance documents folder and return its contents. Return
   * an empty collection if there is no such folder.
   *
   * @return Collection
   */
  public function getFinancialBalanceSupportingDocuments():Collection
  {
    if (empty($this->financialBalanceDocumentsFolder)) {
      return new ArrayCollection;
    }
    return $this->financialBalanceDocumentsFolder->getSubFolders();
  }


  /**
   * Set participantInstruments.
   *
   * @param ArrayCollection $participantInstruments
   *
   * @return Project
   */
  public function setParticipantInstruments(Collection $participantInstruments):Project
  {
    $this->participantInstruments = $participantInstruments;

    return $this;
  }

  /**
   * Get participantInstruments.
   *
   * @return Collection
   */
  public function getParticipantInstruments():Collection
  {
    return $this->participantInstruments;
  }

  /**
   * Set calendarEvents.
   *
   * @param ArrayCollection $calendarEvents
   *
   * @return Project
   */
  public function setCalendarEvents(Collection $calendarEvents):Project
  {
    $this->calendarEvents = $calendarEvents;

    return $this;
  }

  /**
   * Get calendarEvents.
   *
   * @return Collection
   */
  public function getCalendarEvents():Collection
  {
    return $this->calendarEvents;
  }

  /**
   * Return the number of "serious" items which "use" this entity. For
   * project participant this is (for now) the number of payments. In
   * the long run: only open payments/receivables should count.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->payments->count();
  }
}

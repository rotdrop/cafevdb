<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTimeImmutable;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Criteria;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Order;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Types\Types as DBALTypes;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * Projects
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
#[ORM\Table(name: DatabaseTables::PROJECTS_TABLE)]
#[ORM\UniqueConstraint(columns: ['name'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\ProjectEntityListener::class])]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
class Project implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\AutoIncrementTrait;
  use CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  private const DATE_FORMAT = 'Ymd';

  #[ORM\Column(type: 'integer', nullable: false, options: ['unsigned' => true])]
  private int $year;

  #[ORM\Column(type: 'string', length: 64, nullable: false)]
  private string $name;

  #[ORM\Column(type: DBALTypes::ENUM, nullable: false, options: ['default' => Types\EnumProjectTemporalType::TEMPORARY])]
  private Types\EnumProjectTemporalType $type = Types\EnumProjectTemporalType::TEMPORARY;

  /**
   * The list-id of the mailing list for the members
   */
  #[ORM\Column(type: 'string', nullable: true, length: '128', options: ['collation' => 'ascii_general_ci'])]
  private ?string $mailingListId = null;

  /**
   * Optional registration start date. If not set then the online registration
   * is NOT available.
   */
  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private ?DateTimeImmutable $registrationStartDate = null;

  /**
   * Optional registration deadline. If null then the date one day before the
   * first rehearsal is used, if set. Otherwise no registration dead-line is
   * imposed.
   */
  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private ?DateTimeImmutable $registrationDeadline = null;

  /**
   * Optional link to the project registration event.
   */
  #[ORM\OneToOne(targetEntity: ProjectEvent::class, cascade: ['all'], fetch: 'EXTRA_LAZY')]
  private ?ProjectEvent $registrationCalendarEvent = null;

  /** @var Collection<ProjectInstrumentationNumber> */
  #[ORM\OneToMany(targetEntity: ProjectInstrumentationNumber::class, mappedBy: 'project', orphanRemoval: true, fetch: 'EXTRA_LAZY')]
  private Collection $instrumentationNumbers;

  /**
   * @var Collection<ProjectWebPage>
   *
   * @todo this should cascade deletes
   */
  #[ORM\OneToMany(targetEntity: ProjectWebPage::class, mappedBy: 'project', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
  private Collection $webPages;

  /**
   * @var Collection<int, ProjectParticipantField>
   *
   * @todo This does not work well with _AT_Gedmo\Translatable
   */
  #[ORM\OneToMany(targetEntity: ProjectParticipantField::class, mappedBy: 'project', indexBy: 'id', fetch: 'EXTRA_LAZY')]
  #[ORM\OrderBy(['displayOrder' => 'DESC'])]
  private Collection $participantFields;

  /** @var Collection<ProjectParticipantFieldDatum> */
  #[ORM\OneToMany(targetEntity: ProjectParticipantFieldDatum::class, mappedBy: 'project', fetch: 'EXTRA_LAZY')]
  private Collection $participantFieldsData;

  /** @var Collection<int, ProjectParticipant> */
  #[ORM\OneToMany(targetEntity: ProjectParticipant::class, mappedBy: 'project', indexBy: 'musician_id', cascade: ['persist'])]
  private Collection $participants;

  /** @var Collection<int, ProjectApplication> */
  #[ORM\OneToMany(targetEntity: ProjectApplication::class, mappedBy: 'project', indexBy: 'email')]
  private Collection $applications;

  /** @var Collection<SepaDebitMandate> */
  #[ORM\OneToMany(targetEntity: SepaDebitMandate::class, mappedBy: 'project')]
  private Collection $sepaDebitMandates;

  /** @var Collection<CompositePayment> */
  #[ORM\OneToMany(targetEntity: CompositePayment::class, mappedBy: 'project')]
  private Collection $compositePayments;

  /** @var Collection<ProjectPayment> */
  #[ORM\OneToMany(targetEntity: ProjectPayment::class, mappedBy: 'project')]
  private Collection $payments;

  /** @var Collection<Invoice> */
  #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'project')]
  private Collection $invoices;

  /** @var Collection<ProjectInstrument> */
  #[ORM\OneToMany(targetEntity: ProjectInstrument::class, mappedBy: 'project')]
  private Collection $participantInstruments;

  /** @var Collection<ProjectEvent> */
  #[ORM\OneToMany(targetEntity: ProjectEvent::class, mappedBy: 'project')]
  private Collection $calendarEvents;

  /** @var Collection<SentEmail> */
  #[ORM\OneToMany(targetEntity: SentEmail::class, mappedBy: 'project')]
  private Collection $sentEmail;

  #[ORM\OneToOne(targetEntity: DatabaseStorage::class, fetch: 'EXTRA_LAZY', cascade: ['all'], orphanRemoval: true)]
  private ?DatabaseStorage $financialBalanceDocumentsStorage = null;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->applications = new ArrayCollection();
    $this->compositePayments = new ArrayCollection();
    $this->instrumentationNumbers = new ArrayCollection();
    $this->invoices = new ArrayCollection();
    $this->participantFields = new ArrayCollection();
    $this->participantFieldsData = new ArrayCollection();
    $this->participantInstruments = new ArrayCollection();
    $this->participants = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->sentEmail = new ArrayCollection();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->webPages = new ArrayCollection();
    $this->calendarEvents = new ArrayCollection();
    $this->type = Types\EnumProjectTemporalType::TEMPORARY;
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
  public function setType(string|Types\EnumProjectTemporalType $type): Project
  {
    $this->type = Types\EnumProjectTemporalType::get($type);

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
   * Sets registrationStartDate.
   *
   * @param string|int|DateTimeInterface $registrationStartDate
   *
   * @return Project
   */
  public function setRegistrationStartDate(mixed $registrationStartDate):Project
  {
    if (empty($registrationStartDate)) {
      $this->registrationStartDate = null;
    } else {
      $registrationStartDate = self::convertToDateTime($registrationStartDate);
      if (empty($this->registrationStartDate) || $this->registrationStartDate->format(self::DATE_FORMAT) != $registrationStartDate->format(self::DATE_FORMAT)) {
        $this->registrationStartDate = $registrationStartDate;
      }
    }
    return $this;
  }

  /**
   * Returns registrationStartDate.
   *
   * @return DateTimeImmutable
   */
  public function getRegistrationStartDate():?DateTimeInterface
  {
    return $this->registrationStartDate;
  }

  /**
   * Sets registrationDeadline.
   *
   * @param string|int|DateTimeInterface $registrationDeadline
   *
   * @return Project
   */
  public function setRegistrationDeadline(mixed $registrationDeadline):Project
  {
    if (empty($registrationDeadline)) {
      $this->registrationDeadline = null;
    } else {
      $registrationDeadline = self::convertToDateTime($registrationDeadline);
      if (empty($this->registrationDeadline) || $this->registrationDeadline->format(self::DATE_FORMAT) != $registrationDeadline->format(self::DATE_FORMAT)) {
        $this->registrationDeadline = $registrationDeadline;
      }
    }
    return $this;
  }

  /**
   * Returns registrationDeadline.
   *
   * @return DateTimeImmutable
   */
  public function getRegistrationDeadline():?DateTimeInterface
  {
    return $this->registrationDeadline;
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
   * Set applications.
   *
   * @param Collection $applications
   *
   * @return Project
   */
  public function setApplications(Collection $applications):Project
  {
    $this->applications = $applications;

    return $this;
  }

  /**
   * Get applications.
   *
   * @return Collection
   */
  public function getApplications():Collection
  {
    return $this->applications;
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
   * @param string|Types\EnumParticipationContext $participationContext
   *
   * @return Collection
   */
  public function getParticipantFields(
    string|Types\EnumParticipationContext $participationContext = Types\EnumParticipationContext::UNRESTRICTED,
  ):Collection {
    // Sorting during load does not work when using Translatable, so trigger load.
    if ($this->participantFields instanceof \OCA\CAFEVDB\Wrapped\Doctrine\ORM\PersistentCollection) {
      $this->participantFields->initialize();
    }
    $fields = $this->participantFields->matching(Criteria::create(true)->orderBy(['tab' => Order::Ascending, 'displayOrder' => Order::Descending]));
    if ($participationContext != Types\EnumParticipationContext::UNRESTRICTED) {
      $fields = $fields->filter(function(ProjectParticipantField $field) use ($participationContext) {
        $context = $field->getParticipationContext();
        return $context == Types\EnumParticipationContext::UNRESTRICTED || $context == $participationContext;
      });
    }
    return $fields;
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
   * @param string|ParticipationContext $participationContext Defaults to
   * UNRESTRICTED, can be used to filter out "real" participants or associated
   * (legal) persons.
   *
   * @return Collection
   */
  public function getParticipants(
    string|ParticipationContext $participationContext = ParticipationContext::UNRESTRICTED,
  ):Collection {
    switch (ParticipationContext::get($participationContext)) {
      case ParticipationContext::UNRESTRICTED:
        return $this->participants;
      case ParticipationContext::ASSOCIATES:
      case ParticipationContext::PARTICIPANTS:
        return $this->participants->filter(
          fn(ProjectParticipant $participant) => $participant->getParticipationContext() == $participationContext,
        );
      default:
        throw new InvalidArgumentException('Invalid participation context "' . $participationContext . '".');
    }
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
   * Set registrationCalendarEvents.
   *
   * @param ?ProjectEvent $registrationCalendarEvent
   *
   * @return Project
   */
  public function setRegistrationCalendarEvent(?ProjectEvent $registrationCalendarEvent): self
  {
    $this->registrationCalendarEvent = $registrationCalendarEvent;

    return $this;
  }

  /**
   * Get registrationCalendarEvents.
   *
   * @return ?ProjectEvent
   */
  public function getRegistrationCalendarEvent(): ?ProjectEvent
  {
    return $this->registrationCalendarEvent;
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
  public function getInstrumentationNumbers(): Collection
  {
    return $this->instrumentationNumbers;
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
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
   * Set compositePayments.
   *
   * @param Collection $compositePayments
   *
   * @return Project
   */
  public function setCompositePayments(Collection $compositePayments):Project
  {
    $this->compositePayments = $compositePayments;

    return $this;
  }

  /**
   * Get compositePayments.
   *
   * @return Collection
   */
  public function getCompositePayments():Collection
  {
    return $this->compositePayments;
  }

  /**
   * Set invoices.
   *
   * @param Collection $invoices
   *
   * @return Project
   */
  public function setInvoices(Collection $invoices):Project
  {
    $this->invoices = $invoices;

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
   * Set financialBalanceDocumentsStorage
   *
   * @param DatabaseStorage $financialBalanceDocumentsStorage
   *
   * @return Project
   */
  public function setFinancialBalanceDocumentsStorage(DatabaseStorage $financialBalanceDocumentsStorage):Project
  {
    $this->financialBalanceDocumentsStorage = $financialBalanceDocumentsStorage;

    return $this;
  }

  /**
   * Get financialBalanceDocumentsStorage.
   *
   * @return null|DatabaseStorage
   */
  public function getFinancialBalanceDocumentsStorage():?DatabaseStorage
  {
    return $this->financialBalanceDocumentsStorage;
  }

  /**
   * Get financialBalanceDocumentsFolder.
   *
   * @return null|DatabaseStorageFolder
   */
  public function getFinancialBalanceDocumentsFolder():?DatabaseStorageFolder
  {
    return empty($this->financialBalanceDocumentsStorage) ? null : $this->financialBalanceDocumentsStorage->getRoot();
  }

  /**
   * Recurse to the balance documents folder and return its contents. Return
   * an empty collection if there is no such folder.
   *
   * @return Collection
   */
  public function getFinancialBalanceSupportingDocuments():Collection
  {
    if (empty($this->financialBalanceDocumentsStorage)) {
      return new ArrayCollection;
    }
    return $this->getFinancialBalanceDocumentsFolder()->getSubFolders();
  }


  /**
   * Set participantInstruments.
   *
   * @param Collection $participantInstruments
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
   * @param Collection $calendarEvents
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
    return $this->payments->count() + $this->invoices->count();
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    return $this->name . '@' . $this->id;
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationContext as ParticipationContext;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipationStatus as ParticipationStatus;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Ramsey\Uuid\UuidInterface;

/**
 * Entity for project participants.
 */
#[ORM\Table(name: 'ProjectParticipants')]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectParticipantsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
#[ORM\EntityListeners([\OCA\CAFEVDB\Listener\ProjectParticipantEntityListener::class])]
class ProjectParticipant implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\GetByUuidTrait;

  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'participants', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Project $project;

  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'projectParticipation', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Musician $musician;

  #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0, 'comment' => 'Participant has confirmed the registration.'])]
  private bool $registration = false;

  #[ORM\Column(type: 'EnumParticipationStatus', nullable: false, options: ['default' => 'regular'])]
  private Types\EnumParticipationStatus $participationStatus;

  /**
   * @var Collection<UuidInterface, ProjectParticipantFieldDatum>
   *
   * Link to extra fields data
   */
  #[ORM\OneToMany(targetEntity: ProjectParticipantFieldDatum::class, indexBy: 'option_key', mappedBy: 'projectParticipant', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
  private Collection $participantFieldsData;

  /**
   * @var Collection<ProjectInstrument>
   *
   * Link in the project instruments, may be more than one per participant.
   */
  #[ORM\OneToMany(targetEntity: ProjectInstrument::class, mappedBy: 'projectParticipant', cascade: ['all'], orphanRemoval: true)]
  private Collection $projectInstruments;

  /**
   * @var Collection<CompositePayment>
   *
   * Link to composit payments, needed for the usage accounting.
   */
  #[ORM\OneToMany(targetEntity: CompositePayment::class, mappedBy: 'projectParticipant')]
  private Collection $payments;

  /**
   * @var Collection<Invoice>
   *
   * Link to composit payments, needed for the usage accounting.
   */
  #[ORM\OneToMany(targetEntity: Invoice::class, mappedBy: 'projectParticipant')]
  private Collection $invoices;

  /**
   * The root-directory entry for the potentially encrypted participant
   * storage. This would also be available through the DatabaseStorages table,
   * but we keep it here for convenient access.
   */
  #[ORM\OneToOne(targetEntity: DatabaseStorage::class, fetch: 'EXTRA_LAZY', cascade: ['all'], orphanRemoval: true)]
  private DatabaseStorage $databaseDocuments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(?Musician $musician = null, ?Project $project = null)
  {
    $this->arrayCTOR();
    $this->invoices = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->participantFieldsData = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
    $this->musician = $musician;
    $this->project = $project;
    $this->participationStatus = $musician ? $musician->getDefaultParticipationStatus() : Types\EnumParticipationStatus::REGULAR();
  }
  // phpcs:enable

  /**
   * Set project.
   *
   * @param null|int|Project $project
   *
   * @return ProjectParticipant
   */
  public function setProject($project):ProjectParticipant
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
   * Set musician.
   *
   * @param null|int|Musician $musician
   *
   * @return ProjectParticipant
   */
  public function setMusician($musician):ProjectParticipant
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
   * Set registration.
   *
   * @param null|bool $registration
   *
   * @return ProjectParticipant
   */
  public function setRegistration(?bool $registration):ProjectParticipant
  {
    $this->registration = $registration;

    return $this;
  }

  /**
   * Get registration.
   *
   * @return bool
   */
  public function getRegistration()
  {
    return $this->registration;
  }

  /**
   * Set participationStatus.
   *
   * @param string|Types\EnumParticipationStatus $participationStatus
   *
   * @return ProjectParticipant
   */
  public function setParticipationStatus(string|Types\EnumParticipationStatus $participationStatus):ProjectParticipant
  {
    if ($this->participationStatus != $participationStatus) {
      $this->participationStatus = is_string($participationStatus)
        ? new Types\EnumParticipationStatus($participationStatus)
        : $participationStatus;
    }
    return $this;
  }

  /**
   * Get participationStatus.
   *
    * @return Types\EnumParticipationStatus
   */
  public function getParticipationStatus():Types\EnumParticipationStatus
  {
    return $this->participationStatus;
  }

  /**
   * Set projectInstruments.
   *
   * @param Collection $projectInstruments
   *
   * @return ProjectParticipant
   */
  public function setProjectInstruments(Collection $projectInstruments):ProjectParticipant
  {
    $this->projectInstruments = $projectInstruments;

    return $this;
  }

  /**
   * Get projectInstruments.
   *
   * @param null|string|InstrumentFamily $family Restrict to instruments which belong to the
   * InstrumentFamily (or given family name). Default \null.
   *
   * @param bool $complement Invert the search and return all instruments not
   * belonging to $family. Default \false.
   *
   * @return Collection
   */
  public function getProjectInstruments(null|string|InstrumentFamily $family = null, bool $complement = false):Collection
  {
    if ($family !== null) {
      // complicated ...
      $familyName = ($family instanceof InstrumentFamily) ? $family->getFamily() : $family;
      $partitions = $this->projectInstruments->partition(
        fn(mixed $key, ProjectInstrument $projectInstrument)
        =>
        $projectInstrument->getInstrument()
                          ->getFamilies()
                          ->exists(fn(mixed $key, InstrumentFamily $thisFamily)
                                   =>
                                   $thisFamily->getFamily() == $familyName
                                   || $thisFamily->getUntranslatedFamily() == $familyName));
      return $complement ? $partitions[1] : $partitions[0];
    } else {
      return $this->projectInstruments;
    }
  }

  /**
   * @return Collection The not-an-instruments instruments (i.e. special
   * roles) of the participant.
   */
  public function getNonInstruments():Collection
  {
    return $this->getProjectInstruments(ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY);
  }

  /**
   * @return Collection Return the real musical instruments of the participant.
   */
  public function getRealInstruments():Collection
  {
    return $this->getProjectInstruments(ProjectInstrument::NOT_AN_INSTRUMENT_FAMILY, complement: true);
  }

  /**
   * @param MusicianInstrument $musicianInstrument
   *
   * @param int $voice
   *
   * @return ProjectParticipant
   */
  public function addProjectInstrument(MusicianInstrument $musicianInstrument, int $voice = ProjectInstrument::UNVOICED):ProjectParticipant
  {
    $musician = $musicianInstrument->getMusician();
    $instrument = $musicianInstrument->getInstrument();
    if ($this->projectInstruments->exists(fn(mixed $key, ProjectInstrument $projectInstrument)
                                          =>
                                          $projectInstrument->getMusician() == $musician
                                          && $projectInstrument->getInstrument() == $instrument
                                          && $projectInstrument->getVoice() == $voice)) {
      return $this;
    }
    $projectInstrument = new ProjectInstrument($this, $musicianInstrument, $voice);
    $instrumentationNumbers = $this->project->getInstrumentationNumbers()->matching(DBUtil::criteriaWhere([
      'project' => $this->project,
      'instrument' => $instrument,
      'voice' => $voice,
    ]));
    if ($instrumentationNumbers->isEmpty()) {
      $instrumentationNumber = new ProjectInstrumentationNumber(
        project: $this->project,
        instrument: $instrument,
        voice: $voice,
      );
    } else {
      $instrumentationNumber = $instrumentationNumbers->first();
    }
    $instrumentationNumber->getProjectInstruments()->set($musician->getId(), $projectInstrument);
    $projectInstrument->setInstrumentationNumber($instrumentationNumber);

    return $this;
  }

  /**
   * Remove the given instrument.
   *
   * @param ProjectInstrument $projectInstrument
   *
   * @return ProjectParticipant
   */
  public function removeProjectInstrument(ProjectInstrument $projectInstrument):ProjectParticipant
  {
    $this->projectInstruments->removeElement($projectInstrument);
    $this->musician->getProjectInstruments()->removeElement($projectInstrument);
    $this->project->getParticipantInstruments()->removeElement($projectInstrument);
    $projectInstrument->getInstrumentationNumber()->getProjectInstruments()->removeElement($projectInstrument);
    $projectInstrument->getMusicianInstrument()->getProjectInstruments()->removeElement($projectInstrument);
    $projectInstrument->getMusicianInstrument()->getInstrument()->getProjectInstruments()->removeElement($projectInstrument);

    return $this;
  }

  /**
   * Set participantFieldsData.
   *
   * @param Collection $participantFieldsData
   *
   * @return ProjectParticipant
   */
  public function setParticipantFieldsData(Collection $participantFieldsData):ProjectParticipant
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
   * Get one specific participant-field datum indexed by its key
   *
   * @param mixed $key Everything which can be converted to an UUID by
   * Uuid::asUuid().
   *
   * @return null|ProjectParticipantFieldDatum
   */
  public function getParticipantFieldsDatum(mixed $key):?ProjectParticipantFieldDatum
  {
    return $this->getByUuid($this->participantFieldsData, $key, 'optionKey');
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return ProjectParticipant
   */
  public function setPayments(Collection $payments):ProjectParticipant
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
   * Set invoices.
   *
   * @param Collection $invoices
   *
   * @return ProjectParticipant
   */
  public function setInvoices(Collection $invoices):ProjectParticipant
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
   * Set databaseDocuments.
   *
   * @param null|DatabaseStorage $databaseDocuments
   *
   * @return ProjectParticipant
   */
  public function setDatabaseDocuments(?DatabaseStorage $databaseDocuments):ProjectParticipant
  {
    $this->databaseDocuments = $databaseDocuments;

    return $this;
  }

  /**
   * Get databaseDocuments.
   *
   * @return DatabaseStorage|null
   */
  public function getDatabaseDocuments():?DatabaseStorage
  {
    return $this->databaseDocuments;
  }

  /**
   * Get the cooked display-name, taking nick-name into account and
   * just using $displayName if that set.
   *
   * @param bool $firstNameFirst If true return "FIRSTNAME LASTNAME" rather
   * than "LASTNAME, FIRSTNAME".
   *
   * @return string
   */
  public function getPublicName(bool $firstNameFirst = false):string
  {
    return $this->musician->getPublicName($firstNameFirst);
  }

  /**
   * Determine if $this is only an associated member or business contact
   * without double-role as musician.
   *
   * @return bool
   */
  public function isOnlyAssociated():bool
  {
    if ($this->participationStatus == ParticipationStatus::ASSOCIATED) {
      return true;
    }
    // in principle this should just have hacked it, but continue ...
    if (!$this->getNonInstruments()->isEmpty() && $this->getRealInstruments()->isEmpty()) {
      // this is in principle a bug: the participation status should have been
      // set to 'associated' by other parts of the code.
      return true;
    }
    return false;
  }

  /**
   * Determine if $this is only a musician without double-role as associated
   * member or business contact.
   *
   * @return bool
   */
  public function isOnlyMusician():bool
  {
    if ($this->participationStatus != ParticipationStatus::ASSOCIATED) {
      return true;
    }
    // in principle this should just have hacked it, but continue ...
    if ($this->getNonInstruments()->isEmpty() && !$this->getRealInstruments()->isEmpty()) {
      // this is in principle a bug: the participation status should have been
      // set to 'associated' by other parts of the code.
      return true;
    }
    return false;
  }

  /**
   * Return the participation context of the participant.
   *
   * @return ParticipationContext
   */
  public function getParticipationContext():ParticipationContext
  {
    if ($this->isOnlyAssociated()) {
      return ParticipationContext::ASSOCIATES();
    }
    if ($this->isOnlyMusician()) {
      return ParticipationContext::PARTICIPANTS();
    }
    return ParticipationContext::UNRESTRICTED();
  }

  /**
   * Return the number of "serious" items which "use" this entity. For
   * project participant this includces the number of payments. In
   * the long run: only open payments/receivables should count.
   *
   * If a participation context is given the usage in incremented by the
   * number of instruments/roles configured for the complementary context.
   *
   * @param string|ParticipationContext $participationContext
   *
   * @return int
   */
  public function usage(string|ParticipationContext $participationContext = ParticipationContext::UNRESTRICTED):int
  {
    $usage = $this->payments->count() + $this->invoices->count();
    switch ($participationContext) {
      case ParticipationContext::ASSOCIATES:
        $usage += (int)$this->isOnlyMusician();
        break;
      case ParticipationContext::PARTICIPANTS:
        $usage += (int)$this->isOnlyAssociated();
        break;
      case ParticipationContext::UNRESTRICTED:
      default:
        break;
    }
    return $usage;
  }

  /**
   * Return a boolean to indicate that this entity is no longer used.
   *
   * @param string|ParticipationContext $participationContext
   *
   * @return bool
   */
  public function unused(string|ParticipationContext $participationContext = ParticipationContext::UNRESTRICTED):bool
  {
    return $this->usage($participationContext) == 0;
  }

  /**
   * Return a boolean to indicate that this field is used.
   *
   * @param string|ParticipationContext $participationContext
   *
   * @return bool
   */
  public function inUse(string|ParticipationContext $participationContext = ParticipationContext::UNRESTRICTED):bool
  {
    return !$this->unused($participationContext);
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    $musicianName = ($this->musician instanceof Musician)
      ? $this->musician->getUserIdSlug() . ':' . $this->musician->getId()
      : '?';
    $projectName = ($this->project instanceof Project)
      ? $this->project->getName() . ':' . $this->project->getId()
      : '?';

    return $musicianName . '@' . $projectName;
  }
}

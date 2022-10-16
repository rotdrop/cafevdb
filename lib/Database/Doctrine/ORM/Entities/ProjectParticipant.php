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

use OCA\CAFEVDB\Events;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Common\Uuid;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Entity for project participants.
 *
 * @ORM\Table(name="ProjectParticipants")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectParticipantsRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 * @ORM\HasLifecycleCallbacks
 */
class ProjectParticipant implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;
  use CAFEVDB\Traits\GetByUuidTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="participants", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $project;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="projectParticipation", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0", "comment"="Participant has confirmed the registration."})
   */
  private $registration = '0';

  /**
   * Link to payments
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="projectParticipant")
   */
  private $payments;

  /**
   * Link to extra fields data
   *
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDatum", indexBy="option_key", mappedBy="projectParticipant", cascade={"persist"}, fetch="EXTRA_LAZY")
   */
  private $participantFieldsData;

  /**
   * Link in the project instruments, may be more than one per participant.
   *
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="projectParticipant", cascade={"all"})
   */
  private $projectInstruments;

  /**
   * @var SepaBankAccount
   *
   * Optional link to a bank account for this project. The account can
   * but need not belong to a debit-mandate. This is the account used
   * for this project.
   * @todo Either remove or use this information.
   *
   * @ORM\ManyToOne(targetEntity="SepaBankAccount")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id", referencedColumnName="musician_id"),
   *   @ORM\JoinColumn(name="bank_account_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $sepaBankAccount = null;

  /**
   * @var SepaDebitMandate
   *
   * Optional link to a SEPA debit-mandate used for this project.
   * @todo Remove, this is a relict from pre-ORM times. Is it really a relict?
   *
   * @ORM\ManyToOne(targetEntity="SepaDebitMandate")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="musician_id", referencedColumnName="musician_id"),
   *   @ORM\JoinColumn(name="debit_mandate_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $sepaDebitMandate = null;

  /**
   * @var DatabaseStorage
   *
   * The root-directory entry for the potentially encrypted participant
   * storage. This would also be available through the DatabaseStorages table,
   * but we keep it here for convenient access.
   *
   * @ORM\OneToOne(targetEntity="DatabaseStorage", fetch="EXTRA_LAZY", cascade={"all"}, orphanRemoval=true)
   */
  private $databaseDocuments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->payments = new ArrayCollection();
    $this->participantFieldsData = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
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
   * @return Collection
   */
  public function getProjectInstruments():Collection
  {
    return $this->projectInstruments;
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
   * Set sepaBankAccount.
   *
   * @param null|SepaBankAccount $sepaBankAccount
   *
   * @return ProjectParticipant
   */
  public function setSepaBankAccount(?SepaBankAccount $sepaBankAccount):ProjectParticipant
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
   * @param null|SepaDebitMandate $sepaDebitMandate
   *
   * @return ProjectParticipant
   */
  public function setSepaDebitMandate(?SepaDebitMandate $sepaDebitMandate):ProjectParticipant
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
   * @var null|array
   *
   * The array of changed field values.
   */
  private $preUpdateValue = [];

  /**
   * @param Event\PreUpdateEventArgs $event
   *
   * @return void
   *
   * @ORM\PreUpdate
   */
  public function preUpdate(Event\PreUpdateEventArgs $event)
  {
    $field = 'registration';
    if ($event->hasChangedField($field)) {
      /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
      $entityManager = EntityManager::getDecorator($event->getEntityManager());
      $oldValue = $event->getOldValue($field);
      $entityManager->dispatchEvent(new Events\PreChangeRegistrationConfirmation($this, !empty($oldValue), !empty($event->getNewValue($field))));
      $this->preUpdateValue[$field] = $oldValue;
    }
  }

  /**
   * @param Event\LifecycleEventArgs $event
   *
   * @return void
   *
   * @ORM\PostUpdate
   */
  public function postUpdate(Event\LifecycleEventArgs $event)
  {
    $field = 'registration';
    if (array_key_exists($field, $this->preUpdateValue)) {
      /** @var OCA\CAFEVDB\Database\EntityManager $entityManager */
      $entityManager = EntityManager::getDecorator($event->getEntityManager());
      $entityManager->dispatchEvent(new Events\PostChangeRegistrationConfirmation($this, !empty($this->preUpdateValue[$field])));
      unset($this->preUpdateValue[$field]);
    }
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

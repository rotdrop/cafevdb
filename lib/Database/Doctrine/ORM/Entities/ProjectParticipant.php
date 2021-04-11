<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Entity for project participants.
 *
 * @ORM\Table(name="ProjectParticipants")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectParticipantsRepository")
 * @Gedmo\Loggable
 * @Gedmo\SoftDeleteable(fieldName="deleted")
 */
class ProjectParticipant implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;

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
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="1"})
   */
  private $debitnote = '1';

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=65535, nullable=true, options={"comment"="Allgemeine Bermerkungen"})
   */
  private $remarks;

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

  public function __construct() {
    $this->arrayCTOR();
    $this->payments = new ArrayCollection();
    $this->participantFieldsData = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
  }

  /**
   * Set project.
   *
   * @param int $project
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
  public function getProject()
  {
    return $this->project;
  }

  /**
   * Set musician.
   *
   * @param int $musician
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
  public function getMusician()
  {
    return $this->musician;
  }

  /**
   * Set registration.
   *
   * @param bool $registration
   *
   * @return ProjectParticipant
   */
  public function setRegistration($registration):ProjectParticipant
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
   * Set debitnote.
   *
   * @param SepaDebitNote $debitnote
   *
   * @return ProjectParticipant
   */
  public function setDebitnote($debitnote):ProjectParticipant
  {
    $this->debitnote = $debitnote;

    return $this;
  }

  /**
   * Get debitnote.
   *
   * @return SepaDebitNote
   */
  public function getDebitnote()
  {
    return $this->debitnote;
  }

  /**
   * Set remarks.
   *
   * @param null|string $remarks
   *
   * @return ProjectParticipant
   */
  public function setRemarks($remarks):ProjectParticipant
  {
    $this->remarks = $remarks;

    return $this;
  }

  /**
   * Get remarks.
   *
   * @return string
   */
  public function getRemarks():?string
  {
    return $this->remarks;
  }

  /**
   * Set projectInstruments.
   *
   * @param bool $projectInstruments
   *
   * @return ProjectParticipant
   */
  public function setProjectInstruments($projectInstruments):ProjectParticipant
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
  public function setParticipantFieldsData($participantFieldsData):ProjectParticipant
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
   * @return null|ProjectParticipantFieldDatum
   */
  public function getParticipantFieldsDatum($key):?ProjectParticipantFieldDatum
  {
    if (empty($key = Uuid::uuidBytes($key))) {
      return null;
    }
    $datum = $this->participantFieldsData->get($key);
    if (!empty($datum)) {
      return $datum;
    }
    foreach ($this->participantFieldsData as $datum) {
      if ($datum->getOptionKey()->getBytes() == $key) {
        return $datum;
      }
    }
    return null;
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return ProjectParticipant
   */
  public function setPayments($payments):ProjectParticipant
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

}

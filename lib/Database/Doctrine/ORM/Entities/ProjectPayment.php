<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * ProjectPayments
 *
 * @ORM\Table(name="ProjectPayments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectPaymentsRepository")
 */
class ProjectPayment implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
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
   * @var string
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $amount = '0.00';

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=1024, nullable=false)
   */
  private $subject;

  /**
   * @var ProjectParticipantFieldDatum
   *
   * @ORM\ManyToOne(targetEntity="ProjectParticipantFieldDatum", inversedBy="payments")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="field_id", referencedColumnName="field_id", nullable=false),
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id", nullable=false),
   *   @ORM\JoinColumn(name="musician_id", referencedColumnName="musician_id", nullable=false),
   *   @ORM\JoinColumn(name="receivable_key", referencedColumnName="option_key", nullable=false)
   * )
   */
  private $receivable;

  /**
   * @var ProjectParticipantFieldDataOption
   *
   * @ORM\ManyToOne(targetEntity="ProjectParticipantFieldDataOption", inversedBy="payments")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="field_id", referencedColumnName="field_id", nullable=false),
   *   @ORM\JoinColumn(name="receivable_key", referencedColumnName="key", nullable=false)
   * )
   */
  private $receivableOption;

  /**
   * @var CompositePayment
   *
   * @ORM\ManyToOne(targetEntity="CompositePayment", inversedBy="projectPayments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(nullable=false)
   * )
   *
   * Promote any changes to the parent.
   * @Gedmo\Timestampable(on={"update","create","delete"}, timestampField="updated")
   */
  private $compositePayment;

  /**
   * @var Project
   *
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="payments", cascade={"persist"}, fetch="EXTRA_LAZY")
   */
  private $project;

  /**
   * @var Musician
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="payments", fetch="EXTRA_LAZY")
   */
  private $musician;

  /**
   * @ORM\ManyToOne(targetEntity="ProjectParticipant", inversedBy="payments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id", nullable=false),
   *   @ORM\JoinColumn(name="musician_id",referencedColumnName="musician_id", nullable=false)
   * )
   */
  private $projectParticipant;

  /**
   * @var DatabaseStorageFolder
   *
   * @ORM\ManyToOne(targetEntity="DatabaseStorageFolder", fetch="EXTRA_LAZY")
   */
  private $balanceDocumentsFolder;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
  }

  /**
   * Set id.
   *
   * @param null|int $id
   *
   * @return ProjectPayment
   */
  public function setId(?int $id):ProjectPayment
  {
    if (empty($id)) {
      $this->id = null; // flag auto-increment on insert
    }
    return $this;
  }

  /**
   * Get id.
   *
   * @return int
   */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * Set compositePayment.
   *
   * @param null|int|CompositePayment $compositePayment
   *
   * @return ProjectPayment
   */
  public function setCompositePayment(mixed $compositePayment):ProjectPayment
  {
    $this->compositePayment = $compositePayment;

    return $this;
  }

  /**
   * Get compositePayment.
   *
   * @return CompositePayment
   */
  public function getCompositePayment():?CompositePayment
  {
    return $this->compositePayment;
  }

  /**
   * Set projectParticipant.
   *
   * @param null|ProjectParticipant $projectParticipant
   *
   * @return ProjectPayment
   */
  public function setProjectParticipant(?ProjectParticipant $projectParticipant):ProjectPayment
  {
    $this->projectParticipant = $projectParticipant;

    return $this;
  }

  /**
   * Get projectParticipant.
   *
   * @return ProjectParticipant
   */
  public function getProjectParticipant():?ProjectParticipant
  {
    return $this->projectParticipant;
  }

  /**
   * Set project.
   *
   * @param null|int|Project $project
   *
   * @return ProjectPayment
   */
  public function setProject(mixed $project):ProjectPayment
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
   * @return ProjectPayment
   */
  public function setMusician(mixed $musician):ProjectPayment
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
   * Set amount.
   *
   * @param float|null $amount
   *
   * @return ProjectPayment
   */
  public function setAmount(?float $amount):ProjectPayment
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
   * Set subject.
   *
   * @param null|string $subject
   *
   * @return ProjectPayment
   */
  public function setSubject(?string $subject):ProjectPayment
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return string
   */
  public function getSubject():?string
  {
    return $this->subject;
  }

  /**
   * Set receivable.
   *
   * @param ProjectParticipantFieldDatum $receivable
   *
   * @return ProjectPayment
   */
  public function setReceivable(ProjectParticipantFieldDatum $receivable):ProjectPayment
  {
    if (!empty($this->balanceDocumentsFolder) && !empty($this->receivable)) {
      $supportingDocument = $this->receivable->getSupportingDocument();
      if (!empty($supportingDocument)) {
        $this->balanceDocumentsFolder->removeDocument($supportingDocument->getFile(), $supportingDocument->getName());
      }
    }

    $this->receivable = $receivable;

    if (!empty($this->balanceDocumentsFolder) && !empty($this->receivable)) {
      $supportingDocument = $this->receivable->getSupportingDocument();
      if (!empty($supportingDocument)) {
        $this->balanceDocumentsFolder->addDirEntry($supportingDocument->getFile(), $supportingDocument->getName());
      }
    }

    return $this;
  }

  /**
   * Get receivable.
   *
   * @return ProjectParticipantFieldDatum
   */
  public function getReceivable():?ProjectParticipantFieldDatum
  {
    return $this->receivable;
  }

  /**
   * Set receivableOption.
   *
   * @param null|ProjectParticipantFieldDataOption $receivableOption
   *
   * @return ProjectPayment
   */
  public function setReceivableOption(?ProjectParticipantFieldDataOption $receivableOption):ProjectPayment
  {
    $this->receivableOption = $receivableOption;

    return $this;
  }

  /**
   * Get receivableOption.
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function getReceivableOption():?ProjectParticipantFieldDataOption
  {
    return $this->receivableOption;
  }

  /**
   * Set balanceDocumentsFolder.
   *
   * @param DatabaseStorageFolder $balanceDocumentsFolder
   *
   * @return ProjectPayment
   */
  public function setBalanceDocumentsFolder(?DatabaseStorageFolder $balanceDocumentsFolder):ProjectPayment
  {
    if (!empty($this->balanceDocumentsFolder) && !empty($this->receivable)) {
      $supportingDocument = $this->receivable->getSupportingDocument();
      if (!empty($supportingDocument)) {
        $this->balanceDocumentsFolder->removeDocument($supportingDocument->getFile(), $supportingDocument->getName());
      }
    }

    $this->balanceDocumentsFolder = $balanceDocumentsFolder;

    if (!empty($this->balanceDocumentsFolder) && !empty($this->receivable)) {
      $supportingDocument = $this->receivable->getSupportingDocument();
      if (!empty($supportingDocument)) {
        $this->balanceDocumentsFolder->addDocument($supportingDocument->getFile(), $supportingDocument->getName());
      }
    }

    return $this;
  }

  /**
   * Get balanceDocumentsFolder.
   *
   * @return ?DatabaseStorageFolder
   */
  public function getBalanceDocumentsFolder():?DatabaseStorageFolder
  {
    return $this->balanceDocumentsFolder;
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    return $this->toArray();
  }
}

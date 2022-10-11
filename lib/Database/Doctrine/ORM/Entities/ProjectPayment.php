<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * ProjectPayments
 *
 * @ORM\Table(name="ProjectPayments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectPaymentsRepository")
 */
class ProjectPayment implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

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
   * @var ProjectBalanceSupportingDocument
   *
   * @ORM\ManyToOne(targetEntity="ProjectBalanceSupportingDocument", inversedBy="projectPayments", fetch="EXTRA_LAZY")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *   @ORM\JoinColumn(name="balance_document_sequence", referencedColumnName="sequence", nullable=true)
   * )
   */
  private $oldProjectBalanceSupportingDocument;

  /**
   * @var DatabaseStorageDirectory
   *
   * @ORM\ManyToOne(targetEntity="DatabaseStorageDirectory", inversedBy="projectPayments", fetch="EXTRA_LAZY")
   */
  private $balanceDocumentsFolder;

  public function __construct() {
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
   * @param int $compositePayment
   *
   * @return ProjectPayment
   */
  public function setCompositePayment($compositePayment):ProjectPayment
  {
    $this->compositePayment = $compositePayment;

    return $this;
  }

  /**
   * Get compositePayment.
   *
   * @return CompositePayment
   */
  public function getCompositePayment()
  {
    return $this->compositePayment;
  }

  /**
   * Set projectParticipant.
   *
   * @param int $projectParticipant
   *
   * @return ProjectPayment
   */
  public function setProjectParticipant($projectParticipant):ProjectPayment
  {
    $this->projectParticipant = $projectParticipant;

    return $this;
  }

  /**
   * Get projectParticipant.
   *
   * @return ProjectParticipant
   */
  public function getProjectParticipant()
  {
    return $this->projectParticipant;
  }

  /**
   * Set project.
   *
   * @param int $project
   *
   * @return ProjectPayment
   */
  public function setProject($project):ProjectPayment
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
   * @return ProjectPayment
   */
  public function setMusician($musician):ProjectPayment
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
  public function getAmount():float
  {
    return $this->amount;
  }

  /**
   * Set subject.
   *
   * @param string $subject
   *
   * @return ProjectPayment
   */
  public function setSubject($subject):ProjectPayment
  {
    $this->subject = $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return string
   */
  public function getSubject()
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
        $this->balanceDocumentsFolder->removeDocument($supportingDocument);
      }
    }

    $this->receivable = $receivable;

    if (!empty($this->balanceDocumentsFolder) && !empty($this->receivable)) {
      $supportingDocument = $this->receivable->getSupportingDocument();
      if (!empty($supportingDocument)) {
        $this->balanceDocumentsFolder->addDocument($supportingDocument);
      }
    }

    return $this;
  }

  /**
   * Get receivable.
   *
   * @return ProjectParticipantFieldDatum
   */
  public function getReceivable():ProjectParticipantFieldDatum
  {
    return $this->receivable;
  }

  /**
   * Set receivableOption.
   *
   * @param ProjectParticipantFieldDataOption $receivableOption
   *
   * @return ProjectPayment
   */
  public function setReceivableOption($receivableOption):ProjectPayment
  {
    $this->receivableOption = $receivableOption;

    return $this;
  }

  /**
   * Get receivableOption.
   *
   * @return ProjectParticipantFieldDataOption
   */
  public function getReceivableOption()
  {
    return $this->receivableOption;
  }

  /**
   * Set balanceDocumentsFolder.
   *
   * @param DatabaseStorageDirectory $balanceDocumentsFolder
   *
   * @return ProjectPayment
   */
  public function setBalanceDocumentsFolder(?DatabaseStorageDirectory $balanceDocumentsFolder):ProjectPayment
  {
    if (!empty($this->balanceDocumentsFolder) && !empty($this->receivable)) {
      $supportingDocument = $this->receivable->getSupportingDocument();
      if (!empty($supportingDocument)) {
        $this->balanceDocumentsFolder->removeDocument($supportingDocument);
      }
    }

    $this->balanceDocumentsFolder = $balanceDocumentsFolder;

    if (!empty($this->balanceDocumentsFolder) && !empty($this->receivable)) {
      $supportingDocument = $this->receivable->getSupportingDocument();
      if (!empty($supportingDocument)) {
        $this->balanceDocumentsFolder->addDocument($supportingDocument);
      }
    }

    return $this;
  }

  /**
   * Get balanceDocumentsFolder.
   *
   * @return ?DatabaseStorageDirectory
   */
  public function getBalanceDocumentsFolder():?DatabaseStorageDirectory
  {
    return $this->balanceDocumentsFolder;
  }

  /** \JsonSerializable interface */
  public function jsonSerialize():array
  {
    return $this->toArray();
  }
}

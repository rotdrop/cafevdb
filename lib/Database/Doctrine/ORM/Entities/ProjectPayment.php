<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectPayments
 *
 * @ORM\Table(name="ProjectPayments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectPaymentsRepository")
 */
class ProjectPayment implements \ArrayAccess
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
   * @ORM\ManyToOne(targetEntity="ProjectParticipantFieldDataOption", inversedBy="payments")
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="field_id", referencedColumnName="field_id", nullable=false),
   *   @ORM\JoinColumn(name="receivable_key", referencedColumnName="key", nullable=false)
   * )
   */
  private $receivableOption;

  /**
   * @ORM\ManyToOne(targetEntity="CompositePayment", inversedBy="projectPayments", fetch="EXTRA_LAZY")
   */
  private $compositePayment;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="payments", cascade={"persist"}, fetch="EXTRA_LAZY")
   */
  private $project;

  /**
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
   * @var EncryptedFile
   *
   * Optional. ATM only used for particular auto-generated monetary fields.
   *
   * @ORM\OneToOne(targetEntity="EncryptedFile", fetch="EXTRA_LAZY")
   */
  private $supportingDocument;

  public function __construct() {
    $this->arrayCTOR();
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
   * @param string $receivable
   *
   * @return ProjectPayment
   */
  public function setReceivable($receivable):ProjectPayment
  {
    $this->receivable = $receivable;

    return $this;
  }

  /**
   * Get receivable.
   *
   * @return string
   */
  public function getReceivable()
  {
    return $this->receivable;
  }

  /**
   * Set receivableOption.
   *
   * @param string $receivableOption
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
   * @return string
   */
  public function getReceivableOption()
  {
    return $this->receivableOption;
  }
}

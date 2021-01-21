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
use Doctrine\Common\Collections\ArrayCollection;

/**
 * DebitNotes
 *
 * @ORM\Table(name="DebitNotes")
 * @ORM\Entity
 */
class DebitNote implements \ArrayAccess
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
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="sepaDebitMandates", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(nullable=false)
   */
  private $project;

  /**
   * @ORM\OneToOne(targetEntity="DebitNoteData", mappedBy="debitNote", fetch="EXTRA_LAZY")
   */
  private $debitNoteData;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $dateIssued;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="date", nullable=false)
   */
  private $submissionDeadline;

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(type="date", nullable=true)
   */
  private $submitDate;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="date", nullable=false)
   */
  private $dueDate;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $job;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $submissionEventUri;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $submissionTaskUri;

  /**
   * @var int
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"comment"="Cloud Calendar Object URI"})
   */
  private $dueEventUri;

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
   * Set project.
   *
   * @param int $project
   *
   * @return DebitNotes
   */
  public function setProject($project)
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return int
   */
  public function getProject()
  {
    return $this->project;
  }

  /**
   * Set dateIssued.
   *
   * @param \DateTime $dateIssued
   *
   * @return DebitNotes
   */
  public function setDateIssued($dateIssued)
  {
    $this->dateIssued = $dateIssued;

    return $this;
  }

  /**
   * Get dateIssued.
   *
   * @return \DateTime
   */
  public function getDateIssued()
  {
    return $this->dateIssued;
  }

  /**
   * Set submissionDeadline.
   *
   * @param \DateTime $submissionDeadline
   *
   * @return DebitNotes
   */
  public function setSubmissionDeadline($submissionDeadline)
  {
    $this->submissionDeadline = $submissionDeadline;

    return $this;
  }

  /**
   * Get submissionDeadline.
   *
   * @return \DateTime
   */
  public function getSubmissionDeadline()
  {
    return $this->submissionDeadline;
  }

  /**
   * Set submitDate.
   *
   * @param \DateTime|null $submitDate
   *
   * @return DebitNotes
   */
  public function setSubmitDate($submitDate = null)
  {
    $this->submitDate = $submitDate;

    return $this;
  }

  /**
   * Get submitDate.
   *
   * @return \DateTime|null
   */
  public function getSubmitDate()
  {
    return $this->submitDate;
  }

  /**
   * Set dueDate.
   *
   * @param \DateTime $dueDate
   *
   * @return DebitNotes
   */
  public function setDueDate($dueDate)
  {
    $this->dueDate = $dueDate;

    return $this;
  }

  /**
   * Get dueDate.
   *
   * @return \DateTime
   */
  public function getDueDate()
  {
    return $this->dueDate;
  }

  /**
   * Set job.
   *
   * @param string $job
   *
   * @return DebitNotes
   */
  public function setJob($job)
  {
    $this->job = $job;

    return $this;
  }

  /**
   * Get job.
   *
   * @return string
   */
  public function getJob()
  {
    return $this->job;
  }

  /**
   * Set submissionEventUri.
   *
   * @param int $submissionEventUri
   *
   * @return DebitNotes
   */
  public function setSubmissionEventUri($submissionEventUri)
  {
    $this->submissionEventUri = $submissionEventUri;

    return $this;
  }

  /**
   * Get submissionEventUri.
   *
   * @return int
   */
  public function getSubmissionEventUri()
  {
    return $this->submissionEventUri;
  }

  /**
   * Set submissionTaskUri.
   *
   * @param int $submissionTaskUri
   *
   * @return DebitNotes
   */
  public function setSubmissionTaskUri($submissionTaskUri)
  {
    $this->submissionTaskUri = $submissionTaskUri;

    return $this;
  }

  /**
   * Get submissionTaskUri.
   *
   * @return int
   */
  public function getSubmissionTaskUri()
  {
    return $this->submissionTaskUri;
  }

  /**
   * Set dueEventUri.
   *
   * @param int $dueEventUri
   *
   * @return DebitNotes
   */
  public function setDueEventUri($dueEventUri)
  {
    $this->dueEventUri = $dueEventUri;

    return $this;
  }

  /**
   * Get dueEventUri.
   *
   * @return int
   */
  public function getDueEventUri()
  {
    return $this->dueEventUri;
  }
}

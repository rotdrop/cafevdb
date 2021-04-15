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
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * SepaDebitNote
 *
 * This actually models a batch collection
 *
 * @ORM\Table(name="SepaDebitNotes")
 * @ORM\Entity
 */
class SepaDebitNote implements \ArrayAccess
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
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="debitNotes", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(nullable=false)
   */
  private $project;

  /**
   * @ORM\OneToOne(targetEntity="SepaDebitNoteData", mappedBy="debitNote", cascade={"all"}, orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $debitNoteData;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $dateIssued;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $submissionDeadline;

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $submitDate;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="date_immutable", nullable=false)
   */
  private $dueDate;

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
   * @var ArrayCollection
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="debitNote", orphanRemoval=true, cascade={"all"}, fetch="EXTRA_LAZY")
   */
  private $projectPayments;

  public function __construct() {
    $this->arrayCTOR();
    $this->projectPayments = new ArrayCollection();
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
   * Set project.
   *
   * @param int $project
   *
   * @return SepaDebitNote
   */
  public function setProject($project):SepaDebitNote
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
   * Set sepaDebitNoteData.
   *
   * @param int $sepaDebitNoteData
   *
   * @return SepaDebitNote
   */
  public function setSepaDebitNoteData($sepaDebitNoteData):SepaDebitNote
  {
    $this->sepaDebitNoteData = $sepaDebitNoteData;

    return $this;
  }

  /**
   * Get sepaDebitNoteData.
   *
   * @return int
   */
  public function getSepaDebitNoteData()
  {
    return $this->sepaDebitNoteData;
  }

  /**
   * Set dateIssued.
   *
   * @param \DateTime $dateIssued
   *
   * @return SepaDebitNote
   */
  public function setDateIssued($dateIssued):SepaDebitNote
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
   * @return SepaDebitNote
   */
  public function setSubmissionDeadline($submissionDeadline):SepaDebitNote
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
   * @return SepaDebitNote
   */
  public function setSubmitDate($submitDate = null):SepaDebitNote
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
   * @return SepaDebitNote
   */
  public function setDueDate($dueDate):SepaDebitNote
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
   * Set submissionEventUri.
   *
   * @param int $submissionEventUri
   *
   * @return SepaDebitNote
   */
  public function setSubmissionEventUri($submissionEventUri):SepaDebitNote
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
   * @return SepaDebitNote
   */
  public function setSubmissionTaskUri($submissionTaskUri):SepaDebitNote
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
   * @return SepaDebitNote
   */
  public function setDueEventUri($dueEventUri):SepaDebitNote
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

  /**
   * Set projectPayments.
   *
   * @param Collection $projectPayments
   *
   * @return SepaDebitNote
   */
  public function setProjectPayments(Collection $projectPayments):SepaDebitNote
  {
    $this->projectPayments = $projectPayments;

    return $this;
  }

  /**
   * Get projectPayments.
   *
   * @return Collection
   */
  public function getProjectPayments():Collection
  {
    return $this->projectPayments;
  }

  /**
   * Return the number of related ProjectPayment entities.
   */
  public function usage():int
  {
    return $this->projectPayments->count();
  }
}

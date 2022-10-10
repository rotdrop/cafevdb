<?php
/**
 * Orchestra member, musician and project management application.
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

use \RuntimeException;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

/**
 * ProjectBalanceSupportingDocument
 *
 * A table which combines documents from the encrypted Files table to form a
 * compound supporting, enumerated document in order to support the financial
 * post-process labor.
 *
 * @ORM\Table(name="ProjectBalanceSupportingDocuments")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectBalanceSupportingDocumentsRepository")
 */
class ProjectBalanceSupportingDocument implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="financialBalanceSupportingDocuments", fetch="EXTRA_LAZY")
   * @ORM\Id
   * @Gedmo\Timestampable(
   *   on={"update","change","create","delete"},
   *   field="documents",
   *   timestampField="financialBalanceSupportingDocumentsChanged"
   * )
   */
  private $project;

  /**
   * @var int
   *
   * This is a POSITIVE per-musician sequence count. It currently is
   * incremented using
   * \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\PerProjectSequenceTrait
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   * _AT_ORM\GeneratedValue(strategy="CUSTOM")
   * _AT_ORM\CustomIdGenerator(class="OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\PerProjectSequenceGenerator")
   */
  private $sequence;

  /**
   * @var Collection
   *
   * orphan removal would be nice, but make it more difficult to change the sequence number.
   * @ORM\ManyToMany(targetEntity="EncryptedFile", inversedBy="projectBalanceSupportingDocuments", cascade={"persist"}, indexBy="id", fetch="EXTRA_LAZY")
   * @ORM\JoinTable(
   *   joinColumns={
   *     @ORM\JoinColumn(name="project_id", referencedColumnName="project_id"),
   *     @ORM\JoinColumn(name="sequence", referencedColumnName="sequence")
   *   },
   * )
   */
  private $documents;

  /**
   * @var \DateTimeImmutable
   *
   * Tracks changes in the supporting documents collection, in particular
   * deletions.
   *
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  private $documentsChanged;

  /**
   * @var Collection
   *
   * Optional linked project payments.
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="oldProjectBalanceSupportingDocument", cascade={"persist"}, fetch="EXTRA_LAZY")
   */
  private $projectPayments;

  /**
   * @var Collection
   *
   * Optional linked composite payments.
   *
   * @ORM\OneToMany(targetEntity="CompositePayment", mappedBy="oldProjectBalanceSupportingDocument", cascade={"persist"}, fetch="EXTRA_LAZY")
   */
  private $compositePayments;

  public function __construct(?Project $project = null, ?int $sequence = null) {
    $this->arrayCTOR();
    $this->documents = new ArrayCollection();
    $this->projectPayments = new ArrayCollection();
    $this->compositePayments = new ArrayCollection();
    $this->setProject($project);
    $this->setSequence($sequence);
  }

  /**
   * Set project.
   *
   * @param Project|null $project
   *
   * @return SepaBankAccount
   */
  public function setProject($project = null):ProjectBalanceSupportingDocument
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project|null|int
   */
  public function getProject()
  {
    return $this->project;
  }

  /**
   * Set sequence.
   *
   * @param int $sequence
   *
   * @return SepaBankAccount
   */
  public function setSequence(?int $sequence = null):ProjectBalanceSupportingDocument
  {
    $this->sequence = $sequence;

    return $this;
  }

  /**
   * Get sequence.
   *
   * @return int|null
   */
  public function getSequence():?int
  {
    return $this->sequence;
  }

  /**
   * Set documents.
   *
   * @param Collection $documents
   *
   * @return SepaDebitMandate
   */
  public function setDocuments(?Collection $documents):ProjectBalanceSupportingDocument
  {
    if (empty($documents)) {
      $documents = new ArrayCollection;
    }
    $this->documents = $documents;

    return $this;
  }

  /**
   * Get documents.
   *
   * @return Collection
   */
  public function getDocuments():Collection
  {
    return $this->documents;
  }

  /**
   * Add the given file to the list of supporting documents if not already present.
   *
   * This increases the link-count of the file and add this entity to the
   * container collection of the encrypted file.
   *
   * @param EncryptedFile $file
   *
   * @return ProjectBalanceSupportingDocument
   */
  public function addDocument(EncryptedFile $file):ProjectBalanceSupportingDocument
  {
    if (empty($file->getId())) {
      throw new RuntimeException('The supporting document does not have an id.');
    }
    if (!$this->documents->containsKey($file->getId())) {
      $file->link();
      $file->addProjectBalanceSupportingDocument($this);
      $this->documents->set($file->getId(), $file);
    }
    return $this;
  }

  /**
   * Remove the given file from the list of supporting documents.
   *
   * This also decrements the link count of the file and removes $this from
   * the collection of document containes of the encrypted file entity.
   *
   * @param EncryptedFile $file
   *
   * @return ProjectBalanceSupportingDocument
   */
  public function removeDocument(EncryptedFile $file):ProjectBalanceSupportingDocument
  {
    if ($this->documents->contains($file)) {
      $this->documents->removeElement($file);
      $file->removeProjectBalanceSupportingDocument($this);
      $file->unlink();
    }
    return $this;
  }

  /**
   * Get documentsChanged time-stamp.
   *
   * @return \DateTimeInterface
   */
  public function getDocumentsChanged():?\DateTimeInterface
  {
    return $this->documentsChanged ?? $this->updated;
  }

  /**
   * Set projectPayments.
   *
   * @param Collection $projectPayments
   *
   * @return SepaDebitMandate
   */
  public function setProjectPayments(?Collection $projectPayments):ProjectBalanceSupportingDocument
  {
    if (empty($projectPayments)) {
      $projectPayments = new ArrayCollection;
    }
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
   * Set compositePayments.
   *
   * @param Collection $compositePayments
   *
   * @return SepaDebitMandate
   */
  public function setCompositePayments(?Collection $compositePayments):ProjectBalanceSupportingDocument
  {
    if (empty($compositePayments)) {
      $compositePayments = new ArrayCollection;
    }
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
}

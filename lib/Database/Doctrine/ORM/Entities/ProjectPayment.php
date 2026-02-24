<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Common\DecimalRationalMonetary as MonetaryNumberType;
use OCA\CAFEVDB\Common\RationalNumber;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\DecimalRationalMonetaryType as MonetaryDatabaseType;
use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * ProjectPayments
 */
#[ORM\Table(name: DatabaseTables::PROJECT_PAYMENTS_TABLE)]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectPaymentsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProjectPayment implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\AutoIncrementTrait;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * Project payments are actually also expenses in which case the sign of the
   * payment is negative. Receptions are positive.
   */
  #[ORM\Column(type: MonetaryDatabaseType::NAME, nullable: false, options: ['default' => '0.00'])]
  private MonetaryNumberType $amount;

  /**
   * Flags the payment as a donation. The supporting document of the
   * corresponding receivable will be the corresponding certificate.
   */
  #[ORM\Column(type: 'boolean', nullable: false, options: ['default' => 0])]
  private bool $isDonation = false;

  #[ORM\Column(type: 'string', length: 1024, nullable: true)]
  private ?string $subject = null;

  /**
   * Each project payment must be backed by a "receivable".
   */
  #[ORM\JoinColumn(name: 'field_id', referencedColumnName: 'field_id', nullable: false)]
  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false)]
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'receivable_key', referencedColumnName: 'option_key', nullable: false)]
  #[ORM\ManyToOne(targetEntity: ProjectParticipantFieldDatum::class, inversedBy: 'payments')]
  private ProjectParticipantFieldDatum $receivable;

  #[ORM\JoinColumn(name: 'field_id', referencedColumnName: 'field_id', nullable: false)]
  #[ORM\JoinColumn(name: 'receivable_key', referencedColumnName: 'key', nullable: false)]
  #[ORM\ManyToOne(targetEntity: ProjectParticipantFieldDataOption::class, inversedBy: 'payments')]
  private ProjectParticipantFieldDataOption $receivableOption;

  /**
   * Composite payments group several payments together.
   */
  #[ORM\JoinColumn(nullable: false)]
  #[ORM\ManyToOne(targetEntity: CompositePayment::class, inversedBy: 'projectPayments', fetch: 'EXTRA_LAZY')]
  #[Gedmo\Timestampable(on: ['update', 'create', 'delete'], timestampField: 'updated')]
  private CompositePayment $compositePayment;

  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'payments', cascade: ['persist'], fetch: 'EXTRA_LAZY')]
  private Project $project;

  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'payments', fetch: 'EXTRA_LAZY')]
  private Musician $musician;

  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'project_id', nullable: false)]
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\ManyToOne(targetEntity: ProjectParticipant::class, fetch: 'EXTRA_LAZY')]
  private ProjectParticipant $projectParticipant;

  /**
   * Project payments may be supported by documents below the project balance,
   * or by individual supporting documents which are tied to the receivables.
   */
  #[ORM\ManyToOne(targetEntity: DatabaseStorageFolder::class, fetch: 'EXTRA_LAZY')]
  private ?DatabaseStorageFolder $balanceDocumentsFolder = null;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->arrayCTOR();
    $this->setAmount(0);
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
   * @param ProjectParticipant $projectParticipant
   *
   * @return ProjectPayment
   */
  public function setProjectParticipant(ProjectParticipant $projectParticipant):ProjectPayment
  {
    $this->projectParticipant = $projectParticipant;
    $this->project = $projectParticipant->getProject();
    $this->musician = $projectParticipant->getMusician();

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
   * @param int|float|string|RationalNumber $amount
   *
   * @return ProjectPayment
   */
  public function setAmount(int|float|string|RationalNumber $amount): self
  {
    $this->amount = MonetaryNumberType::create($amount);

    return $this;
  }

  /**
   * Get amount.
   *
   * @return MonetaryNumberType
   */
  public function getAmount(): MonetaryNumberType
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

    $autoSubject = ($this->receivable ?? null)?->paymentReference();

    $this->subject = ($subject == $autoSubject) ? null : $subject;

    return $this;
  }

  /**
   * Get subject.
   *
   * @return string
   */
  public function getSubject():?string
  {
    return $this->subject ?? $this->receivable->paymentReference();
  }

  /**
   * Set isDonation.
   *
   * @param bool $isDonation
   *
   * @return ProjectPayment
   */
  public function setIsDonation(bool $isDonation):ProjectPayment
  {
    $this->isDonation = $isDonation;

    return $this;
  }

  /**
   * Get isDonation.
   *
   * @return bool
   */
  public function getIsDonation():?bool
  {
    return $this->isDonation;
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
        $this->balanceDocumentsFolder->addDirEntry($supportingDocument);
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
    return $this->receivable ?? null;
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

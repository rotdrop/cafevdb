<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine
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

use DateTimeInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\PageRenderer\DatabaseTables;
use OCA\CAFEVDB\Wrapped\Carbon\CarbonImmutable as DateTimeImmutable;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

/**
 * SepaDebitMandate
 */
#[ORM\Table(name: DatabaseTables::SEPA_DEBIT_MANDATES_TABLE)]
#[ORM\Index(columns: ['musician_id', 'bank_account_sequence', 'project_id'])]
#[ORM\UniqueConstraint(columns: ['mandate_reference'])]
#[ORM\UniqueConstraint(columns: ['musician_id', 'sequence', 'project_id'])]
#[ORM\Entity(repositoryClass: \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaDebitMandatesRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\SoftDeleteable(fieldName: 'deleted', hardDelete: \OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused::class)]
class SepaDebitMandate implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use CAFEVDB\Traits\UnusedTrait;

  #[ORM\ManyToOne(targetEntity: Musician::class, inversedBy: 'sepaDebitMandates', fetch: 'EXTRA_LAZY')]
  #[ORM\Id]
  private Musician $musician;

  /**
   * This is a POSITIVE per-musician sequence count. It currently is
   * incremented using
   * \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\PerMusicianSequenceTrait
   */
  #[ORM\Column(type: 'integer')]
  #[ORM\Id]
  #[ORM\GeneratedValue(strategy: 'NONE')] // _AT_ORM\GeneratedValue(strategy="CUSTOM")
  private int $sequence;

  /**
   * Debit-mandates can expire, so many debit-mandates may refer the
   * same bank-account.
   */
  #[ORM\JoinColumn(name: 'musician_id', referencedColumnName: 'musician_id', nullable: false)]
  #[ORM\JoinColumn(name: 'bank_account_sequence', referencedColumnName: 'sequence', nullable: false)]
  #[ORM\ManyToOne(targetEntity: SepaBankAccount::class, inversedBy: 'sepaDebitMandates')]
  private SepaBankAccount $sepaBankAccount;

  /**
   * All debit-mandates are tied to a specific project. The convention
   * is that debit-mandates tied to the member's project are permanent
   * and can be used for all other projects as well. We do not make
   * this field an id as the sequence-id is a running index per
   * musician and joins are more difficult to define.
   *
   * The ProjectPayment entity, e.g., has to reference either a
   * mandate for its own project or a mandate from the member's
   * project.
   */
  #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false)]
  #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'sepaDebitMandates', fetch: 'EXTRA_LAZY')]
  private Project $project;

  #[ORM\Column(type: 'string', length: 35, options: ['collation' => 'ascii_general_ci'])]
  private string $mandateReference;

  #[ORM\Column(type: 'boolean', nullable: false)]
  private bool $nonRecurring;

  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private DateTimeImmutable $mandateDate;

  /**
   * Pre-notification dead-line in calendar days. Normally 14, may be
   * shorter, e.g. 7 calendar days but at least 5 business days.
   */
  #[ORM\Column(type: 'integer', options: ['default' => '14'])]
  private int $preNotificationCalendarDays = 7;

  /**
   * Pre-notification dead-line in TARGET2 days. Normally unset.
   */
  #[ORM\Column(type: 'integer', nullable: true)]
  private ?int $preNotificationBusinessDays = 5;

  #[ORM\Column(type: 'date_immutable', nullable: true)]
  private ?DateTimeImmutable $lastUsedDate;

  #[ORM\OneToOne(targetEntity: DatabaseStorageFile::class, cascade: ['all'], orphanRemoval: true)]
  private ?DatabaseStorageFile $writtenMandate = null;

  /**
   * @var Collection<ProjectPayment>
   *
   * Linke to the payments table.
   */
  #[ORM\OneToMany(targetEntity: CompositePayment::class, mappedBy: 'sepaDebitMandate', fetch: 'EXTRA_LAZY')]
  private Collection $payments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->payments = new ArrayCollection();
  }
  // phpcs:enable

  /**
   * Set sequence
   *
   * @param ?int $sequence
   *
   * @return SepaDebitMandate
   *
   * @todo Detangle mandate reference generation from setting
   * sequences here. Perhaps a slug-handler ...
   */
  public function setSequence(?int $sequence): SepaDebitMandate
  {
    $this->sequence = $sequence;
    $this->adjustMandateReference();

    return $this;
  }

  /**
   * Get sequence.
   *
   * @return ?int
   */
  public function getSequence(): ?int
  {
    return $this->sequence ?? null;
  }

  /**
   * Set musician.
   *
   * @param Musician $musician
   *
   * @return SepaDebitMandate
   */
  public function setMusician(Musician $musician): SepaDebitMandate
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return ?Musician
   */
  public function getMusician(): ?Musician
  {
    return $this->musician ?? null;
  }

  /**
   * Set sepaBankAccount.
   *
   * @param SepaBankAccount $sepaBankAccount
   *
   * @return SepaDebitMandate
   */
  public function setSepaBankAccount(SepaBankAccount $sepaBankAccount): SepaDebitMandate
  {
    $this->sepaBankAccount = $sepaBankAccount;

    return $this;
  }

  /**
   * Get sepaBankAccount.
   *
   * @return SepaBankAccount
   */
  public function getSepaBankAccount(): SepaBankAccount
  {
    return $this->sepaBankAccount;
  }

  /**
   * Set mandateReference.
   *
   * @param string $mandateReference
   *
   * @return SepaDebitMandate
   */
  public function setMandateReference(string $mandateReference): SepaDebitMandate
  {
    $this->mandateReference = $mandateReference;

    return $this;
  }

  /**
   * Get mandateReference.
   *
   * @return ?string
   */
  public function getMandateReference(): ?string
  {
    return $this->mandateReference ?? null;
  }

  /**
   * Set project or project-id
   *
   * @param Project $project
   *
   * @return SepaDebitMandate
   */
  public function setProject(Project $project): SepaDebitMandate
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return nullProject
   */
  public function getProject(): ?Project
  {
    return $this->project ?? null;
  }

  /**
   * Set mandateDate.
   *
   * @param mixed $mandateDate
   *
   * @return SepaDebitMandate
   */
  public function setMandateDate(mixed $mandateDate): SepaDebitMandate
  {
    $this->mandateDate = self::convertToDateTime($mandateDate);
    return $this;
  }

  /**
   * Get mandateDate.
   *
   * @return DateTimeInterface
   */
  public function getMandateDate(): DateTimeInterface
  {
    return $this->mandateDate;
  }

  /**
   * Set preNotificationCalendarDays.
   *
   * @param int $preNotificationCalendarDays
   *
   * @return SepaDebitMandate
   */
  public function setPreNotificationCalendarDays(int $preNotificationCalendarDays): SepaDebitMandate
  {
    $this->preNotificationCalendarDays = $preNotificationCalendarDays;
    return $this;
  }

  /**
   * Get preNotificationCalendarDays.
   *
   * @return int
   */
  public function getPreNotificationCalendarDays(): int
  {
    return $this->preNotificationCalendarDays;
  }

  /**
   * Set preNotificationBusinessDays.
   *
   * @param int|null $preNotificationBusinessDays
   *
   * @return SepaDebitMandate
   */
  public function setPreNotificationBusinessDays(?int $preNotificationBusinessDays): SepaDebitMandate
  {
    $this->preNotificationBusinessDays = $preNotificationBusinessDays;

    return $this;
  }

  /**
   * Get preNotificationBusinessDays.
   *
   * @return int|null
   */
  public function getPreNotificationBusinessDays(): ?int
  {
    return $this->preNotificationBusinessDays;
  }

  /**
   * Set lastUsedDate.
   *
   * @param string|\DateTimeInterface $lastUsedDate
   *
   * @return SepaDebitMandate
   */
  public function setLastUsedDate($lastUsedDate): SepaDebitMandate
  {
    $this->lastUsedDate = self::convertToDateTime($lastUsedDate);
    return $this;
  }

  /**
   * Get lastUsedDate.
   *
   * @return ?DateTimeInterface
   */
  public function getLastUsedDate(): ?DateTimeInterface
  {
    return $this->lastUsedDate;
  }

  /**
   * Set nonRecurring.
   *
   * @param bool $nonRecurring
   *
   * @return SepaDebitMandate
   */
  public function setNonRecurring(bool $nonRecurring): SepaDebitMandate
  {
    $this->nonRecurring = $nonRecurring;

    return $this;
  }

  /**
   * Get nonRecurring.
   *
   * @return bool
   */
  public function getNonRecurring(): bool
  {
    return $this->nonRecurring;
  }

  /**
   * Set writtenMandate.
   *
   * @param ?DatabaseStorageFile $writtenMandate
   *
   * @return SepaDebitMandate
   */
  public function setWrittenMandate(?DatabaseStorageFile $writtenMandate): SepaDebitMandate
  {
    $this->writtenMandate = $writtenMandate;

    return $this;
  }

  /**
   * Get writtenMandate.
   *
   * @return ?DatabaseStorageFile
   */
  public function getWrittenMandate(): ?DatabaseStorageFile
  {
    return $this->writtenMandate;
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return SepaDebitMandate
   */
  public function setPayments(Collection $payments): SepaDebitMandate
  {
    $this->payments = $payments;

    return $this;
  }

  /**
   * Get payments.
   *
   * @return Collection
   */
  public function getPayments(): Collection
  {
    return $this->payments;
  }

  /**
   * Return the number of payments attached to this entity.
   *
   * @return int
   */
  public function usage(): int
  {
    return $this->payments->count();
  }

  /**
   * See that the mandate-sequence is reflected by the mandate reference.
   *
   * @return void
   *
   * @todo The DB structure probably should be cleaned up s.t. this is not
   * necessary.
   */
  #[ORM\PrePersist]
  #[ORM\PreUpdate]
  #[ORM\PreFlush]
  public function adjustMandateReference():void
  {
    if ($this->getSequence() !== null && $this->getMandateReference() !== null) {
      $this->mandateReference = preg_replace(
        '/[+][0-9]{2}$/',
        sprintf('+%02d', $this->sequence),
        $this->mandateReference);
    }
  }
}

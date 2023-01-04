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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

// annotations
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping as MediaMonks;

// types
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event\LifecycleEventArgs;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event\PreFlushEventArgs;

/**
 * SepaBankAccount.
 *
 * In principle the IBAN field would be a perfect ID-field. However,
 * it is stored salted and encrypted and thus cannot be used for
 * queries. Instead, we use a simple integer sequence which is unique
 * in connection with the musician id.
 *
 * Note that a unique constraint is not possible as long as we store
 * the personal data encrypted in the data base.
 *
 * @ORM\Table(name="SepaBankAccounts")
 *
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBankAccountsRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 *
 * @ORM\HasLifecycleCallbacks
 */
class SepaBankAccount implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\RotDrop\Toolkit\Traits\DateTimeTrait;
  use CAFEVDB\Traits\UnusedTrait;

  /**
   * @var Musician
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="sepaBankAccounts", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var int
   *
   * This is a POSITIVE per-musician sequence count. It currently is
   * incremented using
   * \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\PerMusicianSequenceTrait
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   * _AT_ORM\GeneratedValue(strategy="CUSTOM")
   * _AT_ORM\CustomIdGenerator(class="OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\PerMusicianSequenceGenerator")
   */
  private $sequence;

  /**
   * @var string
   *
   * Encryption with 16 bytes iv, 64 bytes HMAC (sha512) and perhaps more data for a multi-user seal.
   *
   * For now calculate wtih 256 bytes for the encrypted data itself + another
   * 256 bytes for each multi-user seal. Using 2k of data should be plenty
   * given that we probably only need two users: the management board with a
   * shared encryption key and the respective orchestra member with its own key.
   *
   * @ORM\Column(type="string", length=2048, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt", context="encryptionContext")
   */
  private $iban;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2048, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt", context="encryptionContext")
   */
  private $bic;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2048, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt", context="encryptionContext")
   */
  private $blz;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2048, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt", context="encryptionContext")
   */
  private $bankAccountOwner;

  /**
   * @var array
   *
   * In memory encryption context to support multi user encryption. This is a
   * multi-field encryption context indexed by the property name.
   */
  private $encryptionContext = [];

  /**
   * @var Collection
   *
   * Link to the attached debit mandates. Can be more than one at a
   * given time, even more than one active.
   *
   * @ORM\OneToMany(targetEntity="SepaDebitMandate",
   *                mappedBy="sepaBankAccount",
   *                fetch="EXTRA_LAZY")
   */
  private $sepaDebitMandates;

  /**
   * @var Collection
   *
   * @ORM\OneToMany(targetEntity="CompositePayment",
   *                mappedBy="sepaBankAccount",
   *                fetch="EXTRA_LAZY")
   */
  private $payments;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->payments = new ArrayCollection();
  }
  // phpcs:enable

  /**
   * Set iban.
   *
   * @param null|string $iban
   *
   * @return SepaBankAccount
   */
  public function setIban($iban):SepaBankAccount
  {
    $this->iban = $iban;

    return $this;
  }

  /**
   * Get iban.
   *
   * @return string
   */
  public function getIban()
  {
    return $this->iban;
  }

  /**
   * Set bic.
   *
   * @param null|string $bic
   *
   * @return SepaBankAccount
   */
  public function setBic($bic):SepaBankAccount
  {
    $this->bic = $bic;

    return $this;
  }

  /**
   * Get bic.
   *
   * @return string
   */
  public function getBic()
  {
    return $this->bic;
  }

  /**
   * Set blz.
   *
   * @param null|string $blz
   *
   * @return SepaBankAccount
   */
  public function setBlz($blz):SepaBankAccount
  {
    $this->blz = $blz;

    return $this;
  }

  /**
   * Get blz.
   *
   * @return string
   */
  public function getBlz()
  {
    return $this->blz;
  }

  /**
   * Set bankAccountOwner.
   *
   * @param null|string $bankAccountOwner
   *
   * @return SepaBankAccount
   */
  public function setBankAccountOwner($bankAccountOwner):SepaBankAccount
  {
    $this->bankAccountOwner = $bankAccountOwner;

    return $this;
  }

  /**
   * Get bankAccountOwner.
   *
   * @return string
   */
  public function getBankAccountOwner():string
  {
    return $this->bankAccountOwner;
  }

  /**
   * Set musician.
   *
   * @param Musician|null $musician
   *
   * @return SepaBankAccount
   */
  public function setMusician($musician = null):SepaBankAccount
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician|null|int
   */
  public function getMusician()
  {
    return $this->musician;
  }

  /**
   * Set sequence.
   *
   * @param int $sequence
   *
   * @return SepaBankAccount
   */
  public function setSequence(?int $sequence = null):SepaBankAccount
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
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return SepaBankAccount
   */
  public function setPayments(Collection $payments):SepaBankAccount
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
   * Set sepaDebitMandates.
   *
   * @param Collection $sepaDebitMandates
   *
   * @return SepaBankAccount
   */
  public function setSepaDebitMandates(Collection $sepaDebitMandates):SepaBankAccount
  {
    $this->sepaDebitMandates = $sepaDebitMandates;

    return $this;
  }

  /**
   * Get sepaDebitMandates.
   *
   * @return Collection
   */
  public function getSepaDebitMandates():Collection
  {
    return $this->sepaDebitMandates;
  }

  /**
   * Return the usage count. The bank-account is used and thus
   * undeleteable and unchangeable (up to less important data like
   * typos in the bank-account-owner) if there are recorded payments
   * or debit-mandates.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->payments->count() + $this->sepaDebitMandates->count();
  }

  /**
   * Add a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return SepaBankAccount
   */
  public function addEncryptionIdentity(string $personality):SepaBankAccount
  {
    if (empty($this->encryptionContext)) {
      $this->encryptionContext = [];
    }
    if (!in_array($personality, $this->encryptionContext)) {
      $this->encryptionContext[] = $personality;
    }
    return $this;
  }

  /**
   * Remove a user-id or group-id to the list of "encryption identities",
   * i.e. the list of identities which can read and write this entry.
   *
   * @param string $personality
   *
   * @return SepaBankAccount
   */
  public function removeEncryptionIdentity(string $personality):SepaBankAccount
  {
    $pos = array_search($personality, $this->encryptionContext??[]);
    if ($pos !== false) {
      unset($this->encryptionContext[pos]);
      $this->encryptionContext = array_values($this->encryptionContext);
    }
    return $this;
  }

  /**
   * Ensure that the encryptionContext contains the user-id of the owning musician.
   *
   * @return void
   */
  private function sanitizeEncryptionContext()
  {
    $userIdSlug = $this->musician->getUserIdSlug();
    if (!empty($userIdSlug) && !in_array($userIdSlug, $this->encryptionContext ?? [])) {
      $this->encryptionContext[] = $userIdSlug;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @ORM\PostLoad
   * @ORM\PrePersist
   * _AT_ORM\PreUpdate
   */
  public function handleLifeCycleEvent(LifecycleEventArgs $eventArgs)
  {
    $this->sanitizeEncryptionContext();
  }
}

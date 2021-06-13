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

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;
use OCA\CAFEVDB\Wrapped\MediaMonks\Doctrine\Mapping\Annotation as MediaMonks;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

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
 */
class SepaBankAccount implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use CAFEVDB\Traits\UnusedTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="sepaBankAccounts", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var int
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $sequence;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $iban;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $bic;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $blz;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=512, nullable=false, options={"collation"="ascii_bin"})
   * @MediaMonks\Transformable(name="encrypt")
   */
  private $bankAccountOwner;

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

  public function __construct() {
    $this->arrayCTOR();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->payments = new ArrayCollection();
  }

  /**
   * Set iban.
   *
   * @param string $iban
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
   * @param string $bic
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
   * @param string $blz
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
   * @param string $bankAccountOwner
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
   * @return Musician|null
   */
  public function getMusician():?Musician
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
   * @param int $payments
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
   * @param int $sepaDebitMandates
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
}

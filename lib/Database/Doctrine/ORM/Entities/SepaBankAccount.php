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
use Gedmo\Mapping\Annotation as Gedmo;
use MediaMonks\Doctrine\Mapping\Annotation as MediaMonks;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * SepaBankAccount.
 *
 * In principle the IBAN field would be a perfect ID-field. However,
 * it is stored salted and encrypted and thus cannot be used for
 * queries. Instead, we use a simple integer sequence which is unique
 * in connection with the musician id.
 *
 * @ORM\Table(name="SepaBankAccounts")
 *
 * @ORM\Entity
 * @Gedmo\SoftDeleteable(fieldName="deleted")
 */
class SepaBankAccount implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\TimestampableEntity;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="sepaBankAccounts", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", options={"default"="1"})
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $sequence = 1;

  /**
   * Date of last usage for generated bulk transactions.
   *
   * @var \DateTimeImmutable|null
   *
   * @ORM\Column(type="date_immutable", nullable=true)
   */
  private $lastUsedDate;

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
   * @ORM\OneToMany(targetEntity="ProjectPayment",
   *                mappedBy="sepaBankAccount",
   *                fetch="EXTRA_LAZY")
   */
  private $projectPayments;

  public function __construct() {
    $this->arrayCTOR();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->projectPayments = new ArrayCollection();
  }

  /**
   * Set lastUsedDate.
   *
   * @param null|string|\DateTimeInterface $lastUsedDate
   *
   * @return SepaDebitMandate
   */
  public function setLastUsedDate($lastUsedDate = null):SepaDebitMandate
  {
    if (is_string($lastUsedDate)) {
      $this->lastUsedDate = new \DateTimeImmutable($lastUsedDate);
    } else {
      $this->lastUsedDate = \DateTimeImmutable::createFromInterface($lastUsedDate);
    }
    return $this;
  }

  /**
   * Get lastUsedDate.
   *
   * @return \DateTimeImmutable|null
   */
  public function getLastUsedDate():?\DateTimeImmutable
  {
    return $this->lastUsedDate;
  }

  /**
   * Set iban.
   *
   * @param string $iban
   *
   * @return SepaDebitMandate
   */
  public function setIban($iban):SepaDebitMandate
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
   * @return SepaDebitMandate
   */
  public function setBic($bic):SepaDebitMandate
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
   * @return SepaDebitMandate
   */
  public function setBlz($blz):SepaDebitMandate
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
   * @return SepaDebitMandate
   */
  public function setBankAccountOwner($bankAccountOwner):SepaDebitMandate
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
   * @return SepaDebitMandate
   */
  public function setMusician($musician = null):SepaDebitMandate
  {
    $this->musician = $musician;

    return $this;
  }

  /**
   * Get musician.
   *
   * @return Musician|null
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
   * @return SepaDebitMandate
   */
  public function setSequence(int $sequence = 1):SepaDebitMandate
  {
    $this->sequence = $sequence;

    return $this;
  }

  /**
   * Get sequence.
   *
   * @return Sequence|null
   */
  public function getSequence():int
  {
    return $this->sequence;
  }
}

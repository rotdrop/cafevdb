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
 * SepaDebitMandates
 *
 * @ORM\Table(name="SepaDebitMandates", uniqueConstraints={@ORM\UniqueConstraint(columns={"mandate_reference"})})
 * @ORM\Entity
 */
class SepaDebitMandate implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="sepaDebitMandates", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $project;

  /**
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="sepaDebitMandates", fetch="EXTRA_LAZY")
   * @ORM\Id
   */
  private $musician;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", options={"default"="0"})
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="NONE")
   */
  private $sequence = 0;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=35, nullable=false)
   */
  private $mandateReference;

  /**
   * @var \DateTime
   *
   * @ORM\Column(type="date", nullable=false)
   */
  private $mandatedate;

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(type="date", nullable=true)
   */
  private $lastUsedDate;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false)
   */
  private $nonRecurring;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   */
  private $iban;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   */
  private $bic;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $blz;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=512, nullable=false)
   */
  private $bankAccountOwner;

  /**
   * @var bool|null
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="1"})
   */
  private $active = true;

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
   * Set mandateReference.
   *
   * @param string $mandateReference
   *
   * @return SepaDebitMandates
   */
  public function setMandateReference($mandateReference)
  {
    $this->mandateReference = $mandateReference;

    return $this;
  }

  /**
   * Get mandateReference.
   *
   * @return string
   */
  public function getMandateReference()
  {
    return $this->mandateReference;
  }

  /**
   * Set mandatedate.
   *
   * @param \DateTime $mandatedate
   *
   * @return SepaDebitMandates
   */
  public function setMandatedate($mandatedate)
  {
    $this->mandatedate = $mandatedate;

    return $this;
  }

  /**
   * Get mandatedate.
   *
   * @return \DateTime
   */
  public function getMandatedate()
  {
    return $this->mandatedate;
  }

  /**
   * Set lastUsedDate.
   *
   * @param \DateTime|null $lastUsedDate
   *
   * @return SepaDebitMandates
   */
  public function setLastUsedDate($lastUsedDate = null)
  {
    $this->lastUsedDate = $lastUsedDate;

    return $this;
  }

  /**
   * Get lastUsedDate.
   *
   * @return \DateTime|null
   */
  public function getLastUsedDate()
  {
    return $this->lastUsedDate;
  }

  /**
   * Set musicianId.
   *
   * @param int $musicianId
   *
   * @return SepaDebitMandates
   */
  public function setMusicianId($musicianId)
  {
    $this->musicianId = $musicianId;

    return $this;
  }

  /**
   * Get musicianId.
   *
   * @return int
   */
  public function getMusicianId()
  {
    return $this->musicianId;
  }

  /**
   * Set projectId.
   *
   * @param int $projectId
   *
   * @return SepaDebitMandates
   */
  public function setProjectId($projectId)
  {
    $this->projectId = $projectId;

    return $this;
  }

  /**
   * Get projectId.
   *
   * @return int
   */
  public function getProjectId()
  {
    return $this->projectId;
  }

  /**
   * Set nonRecurring.
   *
   * @param bool $nonRecurring
   *
   * @return SepaDebitMandates
   */
  public function setNonRecurring($nonRecurring)
  {
    $this->nonRecurring = $nonRecurring;

    return $this;
  }

  /**
   * Get nonRecurring.
   *
   * @return bool
   */
  public function getNonRecurring()
  {
    return $this->nonRecurring;
  }

  /**
   * Set iban.
   *
   * @param string $iban
   *
   * @return SepaDebitMandates
   */
  public function setIban($iban)
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
   * @return SepaDebitMandates
   */
  public function setBic($bic)
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
   * @return SepaDebitMandates
   */
  public function setBlz($blz)
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
   * @return SepaDebitMandates
   */
  public function setBankAccountOwner($bankAccountOwner)
  {
    $this->bankAccountOwner = $bankAccountOwner;

    return $this;
  }

  /**
   * Get bankAccountOwner.
   *
   * @return string
   */
  public function getBankAccountOwner()
  {
    return $this->bankAccountOwner;
  }

  /**
   * Set active.
   *
   * @param bool|null $active
   *
   * @return SepaDebitMandates
   */
  public function setActive($active = null)
  {
    $this->active = $active;

    return $this;
  }

  /**
   * Get active.
   *
   * @return bool|null
   */
  public function getActive()
  {
    return $this->active;
  }
}

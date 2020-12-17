<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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


use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Besetzungen *
 * @ORM\Table(name="ProjectParticipants", uniqueConstraints={@ORM\UniqueConstraint(columns={"project_id", "musician_id"})})
 * @ORM\Entity
 */
class ProjectParticipant
{
  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $projectId;

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   */
  private $musicianId;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
   */
  private $registration = '0';

  /**
   * @var string
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00","comment"="Gagen negativ"})
   */
  private $serviceCharge = '0.00';

  /**
   * @var string
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
   */
  private $prePayment = '0.00';

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="1"})
   */
  private $debitnote = '1';

  /**
   * @var string
   *
   * @ORM\Column(type="text", length=65535, nullable=false, options={"comment"="Allgemeine Bermerkungen"})
   */
  private $remarks;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
   */
  private $disabled = '0';

  /**
   * Link to payments
   *
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="projectParticipant")
   */
  private $payment;

  /**
   * Link to extra fields data
   *
   * @ORM\OneToMany(targetEntity="ProjectExtraFieldDatum", mappedBy="projectParticipant")
   */
  private $extraFieldsData;

  /**
   * Core functionality: a participant needs to be present in the
   * Musician table. No participation without registration as musician
   * (where looking after childs of other musicians also counts as
   * playing an instrument :) )
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="projectParticipation")
   * @ORM\JoinColumn(name="musician_id", referencedColumnName="id")
   */
  private $musician;

  public function __construct() {
    $this->arrayCTOR();
    $this->extraFieldsData = new ArrayCollection();
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
   * Set projectId.
   *
   * @param int $projectId
   *
   * @return Besetzungen
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
   * Set musicianId.
   *
   * @param int $musicianId
   *
   * @return Besetzungen
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
   * Set registration.
   *
   * @param bool $registration
   *
   * @return Besetzungen
   */
  public function setRegistration($registration)
  {
    $this->registration = $registration;

    return $this;
  }

  /**
   * Get registration.
   *
   * @return bool
   */
  public function getRegistration()
  {
    return $this->registration;
  }

  /**
   * Set serviceCharge.
   *
   * @param string $serviceCharge
   *
   * @return Besetzungen
   */
  public function setServiceCharge($serviceCharge)
  {
    $this->serviceCharge = $serviceCharge;

    return $this;
  }

  /**
   * Get serviceCharge.
   *
   * @return string
   */
  public function getServiceCharge()
  {
    return $this->serviceCharge;
  }

  /**
   * Set prePayment.
   *
   * @param string $prePayment
   *
   * @return Besetzungen
   */
  public function setPrePayment($prePayment)
  {
    $this->prePayment = $prePayment;

    return $this;
  }

  /**
   * Get prePayment.
   *
   * @return string
   */
  public function getPrePayment()
  {
    return $this->prePayment;
  }

  /**
   * Set debitnote.
   *
   * @param bool $debitnote
   *
   * @return Besetzungen
   */
  public function setDebitnote($debitnote)
  {
    $this->debitnote = $debitnote;

    return $this;
  }

  /**
   * Get debitnote.
   *
   * @return bool
   */
  public function getDebitnote()
  {
    return $this->debitnote;
  }

  /**
   * Set remarks.
   *
   * @param string $remarks
   *
   * @return Besetzungen
   */
  public function setRemarks($remarks)
  {
    $this->remarks = $remarks;

    return $this;
  }

  /**
   * Get remarks.
   *
   * @return string
   */
  public function getRemarks()
  {
    return $this->remarks;
  }

  /**
   * Set disabled.
   *
   * @param bool $disabled
   *
   * @return Besetzungen
   */
  public function setDisabled($disabled)
  {
    $this->disabled = $disabled;

    return $this;
  }

  /**
   * Get disabled.
   *
   * @return bool
   */
  public function getDisabled()
  {
    return $this->disabled;
  }
}

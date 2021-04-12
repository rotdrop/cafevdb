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
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Projects
 *
 * @ORM\Table(name="Projects", uniqueConstraints={@ORM\UniqueConstraint(columns={"name"})})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository")
 */
class Project implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\TimestampableEntity;

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
   * @ORM\Column(type="integer", nullable=false, options={"unsigned"=true})
   */
  private $year;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=64, nullable=false)
   */
  private $name;

  /**
   * @var Types\EnumProjectTemporalType
   *
   * @ORM\Column(type="EnumProjectTemporalType", nullable=false, options={"default"="temporary"})
   */
  private $type = 'temporary';

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $disabled = false;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrumentationNumber", mappedBy="project", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $instrumentationNumbers;

  /**
   * @ORM\OneToMany(targetEntity="ProjectPoster", mappedBy="owner", fetch="EXTRA_LAZY")
   */
  private $posters;

  /**
   * @ORM\OneToMany(targetEntity="ProjectFlyer", mappedBy="owner", fetch="EXTRA_LAZY")
   */
  private $flyers;

  /**
   * @ORM\OneToMany(targetEntity="ProjectWebPage", mappedBy="project", fetch="EXTRA_LAZY")
   * @todo this should cascade deletes
   */
  private $webPages;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantField", mappedBy="project", indexBy="id", fetch="EXTRA_LAZY")
   * @ORM\OrderBy({"displayOrder" = "DESC"})
   */
  private $participantFields;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDatum", mappedBy="project", fetch="EXTRA_LAZY")
   */
  private $participantFieldsData;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipant", mappedBy="project")
   */
  private $participants;

  /**
   * @ORM\OneToMany(targetEntity="SepaDebitMandate", mappedBy="project")
   */
  private $sepaDebitMandates;

  /**
   * @ORM\OneToMany(targetEntity="SepaDebitNote", mappedBy="project")
   */
  private $debitNotes;

  /**
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="project")
   */
  private $payments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="project")
   */
  private $participantInstruments;

  public function __construct() {
    $this->arrayCTOR();
    $this->instrumentationNumbers = new ArrayCollection();
    $this->posters = new ArrayCollection();
    $this->flyers = new ArrayCollection();
    $this->webPages = new ArrayCollection();
    $this->participantFields = new ArrayCollection();
    $this->participantFieldsData = new ArrayCollection();
    $this->participants = new ArrayCollection();
    $this->participantInstruments = new ArrayCollection();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->debitNotes = new ArrayCollection();
    $this->payments = new ArrayCollection();
  }

  /**
   * Set id.
   *
   * @return Project
   */
  public function setId(int $id):Project
  {
    $this->id = $id;

    return $this;
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
   * Set year.
   *
   * @param int $year
   *
   * @return Project
   */
  public function setYear($year)
  {
    $this->year = $year;

    return $this;
  }

  /**
   * Get year.
   *
   * @return int
   */
  public function getYear()
  {
    return $this->year;
  }

  /**
   * Set name.
   *
   * @param string $name
   *
   * @return Project
   */
  public function setName($name)
  {
    $this->name = $name;

    return $this;
  }

  /**
   * Get name.
   *
   * @return string
   */
  public function getName()
  {
    return $this->name;
  }

  /**
   * Set type.
   *
   * @param EnumProjectTemporalType|string $type
   *
   * @return Project
   */
  public function setType($type):Project
  {
    $this->type = new Types\EnumProjectTemporalType($type);

    return $this;
  }

  /**
   * Get type.
   *
   * @return EnumProjectTemporalType
   */
  public function getType():Types\EnumProjectTemporalType
  {
    return $this->type;
  }

  /**
   * Set besetzung.
   *
   * @param array|null $besetzung
   *
   * @return Project
   */
  public function setBesetzung($besetzung = null)
  {
    $this->besetzung = $besetzung;

    return $this;
  }

  /**
   * Get besetzung.
   *
   * @return array|null
   */
  public function getBesetzung()
  {
    return $this->besetzung;
  }

  /**
   * Set serviceCharge.
   *
   * @param string $serviceCharge
   *
   * @return Project
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
   * Set anzahlung.
   *
   * @param string $anzahlung
   *
   * @return Project
   */
  public function setAnzahlung($anzahlung)
  {
    $this->anzahlung = $anzahlung;

    return $this;
  }

  /**
   * Get anzahlung.
   *
   * @return string
   */
  public function getAnzahlung()
  {
    return $this->anzahlung;
  }

  /**
   * Set disabled.
   *
   * @param bool $disabled
   *
   * @return Project
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

  /**
   * Set posters.
   *
   * @param ArrayCollection $posters
   *
   * @return Project
   */
  public function setPosters($posters)
  {
    $this->posters = $posters;

    return $this;
  }

  /**
   * Get posters.
   *
   * @return ArrayCollection
   */
  public function getPosters()
  {
    return $this->posters;
  }

  /**
   * Set flyers.
   *
   * @param ArrayCollection $flyers
   *
   * @return Project
   */
  public function setFlyers($flyers)
  {
    $this->flyers = $flyers;

    return $this;
  }

  /**
   * Get flyers.
   *
   * @return ArrayCollection
   */
  public function getFlyers()
  {
    return $this->flyers;
  }

  /**
   * Set webPages.
   *
   * @param ArrayCollection $webPages
   *
   * @return Project
   */
  public function setWebPages($webPages)
  {
    $this->webPages = $webPages;

    return $this;
  }

  /**
   * Get webPages.
   *
   * @return ArrayCollection
   */
  public function getWebPages()
  {
    return $this->webPages;
  }

  /**
   * Set participantFields.
   *
   * @param ArrayCollection $participantFields
   *
   * @return Project
   */
  public function setParticipantFields($participantFields):Project
  {
    $this->participantFields = $participantFields;

    return $this;
  }

  /**
   * Get participantFields.
   *
   * @return Collection
   */
  public function getParticipantFields():Collection
  {
    return $this->participantFields;
  }

  /**
   * Set participantFieldsData.
   *
   * @param ArrayCollection $participantFieldsData
   *
   * @return Project
   */
  public function setParticipantFieldsData($participantFieldsData):Project
  {
    $this->participantFieldsData = $participantFieldsData;

    return $this;
  }

  /**
   * Get participantFieldsData.
   *
   * @return Collection
   */
  public function getParticipantFieldsData():Collection
  {
    return $this->participantFieldsData;
  }

  /**
   * Set participants.
   *
   * @param ArrayCollection $participants
   *
   * @return Project
   */
  public function setParticipants($participants):Project
  {
    $this->participants = $participants;

    return $this;
  }

  /**
   * Get participants.
   *
   * @return Collection
   */
  public function getParticipants():Collection
  {
    return $this->participants;
  }

  /**
   * Set instrumentationNumbers.
   *
   * @param ArrayCollection $instrumentationNumbers
   *
   * @return Project
   */
  public function setInstrumentationNumbers($instrumentationNumbers)
  {
    $this->instrumentationNumbers = $instrumentationNumbers;

    return $this;
  }

  /**
   * Get instrumentationNumbers.
   *
   * @return ArrayCollection
   */
  public function getInstrumentationNumbers()
  {
    return $this->instrumentationNumbers;
  }

  /**
   * Set payments.
   *
   * @param ArrayCollection $payments
   *
   * @return Project
   */
  public function setPayments(Collection $payments):Project
  {
    $this->payments = $payments;

    return $this;
  }

  /**
   * Get payments.
   *
   * @return ArrayCollection
   */
  public function getPayments():Collection
  {
    return $this->payments;
  }

  /**
   * Set participantInstruments.
   *
   * @param ArrayCollection $participantInstruments
   *
   * @return Project
   */
  public function setParticipantInstruments(Collection $participantInstruments):Project
  {
    $this->participantInstruments = $participantInstruments;

    return $this;
  }

  /**
   * Get participantInstruments.
   *
   * @return ArrayCollection
   */
  public function getParticipantInstruments():Collection
  {
    return $this->participantInstruments;
  }
}

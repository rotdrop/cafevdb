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

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

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
  use CAFEVDB\Traits\TimestampableTrait;

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
   * @var enumprojecttemporaltype
   *
   * @ORM\Column(type="enumprojecttemporaltype", nullable=false, options={"default"="temporary"})
   */
  private $temporalType = 'temporary';

  /**
   * @var string
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=true, options={"default"="0.00"})
   */
  private $serviceCharge = '0.00';

  /**
   * @var string
   *
   * @ORM\Column(type="decimal", precision=7, scale=2, nullable=true, options={"default"="0.00"})
   */
  private $prePayment = '0.00';

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
   * @TODO this should cascade deletes
   */
  private $webPages;

  /**
   * @ORM\OneToMany(targetEntity="ProjectExtraField", mappedBy="project", fetch="EXTRA_LAZY")
   */
  private $extraFields;

  /**
   * @ORM\OneToMany(targetEntity="ProjectExtraFieldDatum", mappedBy="project", fetch="EXTRA_LAZY")
   */
  private $extraFieldsData;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipant", mappedBy="project")
   */
  private $participants;

  /**
   * @ORM\OneToMany(targetEntity="SepaDebitMandate", mappedBy="project")
   */
  private $sepaDebitMandates;

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
    $this->extraFields = new ArrayCollection();
    $this->extraFieldsData = new ArrayCollection();
    $this->participants = new ArrayCollection();
    $this->participantInstruments = new ArrayCollection();
    $this->sepaDebitMandates = new ArrayCollection();
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
   * Set art.
   *
   * @param enumprojecttemporaltype $art
   *
   * @return Project
   */
  public function setArt($art)
  {
    $this->art = $art;

    return $this;
  }

  /**
   * Get art.
   *
   * @return enumprojecttemporaltype
   */
  public function getArt()
  {
    return $this->art;
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
   * Set extraFields.
   *
   * @param ArrayCollection $extraFields
   *
   * @return Project
   */
  public function setExtraFields($extraFields)
  {
    $this->extraFields = $extraFields;

    return $this;
  }

  /**
   * Get extraFields.
   *
   * @return ArrayCollection
   */
  public function getExtraFields()
  {
    return $this->extraFields;
  }

  /**
   * Set extraFieldsData.
   *
   * @param ArrayCollection $extraFieldsData
   *
   * @return Project
   */
  public function setExtraFieldsData($extraFieldsData)
  {
    $this->extraFieldsData = $extraFieldsData;

    return $this;
  }

  /**
   * Get extraFieldsData.
   *
   * @return ArrayCollection
   */
  public function getExtraFieldsData()
  {
    return $this->extraFieldsData;
  }

  /**
   * Set participants.
   *
   * @param ArrayCollection $participants
   *
   * @return Project
   */
  public function setParticipants($participants)
  {
    $this->participants = $participants;

    return $this;
  }

  /**
   * Get participants.
   *
   * @return ArrayCollection
   */
  public function getParticipants()
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
}

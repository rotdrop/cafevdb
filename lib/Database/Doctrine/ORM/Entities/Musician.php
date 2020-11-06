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
 * Musician
 *
 * @ORM\Table(name="Musicians")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusiciansRepository")
 */
class Musician implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\UuidTrait;

  /**
   * @var int
   *
   * @ORM\Column(name="Id", type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(name="Name", type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var string
   *
   * @ORM\Column(name="Vorname", type="string", length=128, nullable=false)
   */
  private $vorname;

  /**
   * @var string
   *
   * @ORM\Column(name="Stadt", type="string", length=128, nullable=false)
   */
  private $stadt;

  /**
   * @var string
   *
   * @ORM\Column(name="Strasse", type="string", length=128, nullable=false)
   */
  private $strasse;

  /**
   * @var string
   *
   * @ORM\Column(name="Land", type="string", length=2, nullable=false)
   */
  private $land = 'DE';

  /**
   * @var int|null
   *
   * @ORM\Column(name="Postleitzahl", type="string", length=32, nullable=false)
   */
  private $postleitzahl;

  /**
   * @var string
   *
   * @ORM\Column(name="Sprachpräferenz", type="string", length=128, nullable=false, options={"comment"="Und was es sonst noch so gibt ..."})
   */
  private $sprachpräferenz;

  /**
   * @var string
   *
   * @ORM\Column(name="MobilePhone", type="string", length=128, nullable=false)
   */
  private $mobilephone;

  /**
   * @var string
   *
   * @ORM\Column(name="FixedLinePhone", type="string", length=128, nullable=false)
   */
  private $fixedlinephone;

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(name="Geburtstag", type="date", nullable=true)
   */
  private $geburtstag;

  /**
   * @var string
   *
   * @ORM\Column(name="Email", type="string", length=256, nullable=false)
   */
  private $email;

  /**
   * @var enummemberstatus|null
   *
   * @ORM\Column(name="MemberStatus", type="enummemberstatus", nullable=true, options={"default"="regular","comment"="passive, soloist, conductor and temporary are excluded from mass-email. soloist and conductor are even excluded from ""per-project"" email unless explicitly selected."})
   */
  private $memberstatus = 'regular';

  /**
   * @var string|null
   *
   * @ORM\Column(name="Remarks", type="string", length=1024, nullable=true)
   */
  private $remarks;

  /**
   * @var bool
   *
   * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
   */
  private $disabled = '0';

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(name="Aktualisiert", type="datetime", nullable=true)
   */
  private $aktualisiert;

  /**
   * @ORM\ManyToMany(targetEntity="Instrument", inversedBy="musicians", orphanRemoval=true)
   * @ORM\JoinTable(
   *   name="musician_instrument",
   *   joinColumns={@ORM\JoinColumn(name="musician_id", referencedColumnName="Id", onDelete="CASCADE")},
   *   inverseJoinColumns={@ORM\JoinColumn(name="instrument_id", referencedColumnName="Id", onDelete="CASCADE")}
   * )
   */
  private $instruments;

  /**
   * @ORM\ManyToMany(targetEntity="Image", inversedBy="musicians", orphanRemoval=true)
   * @ORM\JoinTable(
   *   name="musician_photo",
   *   joinColumns={@ORM\JoinColumn(name="musician_id", referencedColumnName="Id", unique=true, onDelete="CASCADE")},
   *   inverseJoinColumns={@ORM\JoinColumn(name="image_id", referencedColumnName="id", onDelete="CASCADE")}
   * )
   */
  private $photo;

  public function __construct() {
    $this->arrayCTOR();
    $this->instruments = new ArrayCollection();
  }

  /**
   * Set id.
   *
   * @param int $id
   *
   * @return Musician
   */
  public function setId($id)
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
   * Set name.
   *
   * @param string $name
   *
   * @return Musician
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
   * Set vorname.
   *
   * @param string $vorname
   *
   * @return Musician
   */
  public function setVorname($vorname)
  {
    $this->vorname = $vorname;

    return $this;
  }

  /**
   * Get vorname.
   *
   * @return string
   */
  public function getVorname()
  {
    return $this->vorname;
  }

  /**
   * Set stadt.
   *
   * @param string $stadt
   *
   * @return Musician
   */
  public function setStadt($stadt)
  {
    $this->stadt = $stadt;

    return $this;
  }

  /**
   * Get stadt.
   *
   * @return string
   */
  public function getStadt()
  {
    return $this->stadt;
  }

  /**
   * Set strasse.
   *
   * @param string $strasse
   *
   * @return Musician
   */
  public function setStrasse($strasse)
  {
    $this->strasse = $strasse;

    return $this;
  }

  /**
   * Get strasse.
   *
   * @return string
   */
  public function getStrasse()
  {
    return $this->strasse;
  }

  /**
   * Set postleitzahl.
   *
   * @param int|null $postleitzahl
   *
   * @return Musician
   */
  public function setPostleitzahl($postleitzahl = null)
  {
    $this->postleitzahl = $postleitzahl;

    return $this;
  }

  /**
   * Get postleitzahl.
   *
   * @return int|null
   */
  public function getPostleitzahl()
  {
    return $this->postleitzahl;
  }

  /**
   * Set land.
   *
   * @param string $land
   *
   * @return Musician
   */
  public function setLand($land)
  {
    $this->land = $land;

    return $this;
  }

  /**
   * Get land.
   *
   * @return string
   */
  public function getLand()
  {
    return $this->land;
  }

  /**
   * Set sprachpräferenz.
   *
   * @param string $sprachpräferenz
   *
   * @return Musician
   */
  public function setSprachpräferenz($sprachpräferenz)
  {
    $this->sprachpräferenz = $sprachpräferenz;

    return $this;
  }

  /**
   * Get sprachpräferenz.
   *
   * @return string
   */
  public function getSprachpräferenz()
  {
    return $this->sprachpräferenz;
  }

  /**
   * Set mobilephone.
   *
   * @param string $mobilephone
   *
   * @return Musician
   */
  public function setMobilephone($mobilephone)
  {
    $this->mobilephone = $mobilephone;

    return $this;
  }

  /**
   * Get mobilephone.
   *
   * @return string
   */
  public function getMobilephone()
  {
    return $this->mobilephone;
  }

  /**
   * Set fixedlinephone.
   *
   * @param string $fixedlinephone
   *
   * @return Musician
   */
  public function setFixedlinephone($fixedlinephone)
  {
    $this->fixedlinephone = $fixedlinephone;

    return $this;
  }

  /**
   * Get fixedlinephone.
   *
   * @return string
   */
  public function getFixedlinephone()
  {
    return $this->fixedlinephone;
  }

  /**
   * Set geburtstag.
   *
   * @param \DateTime|null $geburtstag
   *
   * @return Musician
   */
  public function setGeburtstag($geburtstag = null)
  {
    $this->geburtstag = $geburtstag;

    return $this;
  }

  /**
   * Get geburtstag.
   *
   * @return \DateTime|null
   */
  public function getGeburtstag()
  {
    return $this->geburtstag;
  }

  /**
   * Set email.
   *
   * @param string $email
   *
   * @return Musician
   */
  public function setEmail($email)
  {
    $this->email = $email;

    return $this;
  }

  /**
   * Get email.
   *
   * @return string
   */
  public function getEmail()
  {
    return $this->email;
  }

  /**
   * Set memberstatus.
   *
   * @param enummemberstatus|null $memberstatus
   *
   * @return Musician
   */
  public function setMemberstatus($memberstatus = null)
  {
    $this->memberstatus = $memberstatus;

    return $this;
  }

  /**
   * Get memberstatus.
   *
   * @return enummemberstatus|null
   */
  public function getMemberstatus()
  {
    return $this->memberstatus;
  }

  /**
   * Set remarks.
   *
   * @param string|null $remarks
   *
   * @return Musician
   */
  public function setRemarks($remarks = null)
  {
    $this->remarks = $remarks;

    return $this;
  }

  /**
   * Get remarks.
   *
   * @return string|null
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
   * @return Musician
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
   * Set aktualisiert.
   *
   * @param \DateTime|null $aktualisiert
   *
   * @return Musician
   */
  public function setAktualisiert($aktualisiert = null)
  {
    $this->aktualisiert = $aktualisiert;

    return $this;
  }

  /**
   * Get aktualisiert.
   *
   * @return \DateTime|null
   */
  public function getAktualisiert()
  {
    return $this->aktualisiert;
  }

  /**
   * Set photo.
   *
   * @param Image $photo|null
   *
   * @return Musician
   */
  public function setPhoto($photo = null)
  {
    $this->photo = $photo;

    return $this;
  }

  /**
   * Get photo.
   *
   * @return Image|null
   */
  public function getPhoto()
  {
    return $this->photo;
  }
}

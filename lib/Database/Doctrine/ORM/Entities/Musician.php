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
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="IDENTITY")
   */
  private $id;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $name;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $firstName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $city;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $street;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, nullable=false)
   */
  private $country = 'DE';

  /**
   * @var int|null
   *
   * @ORM\Column(type="string", length=32, nullable=false)
   */
  private $postalCode;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false, options={"comment"="Und was es sonst noch so gibt ..."})
   */
  private $language;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $mobilePhone;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $fixedLinePhone;

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(type="date", nullable=true)
   */
  private $birthday;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=256, nullable=false)
   */
  private $email;

  /**
   * @var enummemberstatus|null
   *
   * @ORM\Column(type="enummemberstatus", nullable=true, options={"default"="regular","comment"="passive, soloist, conductor and temporary are excluded from mass-email. soloist and conductor are even excluded from ""per-project"" email unless explicitly selected."})
   */
  private $memberStatus = 'regular';

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $remarks;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=false, options={"default"="0"})
   */
  private $disabled = '0';

  /**
   * @var \DateTime|null
   *
   * @ORM\Column(type="datetime", nullable=true)
   */
  private $updated;

  /**
   * @ORM\OneToMany(targetEntity="MusicianInstrument", mappedBy="musician", orphanRemoval=true)
   */
  private $instruments;

  /**
   * Inverse side.
   *
   * @ORM\OneToOne(targetEntity="MusicianPhoto", mappedBy="owner")
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
   * Set firstName.
   *
   * @param string $firstName
   *
   * @return Musician
   */
  public function setFirstName($firstName)
  {
    $this->firstName = $firstName;

    return $this;
  }

  /**
   * Get firstName.
   *
   * @return string
   */
  public function getFirstName()
  {
    return $this->firstName;
  }

  /**
   * Set city.
   *
   * @param string $city
   *
   * @return Musician
   */
  public function setCity($city)
  {
    $this->city = $city;

    return $this;
  }

  /**
   * Get city.
   *
   * @return string
   */
  public function getCity()
  {
    return $this->city;
  }

  /**
   * Set street.
   *
   * @param string $street
   *
   * @return Musician
   */
  public function setStreet($street)
  {
    $this->street = $street;

    return $this;
  }

  /**
   * Get street.
   *
   * @return string
   */
  public function getStreet()
  {
    return $this->street;
  }

  /**
   * Set postalCode.
   *
   * @param int|null $postalCode
   *
   * @return Musician
   */
  public function setPostalCode($postalCode = null)
  {
    $this->postalCode = $postalCode;

    return $this;
  }

  /**
   * Get postalCode.
   *
   * @return int|null
   */
  public function getPostalCode()
  {
    return $this->postalCode;
  }

  /**
   * Set country.
   *
   * @param string $country
   *
   * @return Musician
   */
  public function setCountry($country)
  {
    $this->country = $country;

    return $this;
  }

  /**
   * Get country.
   *
   * @return string
   */
  public function getCountry()
  {
    return $this->country;
  }

  /**
   * Set language.
   *
   * @param string $language
   *
   * @return Musician
   */
  public function setLanguage($language)
  {
    $this->language = $language;

    return $this;
  }

  /**
   * Get language.
   *
   * @return string
   */
  public function getLanguage()
  {
    return $this->language;
  }

  /**
   * Set mobilePhone.
   *
   * @param string $mobilePhone
   *
   * @return Musician
   */
  public function setMobilePhone($mobilePhone)
  {
    $this->mobilePhone = $mobilePhone;

    return $this;
  }

  /**
   * Get mobilePhone.
   *
   * @return string
   */
  public function getMobilePhone()
  {
    return $this->mobilePhone;
  }

  /**
   * Set fixedLinePhone.
   *
   * @param string $fixedLinePhone
   *
   * @return Musician
   */
  public function setFixedLinePhone($fixedLinePhone)
  {
    $this->fixedLinePhone = $fixedLinePhone;

    return $this;
  }

  /**
   * Get fixedLinePhone.
   *
   * @return string
   */
  public function getFixedLinePhone()
  {
    return $this->fixedLinePhone;
  }

  /**
   * Set birthday.
   *
   * @param \DateTime|null $birthday
   *
   * @return Musician
   */
  public function setBirthday($birthday = null)
  {
    $this->birthday = $birthday;

    return $this;
  }

  /**
   * Get birthday.
   *
   * @return \DateTime|null
   */
  public function getBirthday()
  {
    return $this->birthday;
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
   * Set memberStatus.
   *
   * @param enummemberstatus|null $memberStatus
   *
   * @return Musician
   */
  public function setMemberStatus($memberStatus = null)
  {
    $this->memberStatus = $memberStatus;

    return $this;
  }

  /**
   * Get memberStatus.
   *
   * @return enummemberstatus|null
   */
  public function getMemberStatus()
  {
    return $this->memberStatus;
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
   * Set updated.
   *
   * @param \DateTime|null $updated
   *
   * @return Musician
   */
  public function setUpdated($updated = null)
  {
    $this->updated = $updated;

    return $this;
  }

  /**
   * Get updated.
   *
   * @return \DateTime|null
   */
  public function getUpdated()
  {
    return $this->updated;
  }

  /**
   * Set instruments.
   *
   * @param Image $instruments|null
   *
   * @return Musician
   */
  public function setInstruments($instruments = null)
  {
    $this->instruments = $instruments;

    return $this;
  }

  /**
   * Get instruments.
   *
   * @return Image|null
   */
  public function getInstruments()
  {
    return $this->instruments;
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

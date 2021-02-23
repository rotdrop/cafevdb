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

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Musician
 *
 * @ORM\Table(name="Musicians")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusiciansRepository")
 * @ORM\HasLifecycleCallbacks
 */
class Musician implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\UuidTrait;
  use CAFEVDB\Traits\UpdatedAt;
  use CAFEVDB\Traits\CreatedAtEntity;

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
  private $surName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false)
   */
  private $firstName;

  /**
   * Display name, replaces default "$surName, $firstName"
   *
   * @var string
   * @ORM\Column(type="string", length=256, nullable=true)
   */
  private $displayName;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $city;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $street;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, nullable=true)
   */
  private $country;

  /**
   * @var int|null
   *
   * @ORM\Column(type="string", length=32, nullable=true)
   */
  private $postalCode;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=true, options={"comment"="Und was es sonst noch so gibt ..."})
   */
  private $language;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $mobilePhone;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=true)
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
   * @var Types\EnumMemberStatus|null
   *
   * @ORM\Column(type="EnumMemberStatus", nullable=false, options={"default"="regular","comment"="passive, soloist, conductor and temporary are excluded from mass-email. soloist and conductor are even excluded from ""per-project"" email unless explicitly selected."})
   */
  private $memberStatus;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $remarks;

  /**
   * @var bool
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"="0"})
   */
  private $disabled = false;

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

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipant", mappedBy="musician", fetch="EXTRA_LAZY")
   */
  private $projectParticipation;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="musician", fetch="EXTRA_LAZY")
   */
  private $projectInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectExtraFieldDatum", mappedBy="musician", fetch="EXTRA_LAZY")
   */
  private $projectExtraFieldsData;

  /**
   * @ORM\OneToMany(targetEntity="InstrumentInsurance", mappedBy="instrumentHolder", fetch="EXTRA_LAZY")
   */
  private $instrumentInsurances;

  /**
   * @ORM\OneToMany(targetEntity="InstrumentInsurance", mappedBy="billToParty", fetch="EXTRA_LAZY")
   */
  private $payableInsurances;

  /**
   * @ORM\OneToMany(targetEntity="SepaDebitMandate", mappedBy="musician", fetch="EXTRA_LAZY")
   */
  private $sepaDebitMandates;

  /**
   * @ORM\OneToMany(targetEntity="ProjectPayment", mappedBy="musician", fetch="EXTRA_LAZY")
   */
  private $payments;

  /**
   * @var \DateTimeImmutable
   * @Gedmo\Timestampable(on={"update","change"}, field={"photo.updated"})
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  private $updated;

  public function __construct() {
    $this->arrayCTOR();
    $this->instruments = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
    $this->projectParticipation = new ArrayCollection();
    $this->projectExtraFieldsData = new ArrayCollection();
    $this->instrumentInsurances = new ArrayCollection();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->payments = new ArrayCollection();

    $this->memberStatus = Types\EnumMemberStatus::REGULAR();
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
   * Set surName.
   *
   * @param string $surName
   *
   * @return Musician
   */
  public function setSurName($surName)
  {
    $this->surName = $surName;

    return $this;
  }

  /**
   * Get surName.
   *
   * @return string
   */
  public function getSurName()
  {
    return $this->surName;
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
   * @param \DateTime|string|null $birthday If a string is passed it
   *        has to be in a format understood by the constructor of
   *        \DateTime.
   *
   *
   * @return Musician
   */
  public function setBirthday($birthday = null)
  {
    if (is_string($birthday)) {
      try {
        $birthday = new \DateTime($birthday);
      } catch (\Throwable $t) {
        throw new \Exception("Could not convert `$birthday' to DateTime-object", $t->getCode(), $t);
      }
    }
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
   * @param string|EnumMemberStatus $memberStatus
   *
   * @return Musician
   */
  public function setMemberStatus($memberStatus):Musician
  {
    $this->memberStatus = new Types\EnumMemberStatus($memberStatus);

    return $this;
  }

  /**
   * Get memberStatus.
   *
   * @return EnumMemberStatus
   */
  public function getMemberStatus():Types\EnumMemberStatus
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
  public function setRemarks(?string $remarks = null):Musician
  {
    $this->remarks = $remarks;

    return $this;
  }

  /**
   * Get remarks.
   *
   * @return string|null
   */
  public function getRemarks():?string
  {
    return $this->remarks;
  }

  /**
   * Set disabled.
   *
   * @param bool|null $disabled
   *
   * @return Musician
   */
  public function setDisabled(?bool $disabled):Musician
  {
    $this->disabled = $disabled;

    return $this;
  }

  /**
   * Get disabled.
   *
   * @return bool|null
   */
  public function getDisabled():?bool
  {
    return $this->disabled;
  }

  /**
   * Set instruments.
   *
   * @param Collection $instruments
   *
   * @return Musician
   */
  public function setInstruments(Collection $instruments):Musician
  {
    $this->instruments = $instruments;

    return $this;
  }

  /**
   * Get instruments.
   *
   * @return Collection
   */
  public function getInstruments():Collection
  {
    return $this->instruments;
  }

  /**
   * Set photo.
   *
   * @param MusicianPhoto|null $photo
   *
   * @return Musician
   */
  public function setPhoto(?MusicianPhoto $photo = null):Musician
  {
    $this->photo = $photo;

    return $this;
  }

  /**
   * Get photo.
   *
   * @return MusicianPhoto|null
   */
  public function getPhoto():?MusicianPhoto
  {
    return $this->photo;
  }

  /**
   * Set projectInstruments.
   *
   * @param Collection $projectInstruments
   *
   * @return Musician
   */
  public function setProjectInstruments(Collection $projectInstruments):Musician
  {
    $this->projectInstruments = $projectInstruments;

    return $this;
  }

  /**
   * Get projectInstruments.
   *
   * @return Collection
   */
  public function getProjectInstruments():Collection
  {
    return $this->projectInstruments;
  }

  /**
   * Set projectParticipation.
   *
   * @param Collection $projectParticipation
   *
   * @return Musician
   */
  public function setProjectParticipation(Collection $projectParticipation):Musician
  {
    $this->projectParticipation = $projectParticipation;

    return $this;
  }

  /**
   * Get projectParticipation.
   *
   * @return Collection
   */
  public function getProjectParticipation():Collection
  {
    return $this->projectParticipation;
  }

  /**
   * Set projectExtraFieldsData.
   *
   * @param Collection $projectExtraFieldsData
   *
   * @return Musician
   */
  public function setProjectExtraFieldsData($projectExtraFieldsData):Musician
  {
    $this->projectExtraFieldsData = $projectExtraFieldsData;

    return $this;
  }

  /**
   * Get projectExtraFieldsData.
   *
   * @return Collection
   */
  public function getProjectExtraFieldsData():Collection
  {
    return $this->projectExtraFieldsData;
  }

  /**
   * Set instrumentInsurances.
   *
   * @param Collection $instrumentInsurances
   *
   * @return Musician
   */
  public function setInstrumentInsurances($instrumentInsurances):Musician
  {
    $this->instrumentInsurances = $instrumentInsurances;

    return $this;
  }

  /**
   * Get instrumentInsurances.
   *
   * @return Collection
   */
  public function getInstrumentInsurances():Collection
  {
    return $this->instrumentInsurances;
  }

  /**
   * Set payableInsurances.
   *
   * @param Collection $payableInsurances
   *
   * @return Musician
   */
  public function setPayableInsurances($payableInsurances):Musician
  {
    $this->payableInsurances = $payableInsurances;

    return $this;
  }

  /**
   * Get payableInsurances.
   *
   * @return Collection
   */
  public function getPayableInsurances():Collection
  {
    return $this->payableInsurances;
  }

  /**
   * Set payments.
   *
   * @param Collection $payments
   *
   * @return Musician
   */
  public function setPayments($payments):Musician
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
   * @return Musician
   */
  public function setSepaDebitMandates($sepaDebitMandates):Musician
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

}

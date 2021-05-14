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
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Musician
 *
 * @ORM\Table(name="Musicians")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusiciansRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 */
class Musician implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\UuidTrait;
  use CAFEVDB\Traits\UpdatedAt;
  use CAFEVDB\Traits\CreatedAtEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;
  use CAFEVDB\Traits\GetByUuidTrait;

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
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $nickName;

  /**
   * Display name, replaces default "$surName, $firstName"
   *
   * @var string
   * @ORM\Column(type="string", length=256, nullable=true)
   */
  private $displayName;

  /**
   * This should look like a suitable user id, e.g.
   *
   * "official":  firstName.surName, nickName.surName
   * "personal":  firstName or firstname-FIRSTLETER_OF_SURNAME, e.g kathap, kathid
   *
   * We use the semi-official nickName.surName, e.g. katha.puff. This
   * is achieved by passing the firstName as well as the nickName to
   * the slug generator. It will just remove empty fields.
   *
   * @ORM\Column(type="string", length=256, unique=true, nullable=true, options={"collation"="ascii_bin"})
   * @Gedmo\Slug(
   *   fields={"firstName", "nickName", "surName"},
   *   separator=".", unique=true, updatable=true,
   *   handlers={
   *     @Gedmo\SlugHandler(
   *       class="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\Sluggable\LoginNameSlugHandler",
   *       options={
   *         @Gedmo\SlugHandlerOption(name="separator", value="-"),
   *         @Gedmo\SlugHandlerOption(name="preferred", value={1,2})
   *       })
   *   })
   */
  private $userIdSlug;

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
   * @ORM\Column(type="string", length=2, nullable=true, options={"fixed" = true, "collation"="ascii_general_ci"})
   */
  private $country;

  /**
   * @var int|null
   *
   * @ORM\Column(type="string", length=32, nullable=true, options={"collation"="ascii_general_ci"})
   */
  private $postalCode;

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=2, nullable=true, options={"fixed" = true, "collation"="ascii_general_ci"})
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
   * @ORM\Column(type="date_immutable", nullable=true)
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
   * @ORM\OneToMany(targetEntity="MusicianInstrument", mappedBy="musician", orphanRemoval=true)
   */
  private $instruments;

  /**
   * Inverse side.
   *
   * @ORM\OneToOne(targetEntity="MusicianPhoto", mappedBy="owner", orphanRemoval=true)
   */
  private $photo;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipant", mappedBy="musician", indexBy="project", orphanRemoval=true, fetch="EXTRA_LAZY")
   * @Gedmo\SoftDeleteableCascade(delete=true, undelete=true)
   */
  private $projectParticipation;

  /**
   * @ORM\OneToMany(targetEntity="ProjectInstrument", mappedBy="musician", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $projectInstruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipantFieldDatum", mappedBy="musician", indexBy="option_key", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $projectParticipantFieldsData;

  /**
   * @ORM\OneToMany(targetEntity="InstrumentInsurance", mappedBy="instrumentHolder", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $instrumentInsurances;

  /**
   * @ORM\OneToMany(targetEntity="InstrumentInsurance", mappedBy="billToParty", fetch="EXTRA_LAZY")
   */
  private $payableInsurances;

  /**
   * @ORM\OneToMany(targetEntity="SepaBankAccount", mappedBy="musician", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $sepaBankAccounts;

  /**
   * @ORM\OneToMany(targetEntity="SepaDebitMandate", mappedBy="musician", orphanRemoval=true, fetch="EXTRA_LAZY")
   */
  private $sepaDebitMandates;

  /**
   * @ORM\OneToMany(targetEntity="CompositePayment", mappedBy="musician", orphanRemoval=true, fetch="EXTRA_LAZY")
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
    $this->projectParticipantFieldsData = new ArrayCollection();
    $this->instrumentInsurances = new ArrayCollection();
    $this->sepaBankAccounts = new ArrayCollection();
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
  public function setId($id):Musician
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
   * Check whether the given project is contained in projectParticipation.
   *
   * @param int|Project $projectOrId
   *
   * @return bool
   */
  public function isMemberOf($projectOrId):bool
  {
    return !empty($this->getProjectParticipantOf($projectOrId));
  }

  /**
   * Return the project-participant entity for the given project or null
   *
   * @param int|Project $projectOrId
   *
   * @return null|ProjectParticipant
   */
  public function getProjectParticipantOf($projectOrId)
  {
    $projectId = ($projectOrId instanceof Project) ? $projectOrId->getId() : $projectOrId;
    $participant = $this->projectParticipation->get($projectId);
    if (!empty($participant)) {
      return $participant;
    }
    // $matching = $this->projectParticipation->matching(DBUtil::criteriaWhere([
    //   'project' => $projectId,
    // ]));
    //
    // The infamous
    //
    // Cannot match on
    // OCA\CAFEVDB\Database\Doctrine\ORM\Entities\ProjectParticipant::project
    // with a non-object value. Matching objects by id is not
    // compatible with matching on an in-memory collection, which
    // compares objects by reference.
    //
    // Oh no.

    $matching = $this->projectParticipation->filter(function($participant) use ($projectId) {
      return $participant->getProject()->getId() == $projectId;
    });
    if ($matching->count() == 1) {
      return $matching->first();
    }
    return null;
  }

  /**
   * Set projectParticipantFieldsData.
   *
   * @param Collection $projectParticipantFieldsData
   *
   * @return Musician
   */
  public function setProjectParticipantFieldsData($projectParticipantFieldsData):Musician
  {
    $this->projectParticipantFieldsData = $projectParticipantFieldsData;

    return $this;
  }

  /**
   * Get projectParticipantFieldsData.
   *
   * @return Collection
   */
  public function getProjectParticipantFieldsData():Collection
  {
    return $this->projectParticipantFieldsData;
  }

  /**
   * Get one specific participant-field datum indexed by its key
   *
   * @param mixed $key Everything which can be converted to an UUID by
   * Uuid::asUuid().
   *
   * @return null|ProjectParticipantFieldDatum
   */
  public function getProjectParticipantFieldsDatum($key):?ProjectParticipantFieldDatum
  {
    return $this->getByUuid($this->projectParticipantFieldsData, $key, 'optionKey');
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
   * Set sepaBankAccounts.
   *
   * @param Collection $sepaBankAccounts
   *
   * @return Musician
   */
  public function setSepaBankAccounts(Collection $sepaBankAccounts):Musician
  {
    $this->sepaBankAccounts = $sepaBankAccounts;

    return $this;
  }

  /**
   * Get sepaBankAccounts.
   *
   * @return Collection
   */
  public function getSepaBankAccounts():Collection
  {
    return $this->sepaBankAccounts;
  }

  /**
   * Set displayName.
   *
   * @param string|null $displayName
   *
   * @return Musician
   */
  public function setDisplayName(?string $displayName):Musician
  {
    $this->displayName = $displayName;

    return $this;
  }

  /**
   * Get displayName.
   *
   * @return string
   */
  public function getDisplayName():?string
  {
    return $this->displayName;
  }

  /**
   * Set nickName.
   *
   * @param string|null $nickName
   *
   * @return Musician
   */
  public function setNickName(?string $nickName):Musician
  {
    $this->nickName = $nickName;

    return $this;
  }

  /**
   * Get nickName.
   *
   * @return string
   */
  public function getNickName():?string
  {
    return $this->nickName;
  }


  /**
   * Get the cooked display-name, taking nick-name into account and
   * just using $displayName if set.
   */
  public function getPublicName()
  {
    if (!empty($this->displayName)) {
      return $this->displayName;
    }
    $firstName = empty($this->nickName) ? $this->firstName : $this->nickName;
    return $this->surName.', '.$firstName;
  }

  /**
   * Set userIdSlug.
   *
   * @param string|null $userIdSlug
   *
   * @return Musician
   */
  public function setUserIdSlug(?string $userIdSlug):Musician
  {
    $this->userIdSlug = $userIdSlug;

    return $this;
  }

  /**
   * Get userIdSlug.
   *
   * @return string
   */
  public function getUserIdSlug():?string
  {
    return $this->userIdSlug;
  }

  /**
   * Return the number of "serious" items which "use" this entity. For
   * project participant this is (for now) the number of payments. In
   * the long run: only open payments/receivables should count.
   */
  public function usage():int
  {
    return $this->payments->count();
  }
}

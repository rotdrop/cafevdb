<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use \InvalidArgumentException;

use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Common\Uuid;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;

use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\Collection;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Event;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Gedmo\Mapping\Annotation as Gedmo;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Musician
 *
 * @ORM\Table(name="Musicians")
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\MusiciansRepository")
 * @Gedmo\SoftDeleteable(
 *   fieldName="deleted",
 *   hardDelete="OCA\CAFEVDB\Database\Doctrine\ORM\Listeners\SoftDeleteable\HardDeleteExpiredUnused"
 * )
 * @ORM\HasLifecycleCallbacks
 * @ORM\EntityListeners({"\OCA\CAFEVDB\Listener\MusicianEntityListener"})
 *
 * @SuppressWarnings(PHPMD.UnusedPrivateField)
 */
class Musician implements \ArrayAccess, \JsonSerializable
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;
  use CAFEVDB\Traits\UuidTrait;
  use CAFEVDB\Traits\UpdatedAt;
  use CAFEVDB\Traits\CreatedAtEntity;
  use CAFEVDB\Traits\SoftDeleteableEntity;
  use CAFEVDB\Traits\UnusedTrait;
  use CAFEVDB\Traits\GetByUuidTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

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
   * Meant for per-user authentication which might be used for future
   * extensions.
   *
   * @ORM\Column(type="string", length=256, unique=false, nullable=true, options={"collation"="ascii_bin"})
   */
  private $userPassphrase;

  /**
   * @var MusicianRowAccessToken
   *
   * @ORM\OneToOne(targetEntity="MusicianRowAccessToken", mappedBy="musician", cascade={"all"}, orphanRemoval=true)
   */
  private $rowAccessToken;

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
   * The street-number. I may actually be alpha-numeric like "2a" or something, so it is a string.
   *
   * @ORM\Column(type="string", length=32, nullable=true)
   */
  private $streetNumber;

  /**
   * @var string
   *
   * Additional address information, like "Appartment 200" or c/o.
   *
   * @ORM\Column(type="string", length=128, nullable=true)
   */
  private $addressSupplement;

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
   * @ORM\Column(type="string", length=254, nullable=true, options={"collation"="ascii_general_ci"})
   */
  private $email;

  /**
   * @var Collection All email addresses.
   *
   * @ORM\OneToMany(targetEntity="MusicianEmailAddress", mappedBy="musician", cascade={"remove","persist"}, orphanRemoval=true, indexBy="address")
   */
  private $emailAddresses;

  /**
   * @var Types\EnumMemberStatus|null
   *
   * @ORM\Column(
   *   type="EnumMemberStatus",
   *   nullable=false,
   *   options={
   *     "default"="regular",
   *     "comment"="passive, soloist, conductor and temporary are excluded from mass-email. soloist and conductor are even excluded from ""per-project"" email unless explicitly selected."
   *   }
   * )
   */
  private $memberStatus;

  /**
   * @var string|null
   *
   * @ORM\Column(type="string", length=1024, nullable=true)
   */
  private $remarks;

  /**
   * @var bool|null
   *
   * The effect of setting this to true is that the user can no longer login
   * in the cloud but remains visible as cloud user-account.
   *
   * Set to true if for whatever reason the user remains undeleted in the
   * musician-database but its cloud-account needs to be deactivated, for
   * instance to prevent abuse after a password breach or things like that.
   *
   * This only affects the cloud-account of DB-musicians. It can be set by
   * admins and group-admins through the cloud admin UI.
   *
   * @ORM\Column(type="boolean", nullable=true)
   */
  private $cloudAccountDeactivated;

  /**
   * @var bool|null
   *
   * Set to true if the cloud user-account should not be generated at
   * all. This differs from $cloudAccountDeactivated in that with
   * "...Disabled" the musician is not even exported as user account, while
   * the "...Deactivated" flag can be changed by the cloud administrator.
   *
   * Not that deleted users are also not exported to the cloud.
   *
   * @ORM\Column(type="boolean", nullable=true, options={"default"=1})
   */
  private $cloudAccountDisabled;

  /**
   * @var LegalPerson
   *
   * @ORM\OneToOne(targetEntity="LegalPerson", mappedBy="musician", cascade={"remove"}, orphanRemoval=true)
   * @ORM\JoinColumns(
   *   @ORM\JoinColumn(name="legal_person_id",referencedColumnName="id", nullable=true),
   * )
   */
  private $legalPerson;

  /**
   * @ORM\OneToMany(targetEntity="MusicianInstrument", mappedBy="musician", cascade={"remove"}, orphanRemoval=true)
   * @Gedmo\SoftDeleteableCascade(delete=true, undelete=true)
   */
  private $instruments;

  /**
   * @ORM\OneToMany(targetEntity="ProjectParticipant", mappedBy="musician", indexBy="project_id", orphanRemoval=true, fetch="EXTRA_LAZY")
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
   * @var Collection
   *
   * @ORM\ManyToMany(targetEntity="EncryptedFile", inversedBy="owners", indexBy="id", fetch="EXTRA_LAZY")
   * @ORM\JoinTable(name="EncryptedFileOwners")
   *
   * The list of files owned by this musician. This is in particular important for
   * encrypted files where the list of owners determines the encryption keys
   * which are used to seal the data.
   */
  private $encryptedFiles;

  /**
   * @var \DateTimeImmutable
   *
   * @ORM\Column(type="datetime_immutable", nullable=true)
   */
  protected $updated;

  /** {@inheritdoc} */
  public function __construct()
  {
    $this->__wakeup();
    $this->emailAddresses = new ArrayCollection();
    $this->instruments = new ArrayCollection();
    $this->projectInstruments = new ArrayCollection();
    $this->projectParticipation = new ArrayCollection();
    $this->projectParticipantFieldsData = new ArrayCollection();
    $this->instrumentInsurances = new ArrayCollection();
    $this->payableInsurances = new ArrayCollection();
    $this->sepaBankAccounts = new ArrayCollection();
    $this->sepaDebitMandates = new ArrayCollection();
    $this->payments = new ArrayCollection();
    $this->encryptedFiles = new ArrayCollection();

    $this->memberStatus = Types\EnumMemberStatus::REGULAR();
  }

  /** {@inheritdoc} */
  public function __wakeup()
  {
    $this->arrayCTOR();
    $this->keys[] = 'publicName';
  }

  /**
   * Set id.
   *
   * @param null|int $id
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
   * @return null|int
   */
  public function getId():?int
  {
    return $this->id;
  }

  /**
   * Set surName.
   *
   * @param null|string $surName
   *
   * @return Musician
   */
  public function setSurName(?string $surName):Musician
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
   * @param null|string $firstName
   *
   * @return Musician
   */
  public function setFirstName(?string $firstName):Musician
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
   * @param null|string $city
   *
   * @return Musician
   */
  public function setCity(?string $city):Musician
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
   * @param null|string $street
   *
   * @return Musician
   */
  public function setStreet(?string $street):Musician
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
   * Set streetNumber.
   *
   * @param null|string $streetNumber
   *
   * @return Musician
   */
  public function setStreetNumber(?string $streetNumber):Musician
  {
    $this->streetNumber = $streetNumber;

    return $this;
  }

  /**
   * Get streetNumber.
   *
   * @return string
   */
  public function getStreetNumber()
  {
    return $this->streetNumber;
  }

  /**
   * Set addressSupplement.
   *
   * @param null|string $addressSupplement
   *
   * @return Musician
   */
  public function setAddressSupplement(?string $addressSupplement):Musician
  {
    $this->addressSupplement = $addressSupplement;

    return $this;
  }

  /**
   * Get addressSupplement.
   *
   * @return string
   */
  public function getAddressSupplement()
  {
    return $this->addressSupplement;
  }

  /**
   * Set postalCode.
   *
   * @param int|null $postalCode
   *
   * @return Musician
   */
  public function setPostalCode($postalCode = null):Musician
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
   * @param null|string $country
   *
   * @return Musician
   */
  public function setCountry(?string $country):Musician
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
   * @param null|string $language
   *
   * @return Musician
   */
  public function setLanguage(?string $language):Musician
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
   * @param null|string $mobilePhone
   *
   * @return Musician
   */
  public function setMobilePhone(?string $mobilePhone):Musician
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
   * @param null|string $fixedLinePhone
   *
   * @return Musician
   */
  public function setFixedLinePhone(?string $fixedLinePhone):Musician
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
   * @param string|int|\DateTimeInterface $birthday
   *
   * @return Musician
   */
  public function setBirthday($birthday):Musician
  {
    $this->birthday = self::convertToDateTime($birthday);

    return $this;
  }

  /**
   * Get birthday.
   *
   * @return \DateTimeInterface|null
   */
  public function getBirthday():?\DateTimeInterface
  {
    return $this->birthday;
  }

  /**
   * Set the principal email address as address-entity. Updates also the
   * plain-text field $email.
   *
   * @param null|MusicianEmailAddress $principalEmailAddress
   *
   * @return Musician
   */
  public function setPrincipalEmailAddress(?MusicianEmailAddress $principalEmailAddress):Musician
  {
    if (empty($principalEmailAddress)) {
      $this->email = null;
    } else {
      $this->email = $principalEmailAddress->getAddress();
      $this->emailAddresses->set($this->email, $principalEmailAddress);
    }

    return $this;
  }

  /**
   * @return MusicianEmailAddress
   */
  public function getPrincipalEmailAddress():?MusicianEmailAddress
  {
    return $this->emailAddresses->get($this->email);
  }

  /**
   * Set email. Update related associations, add to the collection of all
   * emails if not already there and set the principal email address.
   *
   * @param null|string $email
   *
   * @return Musician
   */
  public function setEmail(?string $email):Musician
  {
    if (empty($email)) {
      $this->email = null;
      return $this;
    }
    $email = strtolower($email);
    $this->email = $email;
    // check by key
    if ($this->emailAddresses->containsKey($email)) {
      return $this;
    }
    // if indexing is broken seach through the collection
    $emails = $this->emailAddresses->filter(fn(MusicianEmailAddress $addressEntity) => $addressEntity->getAddress() == $email);
    if (count($emails) === 1) {
      return $this;
    }
    // otherwise make a new entity
    $addressEntity = new MusicianEmailAddress($email, $this);
    $this->emailAddresses->set($email, $addressEntity);

    return $this;
  }

  /**
   * Get email.
   *
   * @return null|string
   */
  public function getEmail():?string
  {
    return $this->email === null ? null : strtolower($this->email);
  }

  /**
   * Set emailAddresses.
   *
   * @param Collection $emailAddresses
   *
   * @return Musician
   */
  public function setEmailAddresses(Collection $emailAddresses):Musician
  {
    $this->emailAddresses = $emailAddresses;

    return $this;
  }

  /**
   * Get emailAddresses.
   *
   * @return Collection
   */
  public function getEmailAddresses():Collection
  {
    return $this->emailAddresses;
  }

  /**
   * Add a new email-address to the collection
   *
   * @param string|MusicianEmailAddress $email
   *
   * @return Musician $this for easy chaining
   */
  public function addEmailAddress(mixed $email):Musician
  {
    if ($email instanceof MusicianEmailAddress) {
      /** @var MusicianEmailAddress $email */
      $this->emailAddresses->set($email->getAddress(), $email);
      return $this;
    }
    // check by key
    if ($this->emailAddresses->containsKey($email)) {
      return $this; // already there
    }
    // if indexing is broken seach through the collection
    $emails = $this->emailAddresses->filter(fn(MusicianEmailAddress $addressEntity) => $addressEntity->getAddress() == $email);
    if (count($emails) === 1) {
      return $this; // already there
    }
    // otherwise make a new entity
    $addressEntity = new MusicianEmailAddress($email, $this);
    $this->emailAddresses->set($email, $addressEntity);

    return $this;
  }

  /**
   * Remove the given email address.
   *
  * @param string|MusicianEmailAddress $email
   *
   * @return Musician $this for easy chaining
   */
  public function removeEmailAddress(mixed $email):Musician
  {
    if ($email instanceof MusicianEmailAddress) {
      $email = $email->getAddress();
    }
    if ($this->email == $email) {
      // violates not-null constraint but allow here for intermediate updates.
      $this->email = null;
      $this->principalEmailAddress = null;
    }
    // check by key
    if ($this->emailAddresses->containsKey($email)) {
      $this->emailAddresses->remove($email);
      return $this;
    }
    // if indexing is broken seach through the collection
    foreach ($this->emailAddresses as $existingAddress) {
      if ($existingAddress->getAddress() == $email) {
        $this->emailAddresses->removeElement($existingAddress);
        return $this;
      }
    }
    return $this;
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
   * Set cloudAccountDisabled.
   *
   * @param null|bool $cloudAccountDisabled
   *
   * @return Musician
   */
  public function setCloudAccountDisabled(?bool $cloudAccountDisabled):Musician
  {
    $this->cloudAccountDisabled = $cloudAccountDisabled;

    return $this;
  }

  /**
   * Get cloudAccountDisabled.
   *
   * @return null|bool
   */
  public function getCloudAccountDisabled():?bool
  {
    return $this->cloudAccountDisabled;
  }

  /**
   * Set cloudAccountDeactivated.
   *
   * @param null|bool $cloudAccountDeactivated
   *
   * @return Musician
   */
  public function setCloudAccountDeactivated(?bool $cloudAccountDeactivated):Musician
  {
    $this->cloudAccountDeactivated = $cloudAccountDeactivated;

    return $this;
  }

  /**
   * Get cloudAccountDeactivated.
   *
   * @return null|bool
   */
  public function getCloudAccountDeactivated():?bool
  {
    return $this->cloudAccountDeactivated;
  }

  /**
   * Set instruments.
   *
   * @param Collection $instruments
   *
   * @return Musician
   */
  public function setInstruments(?Collection $instruments):Musician
  {
    if ($instruments === null) {
      $instruments = new ArrayCollection;
    }
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
  public function getProjectParticipantOf($projectOrId):?ProjectParticipant
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
  public function setProjectParticipantFieldsData(Collection $projectParticipantFieldsData):Musician
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
  public function getProjectParticipantFieldsDatum(mixed $key):?ProjectParticipantFieldDatum
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
  public function setInstrumentInsurances(Collection $instrumentInsurances):Musician
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
  public function setPayableInsurances(Collection $payableInsurances):Musician
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
  public function setPayments(Collection $payments):Musician
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
   * Set sepaDebitMandates.
   *
   * @param Collection $sepaDebitMandates
   *
   * @return Musician
   */
  public function setSepaDebitMandates(Collection $sepaDebitMandates):Musician
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
   * Set encryptedFiles.
   *
   * @param Collection $encryptedFiles
   *
   * @return Musician
   */
  public function setEncryptedFiles(Collection $encryptedFiles):Musician
  {
    $this->encryptedFiles = $encryptedFiles;

    return $this;
  }

  /**
   * Get encryptedFiles.
   *
   * @return Collection
   */
  public function getEncryptedFiles():Collection
  {
    return $this->encryptedFiles;
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
   * just using $displayName if that set.
   *
   * @param bool $firstNameFirst If true return "FIRSTNAME LASTNAME" rather
   * than "LASTNAME, FIRSTNAME".
   *
   * @return string
   */
  public function getPublicName(bool $firstNameFirst = false):string
  {
    $firstName = empty($this->nickName) ? $this->firstName : $this->nickName;
    if ($firstNameFirst) {
      if (!empty($firstName) && !empty($this->surName)) {
        return $firstName . ' ' . $this->surName;
      }
      if (empty($firstName) && empty($this->surName)) {
        return $this->displayName ?? '';
      }
      if (empty($this->surName)) {
        return $firstName;
      }
      if (empty($firstName)) {
        return $this->surName;
      }
    }
    if (!empty($this->displayName)) {
      return $this->displayName ?? '';
    }
    if (empty($this->surName)) {
      return $firstName;
    }
    if (empty($firstName)) {
      return $this->surName;
    }
    return $this->surName . ', ' . $firstName;
  }

  /**
   * Compose initials from the public display name.
   *
   * @return string
   */
  public function getInitials():string
  {
    return array_reduce(preg_split('/[-_.\s]/', $this->getPublicName(firstNameFirst: true), -1, PREG_SPLIT_NO_EMPTY), fn($initials, $item) => $initials . $item[0]);
  }

  /**
   * Set userPassphrase.
   *
   * @param string|null $userPassphrase
   *
   * @return Musician
   */
  public function setUserPassphrase(?string $userPassphrase):Musician
  {
    $this->userPassphrase = $userPassphrase;

    return $this;
  }

  /**
   * Get userPassphrase.
   *
   * @return string
   */
  public function getUserPassphrase():?string
  {
    return $this->userPassphrase;
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
   * Set rowAccessToken.
   *
   * @param string|null $rowAccessToken
   *
   * @return Musician
   */
  public function setRowAccessToken(?MusicianRowAccessToken $rowAccessToken):Musician
  {
    $this->rowAccessToken = $rowAccessToken;

    return $this;
  }

  /**
   * Get rowAccessToken.
   *
   * @return MusicianRowAccessToken
   */
  public function getRowAccessToken():?MusicianRowAccessToken
  {
    return $this->rowAccessToken;
  }

  /**
   * Return the number of "serious" items which "use" this entity. For
   * project participants this is (for now) the number of payments. In
   * the long run: only open payments/receivables should count.
   *
   * @return int
   */
  public function usage():int
  {
    return $this->payments->count() + $this->projectParticipation->count();
  }

  /** {@inheritdoc} */
  public function jsonSerialize():array
  {
    return array_merge($this->toArray(), [
      'publicName' => $this->getPublicName(true),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @ORM\PrePersist
   *
   * @todo This should no longer be necessary.
   */
  public function prePersist(Event\LifecycleEventArgs $event)
  {
    $this->email = strtolower($this->email);
    $this->prePersistUuid();
  }

  /** {@inheritdoc} */
  public function __toString():string
  {
    $name = $this->getPublicName(firstNameFirst: true);
    if (!empty($this->userIdSlug)) {
      $name .= ' (' . $this->userIdSlug . ')';
    }
    return $name;
  }
}

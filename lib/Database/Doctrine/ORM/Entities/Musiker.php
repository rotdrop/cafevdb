<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;
use Doctrine\ORM\Mapping as ORM;

/**
 * Musiker
 *
 * @ORM\Table(name="Musiker")
 * @ORM\Entity(repositoryClass="CAFEVDB\Repositories\MusikerRepository")
 * @ORM\Entity @ORM\EntityListeners({"ArrayConstructor"})
 */
class Musiker
    extends ArrayConstructor
    implements \ArrayAccess
{
    use FactoryTrait;

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
     * @var string|null
     *
     * @ORM\Column(name="UUID", type="string", length=36, nullable=true, options={"fixed"=true})
     */
    private $uuid;

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

    public function __construct() {
        $this->arrayCTOR();
    }

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return GeoPostalCodeTranslations
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * @return Musiker
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
     * Set uuid.
     *
     * @param string|null $uuid
     *
     * @return Musiker
     */
    public function setUuid($uuid = null)
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * Get uuid.
     *
     * @return string|null
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Set disabled.
     *
     * @param bool $disabled
     *
     * @return Musiker
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
     * @return Musiker
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
}

<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * InstrumentInsurance
 *
 * @ORM\Table(name="InstrumentInsurance", indexes={@ORM\Index(columns={"musician_id"})})
 * @ORM\Entity
 */
class InstrumentInsurance
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
    private $musicianId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=false)
     */
    private $broker;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=false)
     */
    private $geographicalScope;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=false)
     */
    private $object;

    /**
     * @var array
     *
     * @ORM\Column(type="simple_array", length=0, nullable=false, options={"default"="false"})
     */
    private $accessory = 'false';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=false)
     */
    private $manufacturer;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=64, nullable=false)
     */
    private $yearOfConstruction;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    private $insuranceAmount;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"default"="0"})
     */
    private $billToParty = '0';

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="date", nullable=false)
     */
    private $startOfInsurance;



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
     * Set musicianId.
     *
     * @param int $musicianId
     *
     * @return InstrumentInsurance
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
     * Set broker.
     *
     * @param string $broker
     *
     * @return InstrumentInsurance
     */
    public function setBroker($broker)
    {
        $this->broker = $broker;

        return $this;
    }

    /**
     * Get broker.
     *
     * @return string
     */
    public function getBroker()
    {
        return $this->broker;
    }

    /**
     * Set geographicalScope.
     *
     * @param string $geographicalScope
     *
     * @return InstrumentInsurance
     */
    public function setGeographicalScope($geographicalScope)
    {
        $this->geographicalScope = $geographicalScope;

        return $this;
    }

    /**
     * Get geographicalScope.
     *
     * @return string
     */
    public function getGeographicalScope()
    {
        return $this->geographicalScope;
    }

    /**
     * Set object.
     *
     * @param string $object
     *
     * @return InstrumentInsurance
     */
    public function setObject($object)
    {
        $this->object = $object;

        return $this;
    }

    /**
     * Get object.
     *
     * @return string
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Set accessory.
     *
     * @param array $accessory
     *
     * @return InstrumentInsurance
     */
    public function setAccessory($accessory)
    {
        $this->accessory = $accessory;

        return $this;
    }

    /**
     * Get accessory.
     *
     * @return array
     */
    public function getAccessory()
    {
        return $this->accessory;
    }

    /**
     * Set manufacturer.
     *
     * @param string $manufacturer
     *
     * @return InstrumentInsurance
     */
    public function setManufacturer($manufacturer)
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    /**
     * Get manufacturer.
     *
     * @return string
     */
    public function getManufacturer()
    {
        return $this->manufacturer;
    }

    /**
     * Set yearOfConstruction.
     *
     * @param string $yearOfConstruction
     *
     * @return InstrumentInsurance
     */
    public function setYearOfConstruction($yearOfConstruction)
    {
        $this->yearOfConstruction = $yearOfConstruction;

        return $this;
    }

    /**
     * Get yearOfConstruction.
     *
     * @return string
     */
    public function getYearOfConstruction()
    {
        return $this->yearOfConstruction;
    }

    /**
     * Set insuranceAmount.
     *
     * @param int $insuranceAmount
     *
     * @return InstrumentInsurance
     */
    public function setInsuranceAmount($insuranceAmount)
    {
        $this->insuranceAmount = $insuranceAmount;

        return $this;
    }

    /**
     * Get insuranceAmount.
     *
     * @return int
     */
    public function getInsuranceAmount()
    {
        return $this->insuranceAmount;
    }

    /**
     * Set billToParty.
     *
     * @param int $billToParty
     *
     * @return InstrumentInsurance
     */
    public function setBillToParty($billToParty)
    {
        $this->billToParty = $billToParty;

        return $this;
    }

    /**
     * Get billToParty.
     *
     * @return int
     */
    public function getBillToParty()
    {
        return $this->billToParty;
    }

    /**
     * Set startOfInsurance.
     *
     * @param \DateTime $startOfInsurance
     *
     * @return InstrumentInsurance
     */
    public function setStartOfInsurance($startOfInsurance)
    {
        $this->startOfInsurance = $startOfInsurance;

        return $this;
    }

    /**
     * Get startOfInsurance.
     *
     * @return \DateTime
     */
    public function getStartOfInsurance()
    {
        return $this->startOfInsurance;
    }
}

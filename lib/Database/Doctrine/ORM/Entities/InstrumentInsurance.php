<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * InstrumentInsurance
 *
 * @ORM\Table(name="InstrumentInsurance", indexes={@ORM\Index(name="MusikerId", columns={"MusicianId"})})
 * @ORM\Entity
 */
class InstrumentInsurance
{
    /**
     * @var int
     *
     * @ORM\Column(name="Id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="MusicianId", type="integer", nullable=false)
     */
    private $musicianid;

    /**
     * @var string
     *
     * @ORM\Column(name="Broker", type="string", length=128, nullable=false)
     */
    private $broker;

    /**
     * @var string
     *
     * @ORM\Column(name="GeographicalScope", type="string", length=128, nullable=false)
     */
    private $geographicalscope;

    /**
     * @var string
     *
     * @ORM\Column(name="Object", type="string", length=128, nullable=false)
     */
    private $object;

    /**
     * @var array
     *
     * @ORM\Column(name="Accessory", type="simple_array", length=0, nullable=false, options={"default"="false"})
     */
    private $accessory = 'false';

    /**
     * @var string
     *
     * @ORM\Column(name="Manufacturer", type="string", length=128, nullable=false)
     */
    private $manufacturer;

    /**
     * @var string
     *
     * @ORM\Column(name="YearOfConstruction", type="string", length=64, nullable=false)
     */
    private $yearofconstruction;

    /**
     * @var int
     *
     * @ORM\Column(name="InsuranceAmount", type="integer", nullable=false)
     */
    private $insuranceamount;

    /**
     * @var int
     *
     * @ORM\Column(name="BillToParty", type="integer", nullable=false, options={"default"="0"})
     */
    private $billtoparty = '0';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="StartOfInsurance", type="date", nullable=false)
     */
    private $startofinsurance;



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
     * Set musicianid.
     *
     * @param int $musicianid
     *
     * @return InstrumentInsurance
     */
    public function setMusicianid($musicianid)
    {
        $this->musicianid = $musicianid;

        return $this;
    }

    /**
     * Get musicianid.
     *
     * @return int
     */
    public function getMusicianid()
    {
        return $this->musicianid;
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
     * Set geographicalscope.
     *
     * @param string $geographicalscope
     *
     * @return InstrumentInsurance
     */
    public function setGeographicalscope($geographicalscope)
    {
        $this->geographicalscope = $geographicalscope;

        return $this;
    }

    /**
     * Get geographicalscope.
     *
     * @return string
     */
    public function getGeographicalscope()
    {
        return $this->geographicalscope;
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
     * Set yearofconstruction.
     *
     * @param string $yearofconstruction
     *
     * @return InstrumentInsurance
     */
    public function setYearofconstruction($yearofconstruction)
    {
        $this->yearofconstruction = $yearofconstruction;

        return $this;
    }

    /**
     * Get yearofconstruction.
     *
     * @return string
     */
    public function getYearofconstruction()
    {
        return $this->yearofconstruction;
    }

    /**
     * Set insuranceamount.
     *
     * @param int $insuranceamount
     *
     * @return InstrumentInsurance
     */
    public function setInsuranceamount($insuranceamount)
    {
        $this->insuranceamount = $insuranceamount;

        return $this;
    }

    /**
     * Get insuranceamount.
     *
     * @return int
     */
    public function getInsuranceamount()
    {
        return $this->insuranceamount;
    }

    /**
     * Set billtoparty.
     *
     * @param int $billtoparty
     *
     * @return InstrumentInsurance
     */
    public function setBilltoparty($billtoparty)
    {
        $this->billtoparty = $billtoparty;

        return $this;
    }

    /**
     * Get billtoparty.
     *
     * @return int
     */
    public function getBilltoparty()
    {
        return $this->billtoparty;
    }

    /**
     * Set startofinsurance.
     *
     * @param \DateTime $startofinsurance
     *
     * @return InstrumentInsurance
     */
    public function setStartofinsurance($startofinsurance)
    {
        $this->startofinsurance = $startofinsurance;

        return $this;
    }

    /**
     * Get startofinsurance.
     *
     * @return \DateTime
     */
    public function getStartofinsurance()
    {
        return $this->startofinsurance;
    }
}

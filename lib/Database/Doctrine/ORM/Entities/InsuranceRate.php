<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * InsuranceRates
 *
 * @ORM\Table(name="InsuranceRates", uniqueConstraints={@ORM\UniqueConstraint(columns={"Id"})})
 * @ORM\Entity
 */
class InsuranceRates
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
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=false)
     */
    private $broker;

    /**
     * @var array
     *
     * @ORM\Column(type="simple_array", length=0, nullable=false)
     */
    private $geographicalscope;

    /**
     * @var float
     *
     * @ORM\Column(type="float", precision=10, scale=0, nullable=false, options={"comment"="fraction, not percentage, excluding taxes"})
     */
    private $rate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="date", nullable=false, options={"comment"="start of the yearly insurance period"})
     */
    private $duedate;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private $policynumber;



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
     * Set broker.
     *
     * @param string $broker
     *
     * @return InsuranceRates
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
     * @param array $geographicalscope
     *
     * @return InsuranceRates
     */
    public function setGeographicalscope($geographicalscope)
    {
        $this->geographicalscope = $geographicalscope;

        return $this;
    }

    /**
     * Get geographicalscope.
     *
     * @return array
     */
    public function getGeographicalscope()
    {
        return $this->geographicalscope;
    }

    /**
     * Set rate.
     *
     * @param float $rate
     *
     * @return InsuranceRates
     */
    public function setRate($rate)
    {
        $this->rate = $rate;

        return $this;
    }

    /**
     * Get rate.
     *
     * @return float
     */
    public function getRate()
    {
        return $this->rate;
    }

    /**
     * Set duedate.
     *
     * @param \DateTime $duedate
     *
     * @return InsuranceRates
     */
    public function setDuedate($duedate)
    {
        $this->duedate = $duedate;

        return $this;
    }

    /**
     * Get duedate.
     *
     * @return \DateTime
     */
    public function getDuedate()
    {
        return $this->duedate;
    }

    /**
     * Set policynumber.
     *
     * @param string $policynumber
     *
     * @return InsuranceRates
     */
    public function setPolicynumber($policynumber)
    {
        $this->policynumber = $policynumber;

        return $this;
    }

    /**
     * Get policynumber.
     *
     * @return string
     */
    public function getPolicynumber()
    {
        return $this->policynumber;
    }
}

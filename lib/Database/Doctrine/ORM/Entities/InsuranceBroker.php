<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * InsuranceBrokers
 *
 * @ORM\Table(name="InsuranceBrokers", uniqueConstraints={@ORM\UniqueConstraint(columns={"short_name"})})
 * @ORM\Entity
 */
class InsuranceBroker
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
     * @ORM\Column(type="string", length=40, nullable=false)
     */
    private $shortName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=512, nullable=false)
     */
    private $longName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=512, nullable=false)
     */
    private $address;



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
     * Set shortName.
     *
     * @param string $shortName
     *
     * @return InsuranceBrokers
     */
    public function setShortName($shortName)
    {
        $this->shortName = $shortName;

        return $this;
    }

    /**
     * Get shortName.
     *
     * @return string
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    /**
     * Set longName.
     *
     * @param string $longName
     *
     * @return InsuranceBrokers
     */
    public function setLongName($longName)
    {
        $this->longName = $longName;

        return $this;
    }

    /**
     * Get longName.
     *
     * @return string
     */
    public function getLongName()
    {
        return $this->longName;
    }

    /**
     * Set address.
     *
     * @param string $address
     *
     * @return InsuranceBrokers
     */
    public function setAddress($address)
    {
        $this->address = $address;

        return $this;
    }

    /**
     * Get address.
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }
}

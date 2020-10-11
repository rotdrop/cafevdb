<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * InsuranceBrokers
 *
 * @ORM\Table(name="InsuranceBrokers", uniqueConstraints={@ORM\UniqueConstraint(name="ShortName", columns={"ShortName"})})
 * @ORM\Entity
 */
class InsuranceBrokers
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
     * @var string
     *
     * @ORM\Column(name="ShortName", type="string", length=40, nullable=false)
     */
    private $shortname;

    /**
     * @var string
     *
     * @ORM\Column(name="LongName", type="string", length=512, nullable=false)
     */
    private $longname;

    /**
     * @var string
     *
     * @ORM\Column(name="Address", type="string", length=512, nullable=false)
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
     * Set shortname.
     *
     * @param string $shortname
     *
     * @return InsuranceBrokers
     */
    public function setShortname($shortname)
    {
        $this->shortname = $shortname;

        return $this;
    }

    /**
     * Get shortname.
     *
     * @return string
     */
    public function getShortname()
    {
        return $this->shortname;
    }

    /**
     * Set longname.
     *
     * @param string $longname
     *
     * @return InsuranceBrokers
     */
    public function setLongname($longname)
    {
        $this->longname = $longname;

        return $this;
    }

    /**
     * Get longname.
     *
     * @return string
     */
    public function getLongname()
    {
        return $this->longname;
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

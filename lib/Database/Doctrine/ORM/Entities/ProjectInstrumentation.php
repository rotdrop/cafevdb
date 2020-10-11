<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectInstrumentation
 *
 * @ORM\Table(name="ProjectInstrumentation", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectId", columns={"ProjectId", "InstrumentId"})})
 * @ORM\Entity
 */
class ProjectInstrumentation
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
     * @ORM\Column(name="ProjectId", type="integer", nullable=false, options={"comment"="Link into table Projekte"})
     */
    private $projectid;

    /**
     * @var int
     *
     * @ORM\Column(name="InstrumentId", type="integer", nullable=false, options={"comment"="Link into table Instrumente"})
     */
    private $instrumentid;

    /**
     * @var int
     *
     * @ORM\Column(name="Quantity", type="integer", nullable=false, options={"default"="1","comment"="Number of required musicians for this instrument"})
     */
    private $quantity = '1';



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
     * Set projectid.
     *
     * @param int $projectid
     *
     * @return ProjectInstrumentation
     */
    public function setProjectid($projectid)
    {
        $this->projectid = $projectid;

        return $this;
    }

    /**
     * Get projectid.
     *
     * @return int
     */
    public function getProjectid()
    {
        return $this->projectid;
    }

    /**
     * Set instrumentid.
     *
     * @param int $instrumentid
     *
     * @return ProjectInstrumentation
     */
    public function setInstrumentid($instrumentid)
    {
        $this->instrumentid = $instrumentid;

        return $this;
    }

    /**
     * Get instrumentid.
     *
     * @return int
     */
    public function getInstrumentid()
    {
        return $this->instrumentid;
    }

    /**
     * Set quantity.
     *
     * @param int $quantity
     *
     * @return ProjectInstrumentation
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * Get quantity.
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }
}

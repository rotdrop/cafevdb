<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectExtraFieldTypes
 *
 * @ORM\Table(name="ProjectExtraFieldTypes")
 * @ORM\Entity
 */
class ProjectExtraFieldTypes
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
     * @ORM\Column(name="Name", type="string", length=256, nullable=false)
     */
    private $name;

    /**
     * @var enumextrafieldmultiplicity
     *
     * @ORM\Column(name="Multiplicity", type="enumextrafieldmultiplicity", nullable=false)
     */
    private $multiplicity;

    /**
     * @var enumextrafieldkind
     *
     * @ORM\Column(name="Kind", type="enumextrafieldkind", nullable=false, options={"default"="general"})
     */
    private $kind = 'general';



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
     * @return ProjectExtraFieldTypes
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
     * Set multiplicity.
     *
     * @param enumextrafieldmultiplicity $multiplicity
     *
     * @return ProjectExtraFieldTypes
     */
    public function setMultiplicity($multiplicity)
    {
        $this->multiplicity = $multiplicity;

        return $this;
    }

    /**
     * Get multiplicity.
     *
     * @return enumextrafieldmultiplicity
     */
    public function getMultiplicity()
    {
        return $this->multiplicity;
    }

    /**
     * Set kind.
     *
     * @param enumextrafieldkind $kind
     *
     * @return ProjectExtraFieldTypes
     */
    public function setKind($kind)
    {
        $this->kind = $kind;

        return $this;
    }

    /**
     * Get kind.
     *
     * @return enumextrafieldkind
     */
    public function getKind()
    {
        return $this->kind;
    }
}

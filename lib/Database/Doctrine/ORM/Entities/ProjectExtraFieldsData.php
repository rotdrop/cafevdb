<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectExtraFieldsData
 *
 * @ORM\Table(name="ProjectExtraFieldsData", uniqueConstraints={@ORM\UniqueConstraint(name="BesetzungenId", columns={"BesetzungenId", "FieldId"})})
 * @ORM\Entity
 */
class ProjectExtraFieldsData
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
     * @ORM\Column(name="BesetzungenId", type="integer", nullable=false)
     */
    private $besetzungenid;

    /**
     * @var int
     *
     * @ORM\Column(name="FieldId", type="integer", nullable=false)
     */
    private $fieldid;

    /**
     * @var string
     *
     * @ORM\Column(name="FieldValue", type="text", length=16777215, nullable=false)
     */
    private $fieldvalue;



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
     * Set besetzungenid.
     *
     * @param int $besetzungenid
     *
     * @return ProjectExtraFieldsData
     */
    public function setBesetzungenid($besetzungenid)
    {
        $this->besetzungenid = $besetzungenid;

        return $this;
    }

    /**
     * Get besetzungenid.
     *
     * @return int
     */
    public function getBesetzungenid()
    {
        return $this->besetzungenid;
    }

    /**
     * Set fieldid.
     *
     * @param int $fieldid
     *
     * @return ProjectExtraFieldsData
     */
    public function setFieldid($fieldid)
    {
        $this->fieldid = $fieldid;

        return $this;
    }

    /**
     * Get fieldid.
     *
     * @return int
     */
    public function getFieldid()
    {
        return $this->fieldid;
    }

    /**
     * Set fieldvalue.
     *
     * @param string $fieldvalue
     *
     * @return ProjectExtraFieldsData
     */
    public function setFieldvalue($fieldvalue)
    {
        $this->fieldvalue = $fieldvalue;

        return $this;
    }

    /**
     * Get fieldvalue.
     *
     * @return string
     */
    public function getFieldvalue()
    {
        return $this->fieldvalue;
    }
}

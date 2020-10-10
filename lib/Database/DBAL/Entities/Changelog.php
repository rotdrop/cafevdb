<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * Changelog
 *
 * @ORM\Table(name="changelog")
 * @ORM\Entity
 */
class Changelog
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="updated", type="datetime", nullable=true)
     */
    private $updated;

    /**
     * @var string|null
     *
     * @ORM\Column(name="user", type="string", length=255, nullable=true)
     */
    private $user;

    /**
     * @var string|null
     *
     * @ORM\Column(name="host", type="string", length=255, nullable=true)
     */
    private $host;

    /**
     * @var string|null
     *
     * @ORM\Column(name="operation", type="string", length=255, nullable=true)
     */
    private $operation;

    /**
     * @var string|null
     *
     * @ORM\Column(name="tab", type="string", length=255, nullable=true)
     */
    private $tab;

    /**
     * @var string|null
     *
     * @ORM\Column(name="rowkey", type="string", length=255, nullable=true)
     */
    private $rowkey;

    /**
     * @var string|null
     *
     * @ORM\Column(name="col", type="string", length=255, nullable=true)
     */
    private $col;

    /**
     * @var string|null
     *
     * @ORM\Column(name="oldval", type="blob", length=65535, nullable=true)
     */
    private $oldval;

    /**
     * @var string|null
     *
     * @ORM\Column(name="newval", type="blob", length=65535, nullable=true)
     */
    private $newval;



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
     * Set updated.
     *
     * @param \DateTime|null $updated
     *
     * @return Changelog
     */
    public function setUpdated($updated = null)
    {
        $this->updated = $updated;

        return $this;
    }

    /**
     * Get updated.
     *
     * @return \DateTime|null
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Set user.
     *
     * @param string|null $user
     *
     * @return Changelog
     */
    public function setUser($user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return string|null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set host.
     *
     * @param string|null $host
     *
     * @return Changelog
     */
    public function setHost($host = null)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Get host.
     *
     * @return string|null
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set operation.
     *
     * @param string|null $operation
     *
     * @return Changelog
     */
    public function setOperation($operation = null)
    {
        $this->operation = $operation;

        return $this;
    }

    /**
     * Get operation.
     *
     * @return string|null
     */
    public function getOperation()
    {
        return $this->operation;
    }

    /**
     * Set tab.
     *
     * @param string|null $tab
     *
     * @return Changelog
     */
    public function setTab($tab = null)
    {
        $this->tab = $tab;

        return $this;
    }

    /**
     * Get tab.
     *
     * @return string|null
     */
    public function getTab()
    {
        return $this->tab;
    }

    /**
     * Set rowkey.
     *
     * @param string|null $rowkey
     *
     * @return Changelog
     */
    public function setRowkey($rowkey = null)
    {
        $this->rowkey = $rowkey;

        return $this;
    }

    /**
     * Get rowkey.
     *
     * @return string|null
     */
    public function getRowkey()
    {
        return $this->rowkey;
    }

    /**
     * Set col.
     *
     * @param string|null $col
     *
     * @return Changelog
     */
    public function setCol($col = null)
    {
        $this->col = $col;

        return $this;
    }

    /**
     * Get col.
     *
     * @return string|null
     */
    public function getCol()
    {
        return $this->col;
    }

    /**
     * Set oldval.
     *
     * @param string|null $oldval
     *
     * @return Changelog
     */
    public function setOldval($oldval = null)
    {
        $this->oldval = $oldval;

        return $this;
    }

    /**
     * Get oldval.
     *
     * @return string|null
     */
    public function getOldval()
    {
        return $this->oldval;
    }

    /**
     * Set newval.
     *
     * @param string|null $newval
     *
     * @return Changelog
     */
    public function setNewval($newval = null)
    {
        $this->newval = $newval;

        return $this;
    }

    /**
     * Get newval.
     *
     * @return string|null
     */
    public function getNewval()
    {
        return $this->newval;
    }
}

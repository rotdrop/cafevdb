<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ImageData
 *
 * @ORM\Table(name="ImageData", uniqueConstraints={@ORM\UniqueConstraint(name="ItemId", columns={"ItemId", "ItemTable"})})
 * @ORM\Entity
 */
class ImageData
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
     * @ORM\Column(name="ItemId", type="integer", nullable=false)
     */
    private $itemid;

    /**
     * @var string
     *
     * @ORM\Column(name="ItemTable", type="string", length=128, nullable=false)
     */
    private $itemtable;

    /**
     * @var string|null
     *
     * @ORM\Column(name="MimeType", type="string", length=128, nullable=true)
     */
    private $mimetype;

    /**
     * @var string|null
     *
     * @ORM\Column(name="MD5", type="string", length=32, nullable=true, options={"fixed"=true})
     */
    private $md5;

    /**
     * @var string|null
     *
     * @ORM\Column(name="Data", type="text", length=0, nullable=true)
     */
    private $data;



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
     * Set itemid.
     *
     * @param int $itemid
     *
     * @return ImageData
     */
    public function setItemid($itemid)
    {
        $this->itemid = $itemid;

        return $this;
    }

    /**
     * Get itemid.
     *
     * @return int
     */
    public function getItemid()
    {
        return $this->itemid;
    }

    /**
     * Set itemtable.
     *
     * @param string $itemtable
     *
     * @return ImageData
     */
    public function setItemtable($itemtable)
    {
        $this->itemtable = $itemtable;

        return $this;
    }

    /**
     * Get itemtable.
     *
     * @return string
     */
    public function getItemtable()
    {
        return $this->itemtable;
    }

    /**
     * Set mimetype.
     *
     * @param string|null $mimetype
     *
     * @return ImageData
     */
    public function setMimetype($mimetype = null)
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * Get mimetype.
     *
     * @return string|null
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }

    /**
     * Set md5.
     *
     * @param string|null $md5
     *
     * @return ImageData
     */
    public function setMd5($md5 = null)
    {
        $this->md5 = $md5;

        return $this;
    }

    /**
     * Get md5.
     *
     * @return string|null
     */
    public function getMd5()
    {
        return $this->md5;
    }

    /**
     * Set data.
     *
     * @param string|null $data
     *
     * @return ImageData
     */
    public function setData($data = null)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data.
     *
     * @return string|null
     */
    public function getData()
    {
        return $this->data;
    }
}

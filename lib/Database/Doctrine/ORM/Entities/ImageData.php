<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ImageData
 *
 * @ORM\Table(name="ImageData", uniqueConstraints={@ORM\UniqueConstraint(name="itemId_itemTable", columns={"item_id", "item_table"})})
 * @ORM\Entity
 */
class ImageData
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
   * @var int
   *
   * @ORM\Column(name="item_id", type="integer", nullable=false)
   */
  private $itemId;

  /**
   * @var string
   *
   * @ORM\Column(name="item_table", type="string", length=128, nullable=false)
   */
  private $itemTable;

  /**
   * @var string|null
   *
   * @ORM\Column(name="mime_type", type="string", length=128, nullable=true)
   */
  private $mimeType;

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
  public function setItemId($itemid)
  {
    $this->itemid = $itemId;

    return $this;
  }

  /**
   * Get itemId.
   *
   * @return int
   */
  public function getItemId()
  {
    return $this->itemId;
  }

  /**
   * Set itemtable.
   *
   * @param string $itemTable
   *
   * @return ImageData
   */
  public function setItemtable($itemTable)
  {
    $this->itemtable = $itemTable;

    return $this;
  }

  /**
   * Get itemtable.
   *
   * @return string
   */
  public function getItemTable()
  {
    return $this->itemTable;
  }

  /**
   * Set mimetype.
   *
   * @param string|null $mimetype
   *
   * @return ImageData
   */
  public function setMimetype($mimeType = null)
  {
    $this->mimetype = $mimeType;

    return $this;
  }

  /**
   * Get mimetype.
   *
   * @return string|null
   */
  public function getMimetype()
  {
    return $this->mimeType;
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

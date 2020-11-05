<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ImageData
 *
 * @ORM\Table(name="ImageData")
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
   * @var string|null
   *
   * @ORM\Column(name="mime_type", type="string", length=128, nullable=true)
   */
  private $mimeType;

  /**
   * @var string|null
   *
   * @ORM\Column(name="md5", type="string", length=32, nullable=true, options={"fixed"=true})
   */
  private $md5;

  /**
   * @var string|null
   *
   * @ORM\Column(name="data", type="text", length=0, nullable=true)
   */
  private $data;

  /**
   * @ORM\ManyToMany(targetEntity="Musician", mappedBy="photo", fetch="EXTRA_LAZY")
   */
  private $musicians;

  /**
   * @ORM\ManyToMany(targetEntity="Musician", mappedBy="poster", fetch="EXTRA_LAZY")
   */
  private $posterProjects;

  /**
   * @ORM\ManyToMany(targetEntity="Musician", mappedBy="flyer", fetch="EXTRA_LAZY")
   */
  private $flyerProjects;

  public function __construct() {
    $this->arrayCTOR();
    $this->musicians = new ArrayCollection();
    $this->flyerProjects = new ArrayCollection();
    $this->posterProjects = new ArrayCollection();
  }

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

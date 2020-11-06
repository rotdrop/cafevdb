<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * MusicianPhoto
 *
 * @ORM\Table(name="MusicianPhoto")
 * @ORM\Entity
 */
class MusicianPhoto
{
  /**
   * @var int
   *
   * @ORM\Column(name="owner_id", type="integer", nullable=false)
   * @ORM\Id
   */
  private $ownerId;

  /**
   * @var int
   *
   * @ORM\Column(name="image_id", type="integer", nullable=false)
   */
  private $imageId;

  /**
   * Inverse  side.
   *
   * @ORM\ManyToOne(targetEntity="Musician", inversedBy="photos")
   * @ORM\JoinColumn(name="owner_id", referencedColumnName="Id")
   *
   */
  private $musician;
}

<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectPoster
 *
 * @ORM\Table(name="ProjectPoster")
 * @ORM\Entity
 */
class ProjectPoster
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
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="posters")
   * @ORM\JoinColumn(name="owner_id", referencedColumnName="Id")
   *
   */
  private $project;
}

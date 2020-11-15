<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectFlyer
 *
 * @ORM\Table(name="ProjectFlyer")
 * @ORM\Entity
 */
class ProjectFlyer
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
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="flyers")
   * @ORM\JoinColumn(name="owner_id", referencedColumnName="Id")
   *
   */
  private $project;
}

<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/**
 * Numbers
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Numbers
{
  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false)
   * @ORM\Id
   */
  private $n;

  /**
   * Get n.
   *
   * @return int
   */
  public function getN()
  {
    return $this->n;
  }
}

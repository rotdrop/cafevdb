<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

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
     * @ORM\GeneratedValue(strategy="IDENTITY")
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

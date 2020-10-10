<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * Numbers
 *
 * @ORM\Table(name="numbers")
 * @ORM\Entity
 */
class Numbers
{
    /**
     * @var int
     *
     * @ORM\Column(name="N", type="integer", nullable=false)
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

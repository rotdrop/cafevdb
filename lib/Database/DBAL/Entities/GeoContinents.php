<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * GeoContinents
 *
 * @ORM\Table(name="GeoContinents")
 * @ORM\Entity
 */
class GeoContinents
{
    /**
     * @var string
     *
     * @ORM\Column(name="Code", type="string", length=4, nullable=false)
     * @ORM\Id
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="en", type="string", length=1024, nullable=false)
     */
    private $en;

    /**
     * @var string|null
     *
     * @ORM\Column(name="de", type="string", length=1024, nullable=true)
     */
    private $de;

    /**
     * @var string|null
     *
     * @ORM\Column(name="fr", type="string", length=180, nullable=true)
     */
    private $fr;



    /**
     * Get code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set en.
     *
     * @param string $en
     *
     * @return GeoContinents
     */
    public function setEn($en)
    {
        $this->en = $en;

        return $this;
    }

    /**
     * Get en.
     *
     * @return string
     */
    public function getEn()
    {
        return $this->en;
    }

    /**
     * Set de.
     *
     * @param string|null $de
     *
     * @return GeoContinents
     */
    public function setDe($de = null)
    {
        $this->de = $de;

        return $this;
    }

    /**
     * Get de.
     *
     * @return string|null
     */
    public function getDe()
    {
        return $this->de;
    }

    /**
     * Set fr.
     *
     * @param string|null $fr
     *
     * @return GeoContinents
     */
    public function setFr($fr = null)
    {
        $this->fr = $fr;

        return $this;
    }

    /**
     * Get fr.
     *
     * @return string|null
     */
    public function getFr()
    {
        return $this->fr;
    }
}

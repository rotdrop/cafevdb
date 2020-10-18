<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * GeoContinents
 *
 * @ORM\Table(name="GeoContinents")
 * @ORM\Entity
 */
class GeoContinents implements \ArrayAccess
{
    use ArrayTrait;
    use FactoryTrait;

    /**
     * @var string
     *
     * @ORM\Column(name="Code", type="string", length=2, nullable=false)
     * @ORM\Id
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="Target", type="string", length=2, nullable=false)
     * @ORM\Id
     */
    private $target;

    /**
     * @var string
     *
     * @ORM\Column(name="Translation", type="string", length=1024, nullable=false)
     */
    private $translation;

    public function __construct() {
        $this->arrayCTOR();
    }

    /**
     * Get code.
     *
     * @return string
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

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
     * Set target.
     *
     * @param string $target
     *
     * @return GeoContinents
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Get target.
     *
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Set translation.
     *
     * @param string $translatoin
     *
     * @return GeoContinents
     */
    public function setTranslation($translation)
    {
        $this->translation = $translation;

        return $this;
    }

    /**
     * Get translation.
     *
     * @return string
     */
    public function getTranslation()
    {
        return $this->translation;
    }
}

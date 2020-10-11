<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * GeoCountries
 *
 * @ORM\Table(name="GeoCountries")
 * @ORM\Entity
 */
class GeoCountries
{
    /**
     * @var string
     *
     * @ORM\Column(name="ISO", type="string", length=2, nullable=false)
     * @ORM\Id
     */
    private $iso;

    /**
     * @var string
     *
     * @ORM\Column(name="Continent", type="string", length=4, nullable=false)
     */
    private $continent;

    /**
     * @var string
     *
     * @ORM\Column(name="NativeName", type="string", length=180, nullable=false)
     */
    private $nativename;

    /**
     * @var string
     *
     * @ORM\Column(name="en", type="string", length=180, nullable=false)
     */
    private $en;

    /**
     * @var string|null
     *
     * @ORM\Column(name="de", type="string", length=180, nullable=true)
     */
    private $de;

    /**
     * @var string|null
     *
     * @ORM\Column(name="fr", type="string", length=180, nullable=true)
     */
    private $fr;



    /**
     * Get iso.
     *
     * @return string
     */
    public function getIso()
    {
        return $this->iso;
    }

    /**
     * Set continent.
     *
     * @param string $continent
     *
     * @return GeoCountries
     */
    public function setContinent($continent)
    {
        $this->continent = $continent;

        return $this;
    }

    /**
     * Get continent.
     *
     * @return string
     */
    public function getContinent()
    {
        return $this->continent;
    }

    /**
     * Set nativename.
     *
     * @param string $nativename
     *
     * @return GeoCountries
     */
    public function setNativename($nativename)
    {
        $this->nativename = $nativename;

        return $this;
    }

    /**
     * Get nativename.
     *
     * @return string
     */
    public function getNativename()
    {
        return $this->nativename;
    }

    /**
     * Set en.
     *
     * @param string $en
     *
     * @return GeoCountries
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
     * @return GeoCountries
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
     * @return GeoCountries
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

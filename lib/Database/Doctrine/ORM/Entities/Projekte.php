<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * Projekte
 *
 * @ORM\Table(name="Projekte", uniqueConstraints={@ORM\UniqueConstraint(name="Name", columns={"Name"})})
 * @ORM\Entity
 */
class Projekte
{
    /**
     * @var int
     *
     * @ORM\Column(name="Id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="Jahr", type="integer", nullable=false, options={"unsigned"=true})
     */
    private $jahr;

    /**
     * @var string
     *
     * @ORM\Column(name="Name", type="string", length=64, nullable=false)
     */
    private $name;

    /**
     * @var enumprojecttemporaltype
     *
     * @ORM\Column(name="Art", type="enumprojecttemporaltype", nullable=false, options={"default"="temporary"})
     */
    private $art = 'temporary';

    /**
     * @var array|null
     *
     * @ORM\Column(name="Besetzung", type="simple_array", length=0, nullable=true, options={"comment"="Benötigte Instrumente"})
     */
    private $besetzung;

    /**
     * @var string
     *
     * @ORM\Column(name="Unkostenbeitrag", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
     */
    private $unkostenbeitrag = '0.00';

    /**
     * @var string
     *
     * @ORM\Column(name="Anzahlung", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
     */
    private $anzahlung = '0.00';

    /**
     * @var string
     *
     * @ORM\Column(name="ExtraFelder", type="text", length=65535, nullable=false, options={"comment"="Extra-Datenfelder"})
     */
    private $extrafelder;

    /**
     * @var bool
     *
     * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
     */
    private $disabled = '0';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="Aktualisiert", type="datetime", nullable=true)
     */
    private $aktualisiert;



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
     * Set jahr.
     *
     * @param int $jahr
     *
     * @return Projekte
     */
    public function setJahr($jahr)
    {
        $this->jahr = $jahr;

        return $this;
    }

    /**
     * Get jahr.
     *
     * @return int
     */
    public function getJahr()
    {
        return $this->jahr;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Projekte
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set art.
     *
     * @param enumprojecttemporaltype $art
     *
     * @return Projekte
     */
    public function setArt($art)
    {
        $this->art = $art;

        return $this;
    }

    /**
     * Get art.
     *
     * @return enumprojecttemporaltype
     */
    public function getArt()
    {
        return $this->art;
    }

    /**
     * Set besetzung.
     *
     * @param array|null $besetzung
     *
     * @return Projekte
     */
    public function setBesetzung($besetzung = null)
    {
        $this->besetzung = $besetzung;

        return $this;
    }

    /**
     * Get besetzung.
     *
     * @return array|null
     */
    public function getBesetzung()
    {
        return $this->besetzung;
    }

    /**
     * Set unkostenbeitrag.
     *
     * @param string $unkostenbeitrag
     *
     * @return Projekte
     */
    public function setUnkostenbeitrag($unkostenbeitrag)
    {
        $this->unkostenbeitrag = $unkostenbeitrag;

        return $this;
    }

    /**
     * Get unkostenbeitrag.
     *
     * @return string
     */
    public function getUnkostenbeitrag()
    {
        return $this->unkostenbeitrag;
    }

    /**
     * Set anzahlung.
     *
     * @param string $anzahlung
     *
     * @return Projekte
     */
    public function setAnzahlung($anzahlung)
    {
        $this->anzahlung = $anzahlung;

        return $this;
    }

    /**
     * Get anzahlung.
     *
     * @return string
     */
    public function getAnzahlung()
    {
        return $this->anzahlung;
    }

    /**
     * Set extrafelder.
     *
     * @param string $extrafelder
     *
     * @return Projekte
     */
    public function setExtrafelder($extrafelder)
    {
        $this->extrafelder = $extrafelder;

        return $this;
    }

    /**
     * Get extrafelder.
     *
     * @return string
     */
    public function getExtrafelder()
    {
        return $this->extrafelder;
    }

    /**
     * Set disabled.
     *
     * @param bool $disabled
     *
     * @return Projekte
     */
    public function setDisabled($disabled)
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Get disabled.
     *
     * @return bool
     */
    public function getDisabled()
    {
        return $this->disabled;
    }

    /**
     * Set aktualisiert.
     *
     * @param \DateTime|null $aktualisiert
     *
     * @return Projekte
     */
    public function setAktualisiert($aktualisiert = null)
    {
        $this->aktualisiert = $aktualisiert;

        return $this;
    }

    /**
     * Get aktualisiert.
     *
     * @return \DateTime|null
     */
    public function getAktualisiert()
    {
        return $this->aktualisiert;
    }
}

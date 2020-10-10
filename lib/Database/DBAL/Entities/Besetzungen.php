<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * Besetzungen
 *
 * @ORM\Table(name="Besetzungen", uniqueConstraints={@ORM\UniqueConstraint(name="ProjektId", columns={"ProjektId", "MusikerId"})})
 * @ORM\Entity
 */
class Besetzungen
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
     * @ORM\Column(name="ProjektId", type="integer", nullable=false)
     */
    private $projektid;

    /**
     * @var int
     *
     * @ORM\Column(name="MusikerId", type="integer", nullable=false)
     */
    private $musikerid;

    /**
     * @var bool
     *
     * @ORM\Column(name="Anmeldung", type="boolean", nullable=false, options={"default"="0"})
     */
    private $anmeldung = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="Unkostenbeitrag", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00","comment"="Gagen negativ"})
     */
    private $unkostenbeitrag = '0.00';

    /**
     * @var string
     *
     * @ORM\Column(name="Anzahlung", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
     */
    private $anzahlung = '0.00';

    /**
     * @var bool
     *
     * @ORM\Column(name="Lastschrift", type="boolean", nullable=false, options={"default"="1"})
     */
    private $lastschrift = '1';

    /**
     * @var string
     *
     * @ORM\Column(name="Bemerkungen", type="text", length=65535, nullable=false, options={"comment"="Allgemeine Bermerkungen"})
     */
    private $bemerkungen;

    /**
     * @var bool
     *
     * @ORM\Column(name="Disabled", type="boolean", nullable=false, options={"default"="0"})
     */
    private $disabled = '0';



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
     * Set projektid.
     *
     * @param int $projektid
     *
     * @return Besetzungen
     */
    public function setProjektid($projektid)
    {
        $this->projektid = $projektid;

        return $this;
    }

    /**
     * Get projektid.
     *
     * @return int
     */
    public function getProjektid()
    {
        return $this->projektid;
    }

    /**
     * Set musikerid.
     *
     * @param int $musikerid
     *
     * @return Besetzungen
     */
    public function setMusikerid($musikerid)
    {
        $this->musikerid = $musikerid;

        return $this;
    }

    /**
     * Get musikerid.
     *
     * @return int
     */
    public function getMusikerid()
    {
        return $this->musikerid;
    }

    /**
     * Set anmeldung.
     *
     * @param bool $anmeldung
     *
     * @return Besetzungen
     */
    public function setAnmeldung($anmeldung)
    {
        $this->anmeldung = $anmeldung;

        return $this;
    }

    /**
     * Get anmeldung.
     *
     * @return bool
     */
    public function getAnmeldung()
    {
        return $this->anmeldung;
    }

    /**
     * Set unkostenbeitrag.
     *
     * @param string $unkostenbeitrag
     *
     * @return Besetzungen
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
     * @return Besetzungen
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
     * Set lastschrift.
     *
     * @param bool $lastschrift
     *
     * @return Besetzungen
     */
    public function setLastschrift($lastschrift)
    {
        $this->lastschrift = $lastschrift;

        return $this;
    }

    /**
     * Get lastschrift.
     *
     * @return bool
     */
    public function getLastschrift()
    {
        return $this->lastschrift;
    }

    /**
     * Set bemerkungen.
     *
     * @param string $bemerkungen
     *
     * @return Besetzungen
     */
    public function setBemerkungen($bemerkungen)
    {
        $this->bemerkungen = $bemerkungen;

        return $this;
    }

    /**
     * Get bemerkungen.
     *
     * @return string
     */
    public function getBemerkungen()
    {
        return $this->bemerkungen;
    }

    /**
     * Set disabled.
     *
     * @param bool $disabled
     *
     * @return Besetzungen
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
}

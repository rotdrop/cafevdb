<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * Besetzungen *
 * @ORM\Table(name="ProjectParticipants", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectId", columns={"ProjectId", "MusicianId"})})
 * @ORM\Entity
 */
class ProjectParticipant
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
     * @ORM\Column(name="ProjectId", type="integer", nullable=false)
     */
    private $projectId;

    /**
     * @var int
     *
     * @ORM\Column(name="MusicianId", type="integer", nullable=false)
     */
    private $musicianId;

    /**
     * @var bool
     *
     * @ORM\Column(name="Registration", type="boolean", nullable=false, options={"default"="0"})
     */
    private $registration = '0';

    /**
     * @var string
     *
     * @ORM\Column(name="ServiceCharge", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00","comment"="Gagen negativ"})
     */
    private $serviceCharge = '0.00';

    /**
     * @var string
     *
     * @ORM\Column(name="PrePayment", type="decimal", precision=7, scale=2, nullable=false, options={"default"="0.00"})
     */
    private $prePayment = '0.00';

    /**
     * @var bool
     *
     * @ORM\Column(name="Debitnote", type="boolean", nullable=false, options={"default"="1"})
     */
    private $debitnote = '1';

    /**
     * @var string
     *
     * @ORM\Column(name="Remarks", type="text", length=65535, nullable=false, options={"comment"="Allgemeine Bermerkungen"})
     */
    private $remarks;

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
     * Set projectId.
     *
     * @param int $projectId
     *
     * @return Besetzungen
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;

        return $this;
    }

    /**
     * Get projectId.
     *
     * @return int
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * Set musicianId.
     *
     * @param int $musicianId
     *
     * @return Besetzungen
     */
    public function setMusicianId($musicianId)
    {
        $this->musicianId = $musicianId;

        return $this;
    }

    /**
     * Get musicianId.
     *
     * @return int
     */
    public function getMusicianId()
    {
        return $this->musicianId;
    }

    /**
     * Set registration.
     *
     * @param bool $registration
     *
     * @return Besetzungen
     */
    public function setRegistration($registration)
    {
        $this->registration = $registration;

        return $this;
    }

    /**
     * Get registration.
     *
     * @return bool
     */
    public function getRegistration()
    {
        return $this->registration;
    }

    /**
     * Set serviceCharge.
     *
     * @param string $serviceCharge
     *
     * @return Besetzungen
     */
    public function setServiceCharge($serviceCharge)
    {
        $this->serviceCharge = $serviceCharge;

        return $this;
    }

    /**
     * Get serviceCharge.
     *
     * @return string
     */
    public function getServiceCharge()
    {
        return $this->serviceCharge;
    }

    /**
     * Set prePayment.
     *
     * @param string $prePayment
     *
     * @return Besetzungen
     */
    public function setPrePayment($prePayment)
    {
        $this->prePayment = $prePayment;

        return $this;
    }

    /**
     * Get prePayment.
     *
     * @return string
     */
    public function getPrePayment()
    {
        return $this->prePayment;
    }

    /**
     * Set debitnote.
     *
     * @param bool $debitnote
     *
     * @return Besetzungen
     */
    public function setDebitnote($debitnote)
    {
        $this->debitnote = $debitnote;

        return $this;
    }

    /**
     * Get debitnote.
     *
     * @return bool
     */
    public function getDebitnote()
    {
        return $this->debitnote;
    }

    /**
     * Set remarks.
     *
     * @param string $remarks
     *
     * @return Besetzungen
     */
    public function setRemarks($remarks)
    {
        $this->remarks = $remarks;

        return $this;
    }

    /**
     * Get remarks.
     *
     * @return string
     */
    public function getRemarks()
    {
        return $this->remarks;
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

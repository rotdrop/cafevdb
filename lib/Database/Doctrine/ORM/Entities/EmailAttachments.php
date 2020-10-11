<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * EmailAttachments
 *
 * @ORM\Table(name="EmailAttachments", uniqueConstraints={@ORM\UniqueConstraint(name="FileName", columns={"FileName"})})
 * @ORM\Entity
 */
class EmailAttachments
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
     * @ORM\Column(name="MessageId", type="integer", nullable=false, options={"default"="-1"})
     */
    private $messageid = '-1';

    /**
     * @var string
     *
     * @ORM\Column(name="User", type="string", length=512, nullable=false)
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="FileName", type="string", length=512, nullable=false)
     */
    private $filename;



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
     * Set messageid.
     *
     * @param int $messageid
     *
     * @return EmailAttachments
     */
    public function setMessageid($messageid)
    {
        $this->messageid = $messageid;

        return $this;
    }

    /**
     * Get messageid.
     *
     * @return int
     */
    public function getMessageid()
    {
        return $this->messageid;
    }

    /**
     * Set user.
     *
     * @param string $user
     *
     * @return EmailAttachments
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set filename.
     *
     * @param string $filename
     *
     * @return EmailAttachments
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }
}

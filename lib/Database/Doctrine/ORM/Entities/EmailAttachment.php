<?php

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * EmailAttachments
 *
 * @ORM\Table(name="EmailAttachments",uniqueConstraints={@ORM\UniqueConstraint(columns={"file_name"})})
 * @ORM\Entity
 */
class EmailAttachment
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=false, options={"default"="-1"})
     */
    private $messageId = '-1';

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=512, nullable=false)
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=512, nullable=false)
     */
    private $fileName;



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
     * Set messageId.
     *
     * @param int $messageId
     *
     * @return EmailAttachments
     */
    public function setMessageId($messageId)
    {
        $this->messageId = $messageId;

        return $this;
    }

    /**
     * Get messageId.
     *
     * @return int
     */
    public function getMessageId()
    {
        return $this->messageId;
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
     * Set fileName.
     *
     * @param string $fileName
     *
     * @return EmailAttachments
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;

        return $this;
    }

    /**
     * Get fileName.
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }
}

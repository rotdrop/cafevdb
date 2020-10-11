<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * SentEmail
 *
 * @ORM\Table(name="SentEmail")
 * @ORM\Entity
 */
class SentEmail
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
     * @var \DateTime
     *
     * @ORM\Column(name="Date", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $date = 'CURRENT_TIMESTAMP';

    /**
     * @var string
     *
     * @ORM\Column(name="user", type="text", length=0, nullable=false)
     */
    private $user;

    /**
     * @var string
     *
     * @ORM\Column(name="host", type="string", length=64, nullable=false)
     */
    private $host;

    /**
     * @var string
     *
     * @ORM\Column(name="BulkRecipients", type="text", length=0, nullable=false)
     */
    private $bulkrecipients;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5BulkRecipients", type="text", length=16777215, nullable=false)
     */
    private $md5bulkrecipients;

    /**
     * @var string
     *
     * @ORM\Column(name="Cc", type="text", length=0, nullable=false)
     */
    private $cc;

    /**
     * @var string
     *
     * @ORM\Column(name="Bcc", type="text", length=0, nullable=false)
     */
    private $bcc;

    /**
     * @var string
     *
     * @ORM\Column(name="Subject", type="text", length=16777215, nullable=false)
     */
    private $subject;

    /**
     * @var string
     *
     * @ORM\Column(name="HtmlBody", type="text", length=0, nullable=false)
     */
    private $htmlbody;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Text", type="text", length=16777215, nullable=false)
     */
    private $md5text;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment1", type="text", length=16777215, nullable=false)
     */
    private $attachment1;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment1", type="text", length=16777215, nullable=false)
     */
    private $md5attachment1;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment2", type="text", length=16777215, nullable=false)
     */
    private $attachment2;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment2", type="text", length=16777215, nullable=false)
     */
    private $md5attachment2;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment3", type="text", length=16777215, nullable=false)
     */
    private $attachment3;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment3", type="text", length=16777215, nullable=false)
     */
    private $md5attachment3;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment4", type="text", length=16777215, nullable=false)
     */
    private $attachment4;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment4", type="text", length=16777215, nullable=false)
     */
    private $md5attachment4;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment00", type="text", length=16777215, nullable=false)
     */
    private $attachment00;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment00", type="text", length=16777215, nullable=false)
     */
    private $md5attachment00;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment01", type="text", length=16777215, nullable=false)
     */
    private $attachment01;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment01", type="text", length=16777215, nullable=false)
     */
    private $md5attachment01;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment02", type="text", length=16777215, nullable=false)
     */
    private $attachment02;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment02", type="text", length=16777215, nullable=false)
     */
    private $md5attachment02;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment03", type="text", length=16777215, nullable=false)
     */
    private $attachment03;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment03", type="text", length=16777215, nullable=false)
     */
    private $md5attachment03;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment04", type="text", length=16777215, nullable=false)
     */
    private $attachment04;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment04", type="text", length=16777215, nullable=false)
     */
    private $md5attachment04;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment05", type="text", length=16777215, nullable=false)
     */
    private $attachment05;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment05", type="text", length=16777215, nullable=false)
     */
    private $md5attachment05;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment06", type="text", length=16777215, nullable=false)
     */
    private $attachment06;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment06", type="text", length=16777215, nullable=false)
     */
    private $md5attachment06;

    /**
     * @var string
     *
     * @ORM\Column(name="Attachment07", type="text", length=16777215, nullable=false)
     */
    private $attachment07;

    /**
     * @var string
     *
     * @ORM\Column(name="MD5Attachment07", type="text", length=16777215, nullable=false)
     */
    private $md5attachment07;



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
     * Set date.
     *
     * @param \DateTime $date
     *
     * @return SentEmail
     */
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get date.
     *
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set user.
     *
     * @param string $user
     *
     * @return SentEmail
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
     * Set host.
     *
     * @param string $host
     *
     * @return SentEmail
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Get host.
     *
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set bulkrecipients.
     *
     * @param string $bulkrecipients
     *
     * @return SentEmail
     */
    public function setBulkrecipients($bulkrecipients)
    {
        $this->bulkrecipients = $bulkrecipients;

        return $this;
    }

    /**
     * Get bulkrecipients.
     *
     * @return string
     */
    public function getBulkrecipients()
    {
        return $this->bulkrecipients;
    }

    /**
     * Set md5bulkrecipients.
     *
     * @param string $md5bulkrecipients
     *
     * @return SentEmail
     */
    public function setMd5bulkrecipients($md5bulkrecipients)
    {
        $this->md5bulkrecipients = $md5bulkrecipients;

        return $this;
    }

    /**
     * Get md5bulkrecipients.
     *
     * @return string
     */
    public function getMd5bulkrecipients()
    {
        return $this->md5bulkrecipients;
    }

    /**
     * Set cc.
     *
     * @param string $cc
     *
     * @return SentEmail
     */
    public function setCc($cc)
    {
        $this->cc = $cc;

        return $this;
    }

    /**
     * Get cc.
     *
     * @return string
     */
    public function getCc()
    {
        return $this->cc;
    }

    /**
     * Set bcc.
     *
     * @param string $bcc
     *
     * @return SentEmail
     */
    public function setBcc($bcc)
    {
        $this->bcc = $bcc;

        return $this;
    }

    /**
     * Get bcc.
     *
     * @return string
     */
    public function getBcc()
    {
        return $this->bcc;
    }

    /**
     * Set subject.
     *
     * @param string $subject
     *
     * @return SentEmail
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get subject.
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set htmlbody.
     *
     * @param string $htmlbody
     *
     * @return SentEmail
     */
    public function setHtmlbody($htmlbody)
    {
        $this->htmlbody = $htmlbody;

        return $this;
    }

    /**
     * Get htmlbody.
     *
     * @return string
     */
    public function getHtmlbody()
    {
        return $this->htmlbody;
    }

    /**
     * Set md5text.
     *
     * @param string $md5text
     *
     * @return SentEmail
     */
    public function setMd5text($md5text)
    {
        $this->md5text = $md5text;

        return $this;
    }

    /**
     * Get md5text.
     *
     * @return string
     */
    public function getMd5text()
    {
        return $this->md5text;
    }

    /**
     * Set attachment1.
     *
     * @param string $attachment1
     *
     * @return SentEmail
     */
    public function setAttachment1($attachment1)
    {
        $this->attachment1 = $attachment1;

        return $this;
    }

    /**
     * Get attachment1.
     *
     * @return string
     */
    public function getAttachment1()
    {
        return $this->attachment1;
    }

    /**
     * Set md5attachment1.
     *
     * @param string $md5attachment1
     *
     * @return SentEmail
     */
    public function setMd5attachment1($md5attachment1)
    {
        $this->md5attachment1 = $md5attachment1;

        return $this;
    }

    /**
     * Get md5attachment1.
     *
     * @return string
     */
    public function getMd5attachment1()
    {
        return $this->md5attachment1;
    }

    /**
     * Set attachment2.
     *
     * @param string $attachment2
     *
     * @return SentEmail
     */
    public function setAttachment2($attachment2)
    {
        $this->attachment2 = $attachment2;

        return $this;
    }

    /**
     * Get attachment2.
     *
     * @return string
     */
    public function getAttachment2()
    {
        return $this->attachment2;
    }

    /**
     * Set md5attachment2.
     *
     * @param string $md5attachment2
     *
     * @return SentEmail
     */
    public function setMd5attachment2($md5attachment2)
    {
        $this->md5attachment2 = $md5attachment2;

        return $this;
    }

    /**
     * Get md5attachment2.
     *
     * @return string
     */
    public function getMd5attachment2()
    {
        return $this->md5attachment2;
    }

    /**
     * Set attachment3.
     *
     * @param string $attachment3
     *
     * @return SentEmail
     */
    public function setAttachment3($attachment3)
    {
        $this->attachment3 = $attachment3;

        return $this;
    }

    /**
     * Get attachment3.
     *
     * @return string
     */
    public function getAttachment3()
    {
        return $this->attachment3;
    }

    /**
     * Set md5attachment3.
     *
     * @param string $md5attachment3
     *
     * @return SentEmail
     */
    public function setMd5attachment3($md5attachment3)
    {
        $this->md5attachment3 = $md5attachment3;

        return $this;
    }

    /**
     * Get md5attachment3.
     *
     * @return string
     */
    public function getMd5attachment3()
    {
        return $this->md5attachment3;
    }

    /**
     * Set attachment4.
     *
     * @param string $attachment4
     *
     * @return SentEmail
     */
    public function setAttachment4($attachment4)
    {
        $this->attachment4 = $attachment4;

        return $this;
    }

    /**
     * Get attachment4.
     *
     * @return string
     */
    public function getAttachment4()
    {
        return $this->attachment4;
    }

    /**
     * Set md5attachment4.
     *
     * @param string $md5attachment4
     *
     * @return SentEmail
     */
    public function setMd5attachment4($md5attachment4)
    {
        $this->md5attachment4 = $md5attachment4;

        return $this;
    }

    /**
     * Get md5attachment4.
     *
     * @return string
     */
    public function getMd5attachment4()
    {
        return $this->md5attachment4;
    }

    /**
     * Set attachment00.
     *
     * @param string $attachment00
     *
     * @return SentEmail
     */
    public function setAttachment00($attachment00)
    {
        $this->attachment00 = $attachment00;

        return $this;
    }

    /**
     * Get attachment00.
     *
     * @return string
     */
    public function getAttachment00()
    {
        return $this->attachment00;
    }

    /**
     * Set md5attachment00.
     *
     * @param string $md5attachment00
     *
     * @return SentEmail
     */
    public function setMd5attachment00($md5attachment00)
    {
        $this->md5attachment00 = $md5attachment00;

        return $this;
    }

    /**
     * Get md5attachment00.
     *
     * @return string
     */
    public function getMd5attachment00()
    {
        return $this->md5attachment00;
    }

    /**
     * Set attachment01.
     *
     * @param string $attachment01
     *
     * @return SentEmail
     */
    public function setAttachment01($attachment01)
    {
        $this->attachment01 = $attachment01;

        return $this;
    }

    /**
     * Get attachment01.
     *
     * @return string
     */
    public function getAttachment01()
    {
        return $this->attachment01;
    }

    /**
     * Set md5attachment01.
     *
     * @param string $md5attachment01
     *
     * @return SentEmail
     */
    public function setMd5attachment01($md5attachment01)
    {
        $this->md5attachment01 = $md5attachment01;

        return $this;
    }

    /**
     * Get md5attachment01.
     *
     * @return string
     */
    public function getMd5attachment01()
    {
        return $this->md5attachment01;
    }

    /**
     * Set attachment02.
     *
     * @param string $attachment02
     *
     * @return SentEmail
     */
    public function setAttachment02($attachment02)
    {
        $this->attachment02 = $attachment02;

        return $this;
    }

    /**
     * Get attachment02.
     *
     * @return string
     */
    public function getAttachment02()
    {
        return $this->attachment02;
    }

    /**
     * Set md5attachment02.
     *
     * @param string $md5attachment02
     *
     * @return SentEmail
     */
    public function setMd5attachment02($md5attachment02)
    {
        $this->md5attachment02 = $md5attachment02;

        return $this;
    }

    /**
     * Get md5attachment02.
     *
     * @return string
     */
    public function getMd5attachment02()
    {
        return $this->md5attachment02;
    }

    /**
     * Set attachment03.
     *
     * @param string $attachment03
     *
     * @return SentEmail
     */
    public function setAttachment03($attachment03)
    {
        $this->attachment03 = $attachment03;

        return $this;
    }

    /**
     * Get attachment03.
     *
     * @return string
     */
    public function getAttachment03()
    {
        return $this->attachment03;
    }

    /**
     * Set md5attachment03.
     *
     * @param string $md5attachment03
     *
     * @return SentEmail
     */
    public function setMd5attachment03($md5attachment03)
    {
        $this->md5attachment03 = $md5attachment03;

        return $this;
    }

    /**
     * Get md5attachment03.
     *
     * @return string
     */
    public function getMd5attachment03()
    {
        return $this->md5attachment03;
    }

    /**
     * Set attachment04.
     *
     * @param string $attachment04
     *
     * @return SentEmail
     */
    public function setAttachment04($attachment04)
    {
        $this->attachment04 = $attachment04;

        return $this;
    }

    /**
     * Get attachment04.
     *
     * @return string
     */
    public function getAttachment04()
    {
        return $this->attachment04;
    }

    /**
     * Set md5attachment04.
     *
     * @param string $md5attachment04
     *
     * @return SentEmail
     */
    public function setMd5attachment04($md5attachment04)
    {
        $this->md5attachment04 = $md5attachment04;

        return $this;
    }

    /**
     * Get md5attachment04.
     *
     * @return string
     */
    public function getMd5attachment04()
    {
        return $this->md5attachment04;
    }

    /**
     * Set attachment05.
     *
     * @param string $attachment05
     *
     * @return SentEmail
     */
    public function setAttachment05($attachment05)
    {
        $this->attachment05 = $attachment05;

        return $this;
    }

    /**
     * Get attachment05.
     *
     * @return string
     */
    public function getAttachment05()
    {
        return $this->attachment05;
    }

    /**
     * Set md5attachment05.
     *
     * @param string $md5attachment05
     *
     * @return SentEmail
     */
    public function setMd5attachment05($md5attachment05)
    {
        $this->md5attachment05 = $md5attachment05;

        return $this;
    }

    /**
     * Get md5attachment05.
     *
     * @return string
     */
    public function getMd5attachment05()
    {
        return $this->md5attachment05;
    }

    /**
     * Set attachment06.
     *
     * @param string $attachment06
     *
     * @return SentEmail
     */
    public function setAttachment06($attachment06)
    {
        $this->attachment06 = $attachment06;

        return $this;
    }

    /**
     * Get attachment06.
     *
     * @return string
     */
    public function getAttachment06()
    {
        return $this->attachment06;
    }

    /**
     * Set md5attachment06.
     *
     * @param string $md5attachment06
     *
     * @return SentEmail
     */
    public function setMd5attachment06($md5attachment06)
    {
        $this->md5attachment06 = $md5attachment06;

        return $this;
    }

    /**
     * Get md5attachment06.
     *
     * @return string
     */
    public function getMd5attachment06()
    {
        return $this->md5attachment06;
    }

    /**
     * Set attachment07.
     *
     * @param string $attachment07
     *
     * @return SentEmail
     */
    public function setAttachment07($attachment07)
    {
        $this->attachment07 = $attachment07;

        return $this;
    }

    /**
     * Get attachment07.
     *
     * @return string
     */
    public function getAttachment07()
    {
        return $this->attachment07;
    }

    /**
     * Set md5attachment07.
     *
     * @param string $md5attachment07
     *
     * @return SentEmail
     */
    public function setMd5attachment07($md5attachment07)
    {
        $this->md5attachment07 = $md5attachment07;

        return $this;
    }

    /**
     * Get md5attachment07.
     *
     * @return string
     */
    public function getMd5attachment07()
    {
        return $this->md5attachment07;
    }
}

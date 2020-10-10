<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * EmailTemplates
 *
 * @ORM\Table(name="EmailTemplates", uniqueConstraints={@ORM\UniqueConstraint(name="Tag", columns={"Tag"}), @ORM\UniqueConstraint(name="Id", columns={"Id", "Tag"})})
 * @ORM\Entity
 */
class EmailTemplates
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
     * @var string
     *
     * @ORM\Column(name="Tag", type="string", length=128, nullable=false)
     */
    private $tag;

    /**
     * @var string
     *
     * @ORM\Column(name="Subject", type="string", length=1024, nullable=false)
     */
    private $subject;

    /**
     * @var string|null
     *
     * @ORM\Column(name="Contents", type="text", length=0, nullable=true)
     */
    private $contents;



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
     * Set tag.
     *
     * @param string $tag
     *
     * @return EmailTemplates
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Get tag.
     *
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Set subject.
     *
     * @param string $subject
     *
     * @return EmailTemplates
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
     * Set contents.
     *
     * @param string|null $contents
     *
     * @return EmailTemplates
     */
    public function setContents($contents = null)
    {
        $this->contents = $contents;

        return $this;
    }

    /**
     * Get contents.
     *
     * @return string|null
     */
    public function getContents()
    {
        return $this->contents;
    }
}

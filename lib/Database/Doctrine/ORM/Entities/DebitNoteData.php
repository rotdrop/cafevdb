<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * DebitNoteData
 *
 * @ORM\Table(name="DebitNoteData")
 * @ORM\Entity
 */
class DebitNoteData
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
     * @ORM\Column(name="DebitNoteId", type="integer", nullable=false)
     */
    private $debitnoteid;

    /**
     * @var string
     *
     * @ORM\Column(name="FileName", type="string", length=1024, nullable=false)
     */
    private $filename;

    /**
     * @var string
     *
     * @ORM\Column(name="MimeType", type="string", length=1024, nullable=false)
     */
    private $mimetype;

    /**
     * @var string
     *
     * @ORM\Column(name="Data", type="text", length=16777215, nullable=false)
     */
    private $data;



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
     * Set debitnoteid.
     *
     * @param int $debitnoteid
     *
     * @return DebitNoteData
     */
    public function setDebitnoteid($debitnoteid)
    {
        $this->debitnoteid = $debitnoteid;

        return $this;
    }

    /**
     * Get debitnoteid.
     *
     * @return int
     */
    public function getDebitnoteid()
    {
        return $this->debitnoteid;
    }

    /**
     * Set filename.
     *
     * @param string $filename
     *
     * @return DebitNoteData
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

    /**
     * Set mimetype.
     *
     * @param string $mimetype
     *
     * @return DebitNoteData
     */
    public function setMimetype($mimetype)
    {
        $this->mimetype = $mimetype;

        return $this;
    }

    /**
     * Get mimetype.
     *
     * @return string
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }

    /**
     * Set data.
     *
     * @param string $data
     *
     * @return DebitNoteData
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data.
     *
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }
}

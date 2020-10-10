<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * DebitNotes
 *
 * @ORM\Table(name="DebitNotes")
 * @ORM\Entity
 */
class DebitNotes
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
    private $projectid;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="DateIssued", type="datetime", nullable=false)
     */
    private $dateissued;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="SubmissionDeadline", type="date", nullable=false)
     */
    private $submissiondeadline;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="SubmitDate", type="date", nullable=true)
     */
    private $submitdate;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="DueDate", type="date", nullable=false)
     */
    private $duedate;

    /**
     * @var string
     *
     * @ORM\Column(name="Job", type="string", length=128, nullable=false)
     */
    private $job;

    /**
     * @var int
     *
     * @ORM\Column(name="SubmissionEvent", type="integer", nullable=false, options={"comment"="OwnCloud Calendar Object Id"})
     */
    private $submissionevent;

    /**
     * @var int
     *
     * @ORM\Column(name="SubmissionTask", type="integer", nullable=false, options={"comment"="OwnCloud Calendar Object Id"})
     */
    private $submissiontask;

    /**
     * @var int
     *
     * @ORM\Column(name="DueEvent", type="integer", nullable=false, options={"comment"="OwnCloud Calendar Object Id"})
     */
    private $dueevent;



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
     * Set projectid.
     *
     * @param int $projectid
     *
     * @return DebitNotes
     */
    public function setProjectid($projectid)
    {
        $this->projectid = $projectid;

        return $this;
    }

    /**
     * Get projectid.
     *
     * @return int
     */
    public function getProjectid()
    {
        return $this->projectid;
    }

    /**
     * Set dateissued.
     *
     * @param \DateTime $dateissued
     *
     * @return DebitNotes
     */
    public function setDateissued($dateissued)
    {
        $this->dateissued = $dateissued;

        return $this;
    }

    /**
     * Get dateissued.
     *
     * @return \DateTime
     */
    public function getDateissued()
    {
        return $this->dateissued;
    }

    /**
     * Set submissiondeadline.
     *
     * @param \DateTime $submissiondeadline
     *
     * @return DebitNotes
     */
    public function setSubmissiondeadline($submissiondeadline)
    {
        $this->submissiondeadline = $submissiondeadline;

        return $this;
    }

    /**
     * Get submissiondeadline.
     *
     * @return \DateTime
     */
    public function getSubmissiondeadline()
    {
        return $this->submissiondeadline;
    }

    /**
     * Set submitdate.
     *
     * @param \DateTime|null $submitdate
     *
     * @return DebitNotes
     */
    public function setSubmitdate($submitdate = null)
    {
        $this->submitdate = $submitdate;

        return $this;
    }

    /**
     * Get submitdate.
     *
     * @return \DateTime|null
     */
    public function getSubmitdate()
    {
        return $this->submitdate;
    }

    /**
     * Set duedate.
     *
     * @param \DateTime $duedate
     *
     * @return DebitNotes
     */
    public function setDuedate($duedate)
    {
        $this->duedate = $duedate;

        return $this;
    }

    /**
     * Get duedate.
     *
     * @return \DateTime
     */
    public function getDuedate()
    {
        return $this->duedate;
    }

    /**
     * Set job.
     *
     * @param string $job
     *
     * @return DebitNotes
     */
    public function setJob($job)
    {
        $this->job = $job;

        return $this;
    }

    /**
     * Get job.
     *
     * @return string
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * Set submissionevent.
     *
     * @param int $submissionevent
     *
     * @return DebitNotes
     */
    public function setSubmissionevent($submissionevent)
    {
        $this->submissionevent = $submissionevent;

        return $this;
    }

    /**
     * Get submissionevent.
     *
     * @return int
     */
    public function getSubmissionevent()
    {
        return $this->submissionevent;
    }

    /**
     * Set submissiontask.
     *
     * @param int $submissiontask
     *
     * @return DebitNotes
     */
    public function setSubmissiontask($submissiontask)
    {
        $this->submissiontask = $submissiontask;

        return $this;
    }

    /**
     * Get submissiontask.
     *
     * @return int
     */
    public function getSubmissiontask()
    {
        return $this->submissiontask;
    }

    /**
     * Set dueevent.
     *
     * @param int $dueevent
     *
     * @return DebitNotes
     */
    public function setDueevent($dueevent)
    {
        $this->dueevent = $dueevent;

        return $this;
    }

    /**
     * Get dueevent.
     *
     * @return int
     */
    public function getDueevent()
    {
        return $this->dueevent;
    }
}

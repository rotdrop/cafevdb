<?php

namespace OCA\CAFEVDB\Database\DBAL\Entities;


use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectWebPages
 *
 * @ORM\Table(name="ProjectWebPages", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectId", columns={"ProjectId", "ArticleId"})})
 * @ORM\Entity
 */
class ProjectWebPages
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
     * @ORM\Column(name="ProjectId", type="integer", nullable=false, options={"default"="-1"})
     */
    private $projectid = '-1';

    /**
     * @var int
     *
     * @ORM\Column(name="ArticleId", type="integer", nullable=false, options={"default"="-1"})
     */
    private $articleid = '-1';

    /**
     * @var string
     *
     * @ORM\Column(name="ArticleName", type="string", length=128, nullable=false, options={"default"=""})
     */
    private $articlename = '';

    /**
     * @var int
     *
     * @ORM\Column(name="CategoryId", type="integer", nullable=false, options={"default"="-1"})
     */
    private $categoryid = '-1';

    /**
     * @var int
     *
     * @ORM\Column(name="Priority", type="integer", nullable=false, options={"default"="-1"})
     */
    private $priority = '-1';



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
     * @return ProjectWebPages
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
     * Set articleid.
     *
     * @param int $articleid
     *
     * @return ProjectWebPages
     */
    public function setArticleid($articleid)
    {
        $this->articleid = $articleid;

        return $this;
    }

    /**
     * Get articleid.
     *
     * @return int
     */
    public function getArticleid()
    {
        return $this->articleid;
    }

    /**
     * Set articlename.
     *
     * @param string $articlename
     *
     * @return ProjectWebPages
     */
    public function setArticlename($articlename)
    {
        $this->articlename = $articlename;

        return $this;
    }

    /**
     * Get articlename.
     *
     * @return string
     */
    public function getArticlename()
    {
        return $this->articlename;
    }

    /**
     * Set categoryid.
     *
     * @param int $categoryid
     *
     * @return ProjectWebPages
     */
    public function setCategoryid($categoryid)
    {
        $this->categoryid = $categoryid;

        return $this;
    }

    /**
     * Get categoryid.
     *
     * @return int
     */
    public function getCategoryid()
    {
        return $this->categoryid;
    }

    /**
     * Set priority.
     *
     * @param int $priority
     *
     * @return ProjectWebPages
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Get priority.
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }
}

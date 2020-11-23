<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * ProjectWebPage
 *
 * @ORM\Table(name="ProjectWebPages", uniqueConstraints={@ORM\UniqueConstraint(name="ProjectId", columns={"ProjectId", "ArticleId"})})
 * @ORM\Entity
 */
class ProjectWebPage
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

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
  private $projectId = '-1';

  /**
   * @var int
   *
   * @ORM\Column(name="ArticleId", type="integer", nullable=false, options={"default"="-1"})
   */
  private $articleId = '-1';

  /**
   * @var string
   *
   * @ORM\Column(name="ArticleName", type="string", length=128, nullable=false, options={"default"=""})
   */
  private $articleName = '';

  /**
   * @var int
   *
   * @ORM\Column(name="CategoryId", type="integer", nullable=false, options={"default"="-1"})
   */
  private $categoryId = '-1';

  /**
   * @var int
   *
   * @ORM\Column(name="Priority", type="integer", nullable=false, options={"default"="-1"})
   */
  private $priority = '-1';

  /**
   * In principle there can be more than one project attached to one
   * web-page, however, this is just the join-table and ManyToMany is
   * just a pain in the ass.
   *
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="webPages", fetch="EXTRA_LAZY")
   * @ORM\JoinColumn(name="projectId", referencedColumnName="Id")
   */
  private $project;

  public function __construct() {
    $this->arrayCTOR();
  }

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
   * @return ProjectWebPages
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
   * Set articleId.
   *
   * @param int $articleId
   *
   * @return ProjectWebPages
   */
  public function setArticleId($articleId)
  {
    $this->articleId = $articleId;

    return $this;
  }

  /**
   * Get articleId.
   *
   * @return int
   */
  public function getArticleId()
  {
    return $this->articleId;
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
   * Set categoryId.
   *
   * @param int $categoryId
   *
   * @return ProjectWebPages
   */
  public function setCategoryId($categoryId)
  {
    $this->categoryId = $categoryId;

    return $this;
  }

  /**
   * Get categoryId.
   *
   * @return int
   */
  public function getCategoryId()
  {
    return $this->categoryId;
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

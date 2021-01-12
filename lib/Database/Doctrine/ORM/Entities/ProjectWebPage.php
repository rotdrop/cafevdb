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
 * @ORM\Table(name="ProjectWebPages", uniqueConstraints={@ORM\UniqueConstraint(columns={"project_id", "article_id"})})
 * @ORM\Entity(repositoryClass="\OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectWebPagesRepository")
 */
class ProjectWebPage implements \ArrayAccess
{
  use CAFEVDB\Traits\ArrayTrait;
  use CAFEVDB\Traits\FactoryTrait;

  /**
   * @ORM\ManyToOne(targetEntity="Project", inversedBy="webPages", fetch="EXTRA_LAZY"))
   * @ORM\Id
   * @TODO this should cascade deletes
   */
  private $project;

  /**
   * @var int
   * @ORM\Column(type="integer", nullable=false, options={"default"="-1"})
   * @ORM\Id
   */
  private $articleId = '-1';

  /**
   * @var string
   *
   * @ORM\Column(type="string", length=128, nullable=false, options={"default"=""})
   */
  private $articleName = '';

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"="-1"})
   */
  private $categoryId = '-1';

  /**
   * @var int
   *
   * @ORM\Column(type="integer", nullable=false, options={"default"="-1"})
   */
  private $priority = '-1';

  public function __construct() {
    $this->arrayCTOR();
  }

  /**
   * Set project.
   *
   * @param int $project
   *
   * @return ProjectWebPages
   */
  public function setProject($project)
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return int
   */
  public function getProject()
  {
    return $this->project;
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
   * Set articleName.
   *
   * @param string $articleName
   *
   * @return ProjectWebPages
   */
  public function setArticleName($articleName)
  {
    $this->articleName = $articleName;

    return $this;
  }

  /**
   * Get articleName.
   *
   * @return string
   */
  public function getArticleName()
  {
    return $this->articleName;
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

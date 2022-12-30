<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use OCA\CAFEVDB\Database\Doctrine\ORM as CAFEVDB;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;
use OCA\CAFEVDB\Wrapped\Doctrine\Common\Collections\ArrayCollection;

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
   * @todo this should cascade deletes
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

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct()
  {
    $this->arrayCTOR();
  }
  // phpcs:enable

  /**
   * Set project.
   *
   * @param null|Project $project
   *
   * @return ProjectWebPage
   */
  public function setProject($project):ProjectWebPage
  {
    $this->project = $project;

    return $this;
  }

  /**
   * Get project.
   *
   * @return Project
   */
  public function getProject():Project
  {
    return $this->project;
  }

  /**
   * Set articleId.
   *
   * @param int $articleId
   *
   * @return ProjectWebPage
   */
  public function setArticleId(int $articleId):ProjectWebPage
  {
    $this->articleId = $articleId;

    return $this;
  }

  /**
   * Get articleId.
   *
   * @return int
   */
  public function getArticleId():int
  {
    return $this->articleId;
  }

  /**
   * Set articleName.
   *
   * @param string $articleName
   *
   * @return ProjectWebPage
   */
  public function setArticleName(string $articleName):ProjectWebPage
  {
    $this->articleName = $articleName;

    return $this;
  }

  /**
   * Get articleName.
   *
   * @return string
   */
  public function getArticleName():string
  {
    return $this->articleName;
  }

  /**
   * Set categoryId.
   *
   * @param null|int $categoryId
   *
   * @return ProjectWebPage
   */
  public function setCategoryId($categoryId):ProjectWebPage
  {
    $this->categoryId = $categoryId;

    return $this;
  }

  /**
   * Get categoryId.
   *
   * @return int
   */
  public function getCategoryId():int
  {
    return $this->categoryId;
  }

  /**
   * Set priority.
   *
   * @param null|int $priority
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

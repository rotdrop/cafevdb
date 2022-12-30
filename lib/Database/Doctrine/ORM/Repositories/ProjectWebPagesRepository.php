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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use Doctrine\Common\Proxy\Proxy;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/** Repository for the per-project web-pages. */
class ProjectWebPagesRepository extends EntityRepository
{
  /**
   * Attach the given web-article to the given project.
   *
   * @param int|Project $projectOrId Either the project-id or a
   * (reference to the) associated project entity.
   *
   * @param array|ProjectWebArticle $webArticle Web article with all remaining data fields
   * except id and ProjectId.
   * ```
   * [
   *   'ArticleId' => int
   *   'ArticleName' => string
   *   'CategoryId' => int
   *   'Priority' => int
   * ]
   * ```.
   *
   * @return ProjectWebPage The updated or created project web page.
   */
  public function attachProjectWebPage($projectOrId, $webArticle):Entities\ProjectWebPage
  {
    /** @var Entities\Project $project */
    if ($projectOrId instanceof Entities\Project) {
      $project = $projectOrId;
      $projectId = $project->getId();
    } else {
      $projectId = $projectOrId;
      $project = null;
    }
    $articleId = $webArticle['articleId'];

    $entityManager = $this->getEntityManager();
    $projectWebPage = $this->find([ 'project' => $projectId,
                                    'articleId' => $articleId, ]);
    if (empty($projectWebPage)) {
      if (empty($project)) {
        $project = $entityManager->getReference(Entities\Project::class, $projectId);
      }
      $projectWebPage = (new Entities\ProjectWebPage())
                      ->setProject($project)
                      ->setArticleId($articleId);
    }
    $projectWebPage->setArticlename($webArticle['articleName'])
                   ->setCategoryId($webArticle['categoryId'])
                   ->setPriority($webArticle['priority']);
    $project = $projectWebPage->getProject();

    if (!($project instanceof Proxy) || $project->__isInitialized()) {
      // also update the project entity
      $project->getWebPages()->add($projectWebPage);
    }

    $entityManager->persist($projectWebPage);
    $entityManager->flush();

    return $projectWebPage;
  }

  /**
   * @param mixed $selector
   *
   * @param mixed $attributes
   *
   * @return void
   */
  public function mergeAttributes(mixed $selector, mixed $attributes)
  {
    $entityManager = $this->getEntityManager();
    $articles = $this->findBy($selector);
    foreach ($articles as $webArticle) {
      foreach ($attributes as $key => $value) {
        $webArticle[$key] = $value;
      }
      $entityManager->merge($webArticle);
    }
    $entityManager->flush();
  }
}

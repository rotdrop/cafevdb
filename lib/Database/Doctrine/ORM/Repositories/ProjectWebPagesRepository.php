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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;

class ProjectWebPagessRepository extends EntityRepository
{
  /**
   * Attach the given web-article to the given project.
   *
   * @param int|Project $projectOrId Either the project-id or a
   * (reference to the) associated project entity.
   *
   * @param array|ProjectWebArticel Web article with all remaining data fields
   * except id and ProjectId.
   *
   * ```
   * [
   *   'ArticleId' => int
   *   'ArticleName' => string
   *   'CategoryId' => int
   *   'Priority' => int
   * ]
   * ```
   *
   * @return ProjectWebPage The updated or created project web page.
   */
  public function attachProjectWebPage($projectOrId, $webArticle):Entities\ProjectWebPage
  {
    $entityManager = $this->getEntityManager();
    $projectWebPage = $this->findOneBy([ 'projectId' => $projectId,
                                         'articleId' => $articleId, ]);
    if (empty($projectWebPage)) {
      $project = $entityManager->getReference(Entities\Project::class, $projectId);
      $projectWebPage = new Entities\ProjectWebPage()
                      ->setProject($project)
                      ->setArticleId($article['ArticleId']);
    }
    $projectWebPage->setArticlename($article['ArticleName'])
                   ->setCategoryId($article['CategoryId'])
                   ->setPriority($article['Priority']);
    $projectWebPage = $entityManager->merge($projectWebPage);
    $this->entityManager->flush($projectWebPage);

    return $projectWebPage;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

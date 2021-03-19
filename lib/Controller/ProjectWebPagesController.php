<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\IRequest;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;

class ProjectWebPagesController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\EventsService */
  private $projectsService;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , ConfigService $configService
    , ProjectService $projectService
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->projectService = $projectService;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $projectId = -1, $articleId = -1, $articleData = [])
  {
    if ($projectId <= 0) {
      return self::grumble($this->l->t("Invalid or unset project-id: `%s'", [ $projectId ]));
    }

    if (count($articleData) > 0 &&
        $articleId >= 0 &&
        $articleData['articleId'] != $articleId) {
      return self::grumble(
        $this->l->t("Submitted article id %d ".
                    "does not match the id %d stored in the article-data.",
                    [ $articleId, $articleData['articleId'] ]));
    }

    if ($topic != 'add') {
      // require both, articleId and articleData
      if ($articleId < 0) {
        return self::grumble($this->l->t("Invalid or unset article-id: `%s'", [ $articleId ]));
      }
      if (count($articleData) == 0) {
        return self::grumble(
          $this->l->t("Cannot perform action `%s' with article with id %d, project with id %d: ".
                      "missing article-data.",
                      [ $action, $articleId, $projectId ]));
      }
    }

    switch ($topic) {
    case 'add':
      try {
        // This simply means: create a new page for the project.
        $article = $this->projectService->createProjectWebPage($projectId);
      } catch (\Throwable $t) {
        return self::grumble($this->exceptionChainData($t));
      }
      $message = $this->l->t("Created a new public web page with name %s and id %d ".
                             "for the project with id %d.",
                             [ $article['articleName'], $article['articleId'], $projectId ]);
      // If there is no rehearsal page attached to the project, then attach one
      $rehearsalsCat = $this->getConfigValue('redaxoRehearsals');
      $rehearsal = null;
      try {
        $articles = $this->projectService->fetchProjectWebPages($projectId);
        foreach($articles as $article) {
          if ($article['categoryId'] == $rehearsalsCat) {
            $rehearsal = $article;
            break;
          }
        }
      } catch (\Throwable $t) {
        // ignore
      }
      if ($rehearsal === null) {
        // create one, but ignore anypotential error
        try {
          $this->projectService->createProjectWebPage($projectId, 'rehearsals');
          $message .= ' '.$this->l->t("Created additionally a new rehearsal web page.");
        } catch (\Throwable $t) {
          $message .= ' '.$this->l->t("Failed to create additionally a new rehearsal web-page.");
        }
      }
      return self::response($message);
    case 'link':
      try {
        $this->projectService->attachProjectWebPage($projectId, $articleData);
      } catch (\Throwable $t) {
        return self::grumble($this->exceptionChainData($t));
      }
      return self::response(
        $this->l->t("Linked the existing public web-article %s (id %d) to the project with id %d",
                    [ $articleData['articleName'], $articleId, $projectId ]));
    case 'unlink':
      try {
        $this->projectService->detachProjectWebPage($projectId, $articleId);
      } catch (\Throwable $t) {
        return self::grumble($this->exceptionChainData($t));
      }
      return self::response(
        $this->l->t("Detached the public web-article %s (id %d) from the project with id %d",
                    [ $articleData['articleName'], $articleId, $projectId ]));
    case 'delete':
      try {
        $this->projectService->deleteProjectWebPage($projectId, $articleId);
      } catch (\Throwable $t) {
        return self::grumble($this->exceptionChainData($t));
      }
      return self::response(
        $this->l->t("Removed public web page %s (id %d) from the project with id %d.",
                    [ $articleData['articleName'], $articleId, $projectId ]));
      break;
    }

    return self::grumble($this->l->t('Unknown Request'));
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

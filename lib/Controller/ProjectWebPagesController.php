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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\IRequest;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;

/** AJAX end-points to manage the web-pages via the CMS. */
class ProjectWebPagesController extends Controller
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var \OCA\CAFEVDB\Service\ParameterService */
  private $parameterService;

  /** @var \OCA\CAFEVDB\Service\EventsService */
  private $projectService;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    ProjectService $projectService,
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->projectService = $projectService;
    $this->l = $this->l10N();
  }
  // phpcs:enable

  /**
   * @param string $topic
   *
   * @param null|int $projectId
   *
   * @param null|int $articleId
   *
   * @param array $articleData
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(
    string $topic,
    ?int $projectId = null,
    ?int $articleId = null,
    array $articleData = [],
  ):Http\Response {
    if ($topic != 'ping' && $projectId <= 0) {
      return self::grumble($this->l->t("Invalid or unset project-id: `%s'", [ $projectId ]));
    }

    if (count($articleData) > 0 &&
        $articleId >= 0 &&
        $articleData['articleId'] != $articleId) {
      return self::grumble(
        $this->l->t(
          "Submitted article id %d ".
          "does not match the id %d stored in the article-data.",
          [ $articleId, $articleData['articleId'] ]));
    }

    if ($topic != 'add' && $topic != 'ping') {
      // require both, articleId and articleData
      if ($articleId < 0) {
        return self::grumble($this->l->t("Invalid or unset article-id: `%s'", [ $articleId ]));
      }
      if (count($articleData) == 0) {
        return self::grumble(
          $this->l->t(
            "Cannot perform action `%s' with article with id %d, project with id %d: ".
            "missing article-data.",
            [ $topic, $articleId, $projectId ]));
      }
    }

    switch ($topic) {
      case 'ping':
        if ($this->projectService->pingWebPages() === false) {
          return self::grumble($this->l->t('Unable to ping project web-pages CMS'));
        } else {
          return self::response('OK');
        }
        break;
      case 'add':
        try {
          // This simply means: create a new page for the project.
          $article = $this->projectService->createProjectWebPage($projectId);
        } catch (\Throwable $t) {
          return self::grumble($this->exceptionChainData($t));
        }
        $message = $this->l->t(
          "Created a new public web page with name %s and id %d ".
          "for the project with id %d.",
          [ $article['articleName'], $article['articleId'], $projectId ]);
        // If there is no rehearsal page attached to the project, then attach one
        $rehearsalsCat = $this->getConfigValue('redaxoRehearsals');
        $rehearsal = null;
        try {
          $articles = $this->projectService->fetchProjectWebPages($projectId);
          foreach ($articles as $article) {
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
          $this->l->t(
            "Linked the existing public web-article %s (id %d) to the project with id %d",
            [ $articleData['articleName'], $articleId, $projectId ]));
      case 'unlink':
        try {
          $this->projectService->detachProjectWebPage($projectId, $articleId);
        } catch (\Throwable $t) {
          return self::grumble($this->exceptionChainData($t));
        }
        return self::response(
          $this->l->t(
            "Detached the public web-article %s (id %d) from the project with id %d",
            [ $articleData['articleName'], $articleId, $projectId ]));
      case 'delete':
        try {
          $this->projectService->deleteProjectWebPage($projectId, $articleData);
        } catch (\Throwable $t) {
          return self::grumble($this->exceptionChainData($t));
        }
        return self::response(
          $this->l->t(
            "Removed public web page %s (id %d) from the project with id %d.",
            [ $articleData['articleName'], $articleId, $projectId ]));
        break;
    }

    return self::grumble($this->l->t('Unknown Request'));
  }
}

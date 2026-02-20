<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\IRequest;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Exceptions;

/** AJAX end-points to manage the web-pages via the CMS. */
#[TSAttributes\TypeScript]
class ProjectWebPagesController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public const END_POINT = 'project/webpages';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private ProjectService $projectService,
    protected ConfigService $configService,
  ) {
    parent::__construct($appName, $request);

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
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::END_POINT . '/{topic}')]
  public function serviceSwitch(
    string $topic,
    ?int $projectId = null,
    ?int $articleId = null,
    array $articleData = [],
  ):Http\Response {
    if ($topic != 'ping' && $projectId <= 0) {
      return self::grumble($this->l->t('Invalid or unset project-id: "%s".', [ $projectId ]));
    }

    if (count($articleData) > 0 &&
        $articleId >= 0 &&
        $articleData[EnumProjectWebPageParam::ARTICLE_ID->value] != $articleId) {
      return self::grumble(
        $this->l->t(
          'Submitted article id "%1$d" does not match the id "%2$d" stored in the article-data.',
          [ $articleId, $articleData[EnumProjectWebPageParam::ARTICLE_ID->value] ]));
    }

    $topic = EnumProjectWebPagesAction::get($topic);

    if ($topic != EnumProjectWebPagesAction::ADD && $topic != EnumProjectWebPagesAction::PING) {
      // require both, articleId and articleData
      if ($articleId < 0) {
        return self::grumble($this->l->t('Invalid or unset article-id: "%s".', [ $articleId ]));
      }
      if (count($articleData) == 0) {
        return self::grumble(
          $this->l->t(
            'Cannot perform action "%1$s" with article with id "%2$d", project with id "%3$d": '.
            'missing article-data.',
            [ $topic, $articleId, $projectId ]));
      }
    }

    switch ($topic) {
      case EnumProjectWebPagesAction::PING:
        if ($this->projectService->pingWebPages() === false) {
          return self::grumble($this->l->t('Unable to ping project web-pages CMS'));
        } else {
          return self::response('OK');
        }
        break;
      case EnumProjectWebPagesAction::ADD:
        try {
          // This simply means: create a new page for the project.
          $article = $this->projectService->createProjectWebPage($projectId);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Unable to create new project web pages for the project with id "%s".', $projectId),
            previous: $t,
            httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
          );
        }
        $message = $this->l->t(
          'Created a new public web page with name "%1$s" and id "%2$d" for the project with id "%3$d".',
          [ $article['articleName'], $article[EnumProjectWebPageParam::ARTICLE_ID->value], $projectId ],
        );
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
        } catch (Throwable $t) {
          // ignore
        }
        if ($rehearsal === null) {
          // create one, but ignore anypotential error
          try {
            $this->projectService->createProjectWebPage($projectId, 'rehearsals');
            $message .= ' '.$this->l->t('Created additionally a new rehearsal web page.');
          } catch (Throwable $t) {
            $message .= ' '.$this->l->t('Failed to create additionally a new rehearsal web-page.');
          }
        }
        return self::response($message);
      case EnumProjectWebPagesAction::LINK:
        try {
          $this->projectService->attachProjectWebPage($projectId, $articleData);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Unable to link the existing public web-article "%1$s" (id "%2$d") to the project with id "%3$d".',
              [ $articleData['articleName'], $articleId, $projectId ],
            ),
            previous: $t,
            httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
          );
        }
        return self::response(
          $this->l->t(
            'Linked the existing public web-article "%1$s" (id "%2$d") to the project with id "%3$d".',
            [ $articleData['articleName'], $articleId, $projectId ]));
      case EnumProjectWebPagesAction::UNLINK:
        try {
          $this->projectService->detachProjectWebPage($projectId, $articleId);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Unable to detach the public web-article "%s" (id "%d") from the project with id "%d".',
              [ $articleData['articleName'], $articleId, $projectId ],
            ),
            previous: $t,
            httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
          );
        }
        return self::response(
          $this->l->t(
            'Detached the public web-article "%1$s" (id "%2$d") from the project with id "%3$d".',
            [ $articleData['articleName'], $articleId, $projectId ],
          ));
      case EnumProjectWebPagesAction::DELETE:
        try {
          $this->projectService->deleteProjectWebPage($projectId, $articleData);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t(
              'Unable to remove the public web page "%1$s" (id "%2$d") from the project with id "%3$d".',
              [ $articleData['articleName'], $articleId, $projectId ],
            ),
            previous: $t,
            httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
          );
        }
        return self::response(
          $this->l->t(
            'Removed the public web page "%1$s" (id "%2$d") from the project with id "%3$d".',
            [ $articleData['articleName'], $articleId, $projectId ],
          ));
        break;
    }

    return self::grumble($this->l->t('Unknown Request'));
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023 Claus-Justus Heine
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

use Throwable;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;
use OCP\IDateTimeZone;
use OCP\IURLGenerator;

use OCA\CAFEVDB\Database\Cloud\Mapper\BlogMapper;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ToolTipsService;

/**
 * AJAX end-points for the db-app blog.
 *
 * @todo Check whether the blog is still needed at all. Perhaps replace by
 * context sensisitive one-time popup messages.
 */
class BlogController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /** @var \OCP\IDateTimeZone */
  private $timeZone;

  /** @var \OCP\IURLGenerator */
  private $urlGenerator;

  /** @var ParameterService */
  private $parameterService;

  /** @var ToolTipsService */
  protected $toolTipsService;

  /** @var BlogMapper */
  private $blogMapper;

  /** @var string */
  private $userId;

  /** @var IL10N */
  protected IL10N $l;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    IURLGenerator $urlGenerator,
    RequestParameterService $parameterService,
    ToolTipsService $toolTipsService,
    BlogMapper $blogMapper,
    ?string $userId,
    IL10N $l10n,
    IDateTimeZone $timeZone,
    ILogger $logger,
  ) {
    parent::__construct($appName, $request);

    $this->urlGenerator = $urlGenerator;
    $this->parameterService = $parameterService;
    $this->toolTipsService = $toolTipsService;
    $this->blogMapper = $blogMapper;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->l = $l10n;
    $this->timeZone = $timeZone;
  }
  // phpcs:enable

  /**
   * Return template for editor.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function editEntry():Response
  {
    $author   = $this->parameterService->getParam('author', $this->userId);

    $blogId   = $this->parameterService->getParam('blogId', null);
    $inReplyTo  = $this->parameterService->getParam('inReplyTo', null);
    $content  = $this->parameterService->getParam('content', '');
    $priority = $this->parameterService->getParam('priority', false);
    $popup    = $this->parameterService->getParam('popup', false);
    $reader   = $this->parameterService->getParam('reader', '');

    if (empty($author)) {
      return self::grumble($this->l->t('Refusing to create blog entry without author identity.'));
    }

    if (!empty($blogId) && empty($inReplyTo) && $content == '') {
      // This is an edit attempt.
      try {
        $entry = $this->blogMapper->find($blogId);
      } catch (Throwable $t) {
        $this->logger->logException($t);
        return self::grumble($this->l->t('Error, caught an exception `%s\'.', [$t->getMessage()]));
      }
      if (!$entry) {
        return self::grumble('Blog entry with id `%s\' could not be retrieved.', [$blogId]);
      }

      $content = $entry->getMessage();
      if ($entry->getInReplyTo() < 0) {
        $priority = $entry->getPriority();
      } else {
        $priority = false;
      }
      $popup   = $entry->getPopup() != 0;
      $reader  = $entry->getReader();
    } elseif ($inReplyTo >= 0) {
      $priority = false;
      $popup    = false;
      $reader   = '';
    }

    $template = 'blog/blogedit';
    $templateParameters = [
      'priority' => $priority,
      'popup' => $popup,
      'toolTips' => $this->toolTipsService,
    ];
    $renderAs = 'blank';
    $tmpl = new TemplateResponse($this->appName, $template, $templateParameters, $renderAs);
    $html = $tmpl->render();

    $responseData = [
      'content' => $html,
      'author' => $author,
      'blogId' => $blogId,
      'inReplyTo' => $inReplyTo,
      'text' => $content,
      'priority' => $priority,
      'popup' => $popup,
      'reader' => $reader,
      'message' => $content.' '.$blogId.' '.$inReplyTo
    ];

    return self::dataResponse($responseData);
  }

  /**
   * Return template for editor
   *
   * @param string $operation
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function action(string $operation):Response
  {
    $author    = $this->parameterService->getParam('author', $this->userId);
    $blogId    = $this->parameterService->getParam('blogId', null);
    $inReplyTo = $this->parameterService->getParam('inReplyTo', null);
    $content   = $this->parameterService->getParam('content', '');
    $priority  = $this->parameterService->getParam('priority', false);
    $popup     = $this->parameterService->getParam('popup', false);
    $reader    = $this->parameterService->getParam('reader', '');
    $clearRdr  = $this->parameterService->getParam('clearReader', false);

    $inReplyTo = $inReplyTo ?: null;

    $realValue = filter_var($popup, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]);
    if ($realValue === null) {
      return self::grumble(
        $this->l->t(
          'Value "%1$s" for set "%2$s" is not convertible to boolean.', [$popup, 'popup']));
    }
    $popup = $realValue;

    if ($clearRdr) {
      $reader = -1;
    }

    if (empty($author)) {
      return self::grumble($this->l->t('Refusing to create blog entry without author identity.'));
    }

    if ($priority !== false && !is_numeric($priority)) {
      return self::grumble(
        $this->l->t('Message priority should be numeric (and in principle positiv and in the range 0 - 255). I got `%s\'', [ $priority ]));
    }

    $generateContents = true;
    $html = '';

    switch ($operation) {
      case 'create':
        // Sanity checks
        if (empty(trim($content))) {
          return self::grumble($this->l->t('Refusing to create empty blog entry.'));
        }
        $priority = intval($priority) % 256;
        $this->logInfo('IN REPLY TO ' . (int)($inReplyTo === null) . ' "' . $inReplyTo);
        /* $result = */$this->blogMapper->createNote($author, $inReplyTo, $content, $priority, $popup);
        break;
      case 'modify':
        if ($blogId < 0) {
          return self::grumble($this->l->t('Cannot modify a blog-entry without id.'));
        }
        $priority = intval($priority) % 256;
        /* $result = */$this->blogMapper->modifyNote($author, $blogId, trim($content), $priority, $popup, $reader);
        break;
      case 'markread':
        if ($blogId < 0) {
          return self::grumble($this->l->t('Cannot modify a blog-entry without id.'));
        }
        /* $result = */$this->blogMapper->modifyNote($author, $blogId, '', false, null, $author);
        $generateContents = false;
        break;
      case 'delete':
        if ($blogId < 0) {
          return self::grumble($this->l->t('Cannot delete a blog-thread without id.'));
        }
        /* $result = */$this->blogMapper->deleteNote($blogId, false);
        break;
      default:
        return self::grumble($this->l->t('Unknown Request'));
    }

    if ($generateContents) {
      $template = 'blog/blogthreads';
      $templateParameters = [
        'timezone' => $this->timeZone->getTimeZone(time())->getName(),
        'locale' => $this->l->getLocaleCode(), // this should already have been provided by the NC core
        'user' => $this->userId,
        'urlGenerator' => $this->urlGenerator,
        'renderer' => $this->blogMapper,
        'toolTips' => $this->toolTipsService,
      ];
      $renderAs = 'blank';
      $tmpl = new TemplateResponse($this->appName, $template, $templateParameters, $renderAs);
      $html = $tmpl->render();
    }

    $responseData = [
      'content' => $html,
    ];

    return self::dataResponse($responseData);
  }
}

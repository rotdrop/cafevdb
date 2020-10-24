<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\Cloud\Mapper\BlogMapper;
use OCA\CAFEVDB\Service\RequestParameterService;

class BlogController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var ParameterService */
  private $parameterService;

  /** @var BlogMapper */
  private $blogMapper;

  /** @var string */
  private $userId;

  /** @var IL10N */
  private $l;

  /** @var ILogger */
  private $logger;

  public function __construct(
    $appName
    , IRequest $request
    , RequestParameterService $parameterService
    , BlogMapper $blogMapper
    , $userId
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);

    $this->parameterService = $parameterService;
    $this->blogMapper = $blogMapper;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->l = $l10n;
  }

  /**
   * Return template for editor
   *
   * @NoAdminRequired
   */
  public function editEntry()
  {
    $author   = $this->parameterService->getParam('author', $this->userId);

    $blogId   = $this->parameterService->getParam('blogId', -1);
    $inReply  = $this->parameterService->getParam('inReply', -1);
    $text     = $this->parameterService->getParam('text', '');
    $priority = $this->parameterService->getParam('priority', false);
    $popup    = $this->parameterService->getParam('popup', false);
    $reader   = $this->parameterService->getParam('reader', '');

    if (empty(author)) {
      return self::grumble($this->l->t('Refusing to create blog entry without author identity.'));
    }

    if ($blogId >= 0 && $inReply == -1 && $text == '') {
      // This is an edit attempt.
      try {
        $entry = Blog::fetchNote($blogId);
      } catch (\Throwable $t) {
        $this->logger->logException($t);
        return self::grumble($this->l->t('Error, caught an exception `%s\'.', [$e->getMessage()]));
      }
      if (!$entry) {
        return self::grumble('Blog entry with id `%s\' could not be retrieved.', [$blogId]);
      }

      $Text     = $entry->getMessage();
      if ($entry->getInreplyto() < 0) {
        $priority = $entry->getPriority();
      } else {
        $priority = false;
      }
      $popup   = $entry->getPopup() != 0;
      $reader  = $entry->getReader();
    } else if ($inReply >= 0) {
      $priority = false;
      $popup    = false;
      $reader   = '';
    }

  // $tmpl = new OCP\Template(Config::APP_NAME, 'blogedit');

    $template = 'blogedit';
    $templateParameters = [
      'priority' => $priority,
      'popup' => $popup,
    ];
    $renderAs = 'blank';
    $tmpl = new TemplateResponse($this->appName, $template, $templateParameters, $renderAs);
    $html = $tmpl->render();

    $responseData = [
      'content' => $html,
      'author' => $author,
      'blogId' => $blogId,
      'inReply' => $inReply,
      'text' => $text,
      'priority' => $priority,
      'popup' => $popup,
      'reader' => $reader,
      'message' => $text.' '.$blogId.' '.$inReply
    ];

    return self::dataResponse($responseData);
  }

  public function action($operation)
  {
    switch ($operation) {
      case 'create':
      case 'modify':
      case 'markread':
      case 'delete':
      default:
        return self::grumble($this->l->t('Unknown Request'));
    }
  }


}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

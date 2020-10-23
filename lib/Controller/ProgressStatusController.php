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

use OCA\CAFEVDB\Service\ProgressStatusService;
use OCP\IDBConnection;

class ProgressStatusController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  /** @var ProgressStatusService */
  private $progressStatusService;

  /** @var string */
  private $userId;

  /** @var IL10N */
  private $l;

  /** @var ILogger */
  private $logger;

  public function __construct(
    $appName
    , IRequest $request
    , ProgressStatusService $progressStatusService
    , $userId
    , ILogger $logger
    , IL10N $l10n
  ) {
    parent::__construct($appName, $request);

    $this->progressStatusService = $progressStatusService;
    $this->logger = $logger;
    $this->userId = $userId;
    $this->l = $l10n;
  }

  /**
   * Return settings form
   *
   * @NoAdminRequired
   */
  public function get($id)
  {
    try {
      $progress = $this->progressStatusService->get($id);
    } catch (\Throwable $t) {
      $this->logger->logException($t);
      return self::grumble($this->l->t('Exception `%s\'', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
    }
    if (empty($progress)) {
      return self::grumble($this->l->t('Unable to find status of job `%s\'', [ $uuid ]));
    }
    if ($progress->getUserId() != $this->userId) {
      return self::grumble($this->l->t('Permission denied').$progress->getUserId().'/'.$this->userId, Http::STATUS_FORBIDDEN);
    }
    return self::dataResponse([ 'current' => $progress->getCurrent(), 'target' => $progress->getTarget() ]);
  }

  /**
   * Return settings form
   *
   * @NoAdminRequired
   */
  public function action($action)
  {
    switch($action) {
    case 'create':
      try {
        $progress = $this->progressStatusService->create(
          $this->request['current'],
          $this->request['target'],
          $this->request['id']
        );
        return self::dataResponse([
          'id' => $progress->getId(),
          'current' => $progress->getCurrent(),
          'target' => $progress->getTarget(),
        ]);
      } catch (\Throwable $t) {
        $this->logger->logException($t);
        return self::grumble($this->l->t('Exception `%s\'', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
      }
      break;
    case 'update':
      try {
        $progress = $this->progressStatusService->get($this->request['id']);
      } catch (\Throwable $t) {
        $this->logger->logException($t);
        return self::grumble($this->l->t('Exception `%s\'', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
      }
      try {
        $progress->merge(['current' => $this->request['current']]);
        return self::dataResponse([
          'id' => $progress->getId(),
          'current' => $progress->getCurrent(),
          'target' => $progress->getTarget(),
        ]);
      } catch (\Throwable $t) {
        $this->logger->logException($t);
        return self::grumble($this->l->t('Exception `%s\'', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
      }
      break;
    case 'test':
      $progress = $this->progressStatusService->create(0, 100, $this->request['id']);
      for ($i = 0; $i <= $progress->getTarget(); $i++) {
        $progress->merge(['current' => $i]);
        usleep(500000);
      }
      return self::dataResponse([]);
      break;
    default:
      return self::grumble($this->l->t('Unknown Request'));
    }
  }

  /**
   * Return settings form
   *
   * @NoAdminRequired
   */
  public function test($id)
  {
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

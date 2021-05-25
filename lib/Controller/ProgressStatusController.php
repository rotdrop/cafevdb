<?php
/* Orchestra member, musician and project management application.
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
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Common\IProgressStatus;

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
   * @NoAdminRequired
   */
  public function get($id)
  {
    try {
      $progress = $this->progressStatusService->get($id);
    } catch (\Throwable $t) {
      $this->logger->logException($t);
      return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
    }
    if (empty($progress)) {
      return self::grumble($this->l->t('Unable to find status of job "%s"', [ $id ]));
    }
    return self::progressResponse($progress);
  }

  /**
   * @NoAdminRequired
   */
  public function action($operation)
  {
    switch($operation) {
      case 'create':
        try {
          $progress = $this->progressStatusService->create(
            $this->request['current'],
            $this->request['target'],
            $this->request['data']
          );
          return self::progressResponse($progress);
        } catch (\Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
        break;
      case 'update':
        try {
          $progress = $this->progressStatusService->get($this->request['id']);
        } catch (\Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
        try {
          foreach ([ 'current', 'target', 'data' ] as $key) {
            ${$key} = $this->request[$key]?:null;
          }
          $progress->update($current, $target, $data);
          return self::progressResponse($progress);
        } catch (\Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
        break;
      case 'test':
        $target = $this->request['target']?:100;
        $progress = $this->progressStatusService->create(0, $target, $this->request['data'], $this->request['id']);
        for ($i = 0; $i <= $progress->getTarget(); $i++) {
          $progress->update($i);
          usleep(500000);
        }
        return self::dataResponse([]);
        break;
      default:
        return self::grumble($this->l->t('Unknown Request'));
    }
  }

  static private function progressResponse(IProgressStatus $progress)
  {
    return self::dataResponse([
      'id' => $progress->getId(),
      'current' => $progress->getCurrent(),
      'target' => $progress->getTarget(),
      'data' => $progress->getData(),
    ]);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

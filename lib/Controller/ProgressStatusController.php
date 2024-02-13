<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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
use OCP\AppFramework\Http\DataResponse;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Http\TemplateResponse;
use OCA\CAFEVDB\Service\ProgressStatusService;
use OCA\CAFEVDB\Common\IProgressStatus;

/** AJAX end-point for progress status. */
class ProgressStatusController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private ProgressStatusService $progressStatusService,
    protected ILogger $logger,
    protected IL10N $l,
  ) {
    parent::__construct($appName, $request);

    $this->progressStatusService = $progressStatusService;
  }
  // phpcs:enable

  /**
   * @param string $id
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function get(string $id):Http\Response
  {
    try {
      $progress = $this->progressStatusService->get($id);
    } catch (Throwable $t) {
      $this->logger->logException($t);
      return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
    }
    if (empty($progress)) {
      return self::grumble($this->l->t('Unable to find the status of the job "%s"', [ $id ]));
    }
    return self::progressResponse($progress);
  }

  /**
   * @param string $operation
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function action(string $operation):Http\Response
  {
    switch ($operation) {
      case 'create':
        try {
          $progress = $this->progressStatusService->create(
            $this->request['current'],
            $this->request['target'],
            $this->request['data']
          );
          return self::progressResponse($progress);
        } catch (Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
        break;
      case 'update':
        try {
          $progress = $this->progressStatusService->get($this->request['id']);
        } catch (Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
        try {
          foreach ([ 'current', 'target', 'data' ] as $key) {
            ${$key} = $this->request[$key]?:null;
          }
          $progress->update($current, $target, $data);
          return self::progressResponse($progress);
        } catch (Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
        break;
      case 'delete':
        try {
          $progress = $this->progressStatusService->get($this->request['id']);
        } catch (Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
        try {
          $progress->delete();
          return self::response($this->l->t('Progress "%s" successfully deleted.', $this->request['id']));
        } catch (Throwable $t) {
          $this->logger->logException($t);
          return self::grumble($this->l->t('Exception "%s"', [$t->getMessage()]), Http::STATUS_BAD_REQUEST);
        }
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

  /**
   * @param IProgressStatus $progress
   *
   * @return DataResponse
   */
  private static function progressResponse(IProgressStatus $progress):DataResponse
  {
    return self::dataResponse([
      'id' => $progress->getId(),
      'current' => $progress->getCurrent(),
      'target' => $progress->getTarget(),
      'data' => $progress->getData(),
    ]);
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2026 Claus-Justus Heine
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
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Common\IProgressStatus;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ProgressStatusService;

/** AJAX end-point for progress status. */
#[TSAttributes\TypeScript]
class ProgressStatusController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  public const GET_URL = '/foregroundjob/progress/{id}';
  public const POST_URL = '/foregroundjob/progress/{operation}';

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private ProgressStatusService $progressStatusService,
    protected ILogger $logger,
    protected IL10N $l,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * @param string $id
   *
   * @return Http\DataResponse|Http\JSONResponse
   *
   * @throws Exceptions\EnduserNotificationException
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'GET', url: self::GET_URL)]
  public function get(string $id): Http\DataResponse|Http\JsONResponse
  {
    try {
      $progress = $this->progressStatusService->get($id);
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        message: $this->l->t('Caught an exception.'),
        previous: $t,
        context: ['id' => $id],
      );
    }
    if (empty($progress)) {
      throw new Exceptions\EnduserNotificationException(
        message: $this->l->t('Unable to find the status of the job "%s"', [ $id ]),
        context: ['id' => $id],
      );
    }
    return self::progressResponse($progress);
  }

  /**
   * @param string $operation
   *
   * @return Http\DataResponse|Http\JSONResponse
   *
   * @throws Exceptions\EnduserNotificationException
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: self::POST_URL)]
  public function action(string $operation): Http\DataResponse|Http\JSONResponse
  {
    $operation = EnumProgressStatusOperation::get($operation);
    switch ($operation) {
      case EnumProgressStatusOperation::CREATE:
        $current = $this->request->getParam('current', null);
        $target = $this->request->getParam('target', null);
        $data = $this->request->getParam('data', null);
        try {
          $progress = $this->progressStatusService->create(
            start: $current,
            stop: $target,
            data: $data,
          );
          return self::progressResponse($progress);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Caught an exception.'),
            previous: $t,
            context: compact('current', 'target', 'data'),
          );
        }
        break;
      case EnumProgressStatusOperation::UPDATE:
        $id = $this->request->getParam('id');
        try {
          $progress = $this->progressStatusService->get($id);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Caught an exception.'),
            previous: $t,
            context: compact('id'),
          );
        }
        try {
          $current = $this->request->getParam('current', null);
          $target = $this->request->getParam('target', null);
          $data = $this->request->getParam('data', null);
          $progress->update($current, $target, $data);
          return self::progressResponse($progress);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Caught an exception.'),
            previous: $t,
            context: compact('id', 'current', 'target', 'data'),
          );
        }
        break;
      case EnumProgressStatusOperation::DELETE:
        $id = $this->request->getParam('id');
        try {
          $progress = $this->progressStatusService->get($id);
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Caught an exception.'),
            previous: $t,
            context: compact('id'),
          );
        }
        try {
          $progress->delete();
          return new DTO\MessagesResponse(
            messages: [$this->l->t('Progress "%s" successfully deleted.', $this->request->getParam('id'))],
          )->response();
        } catch (Throwable $t) {
          throw new Exceptions\EnduserNotificationException(
            message: $this->l->t('Caught an exception.'),
            previous: $t,
            context: compact('id'),
          );
        }
      default:
        throw new Exceptions\EnduserNotificationException($this->l->t('Unknown Request'));
    }
  }

  /**
   * @param IProgressStatus $progress
   *
   * @return DataResponse
   */
  private static function progressResponse(IProgressStatus $progress): Http\DataResponse|Http\JsONResponse
  {
    return DTO\ProgressResponse::fromArray([
      'id' => $progress->getId(),
      'current' => $progress->getCurrent(),
      'target' => $progress->getTarget(),
      'data' => $progress->getData(),
    ])->response();
  }
}

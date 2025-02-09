<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Middleware;

use Exception;

use Psr\Log\LoggerInterface;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OCP\IRequest;
use OC\AppFramework\Utility\QueryNotFoundException;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ConfigService;

/**
 * Turn an exception into a data response which can be parsed by the frontend
 * if the controller method has the @CatchExceptions annotation.
 */
class ExceptionMiddleware extends Middleware
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IAppContainer $appContainer,
    protected IControllerMethodReflector $reflector,
    protected IL10N $l,
    protected IRequest $request,
    protected LoggerInterface $logger,
  ) {
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   *
   * Convert the exception to a data-response for the front-end. In effect
   * this disables the exception handling of the Nextcloud core.
   */
  public function afterException($controller, $methodName, Exception $exception)
  {
    if ($this->reflector->hasAnnotation('DoNotCatchExceptions')) {
      throw $exception;
    }
    if (!($exception instanceof Exceptions\EnduserNotificationException)) {
      $exception = new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to serve request to "%s".', $this->request->getPathInfo()),
        0,
        $exception,
        httpStatusCode: ($exception instanceof QueryNotFoundException)
          ? Http::STATUS_NOT_FOUND : Http::STATUS_INTERNAL_SERVER_ERROR,
      );
    }
    $logEntry = $this->logException($exception, message: $exception->getMessage(), returnLogEntry: true);
    $httpStatusCode = $exception->getHttpStatusCode();
    return new JSONResponse($logEntry, $httpStatusCode);
  }
}

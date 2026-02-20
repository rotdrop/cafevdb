<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2022, 2023, 2024, 2025 Claus-Justus Heine
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
use ReflectionMethod;

use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;

use OCA\CAFEVDB\Attributes;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Service\AuthorizationService;

/**
 * Verifies whether an user has at least subadmin rights. To bypass use the
 * `@NoGroupMemberRequired` annotation or attribute.
 */
class GroupMemberMiddleware extends Middleware
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\HasAnnotationOrAttributeTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected AuthorizationService $authorizationService,
    protected IControllerMethodReflector $reflector,
    protected IL10N $l,
    protected LoggerInterface $logger,
  ) {
  }
  // phpcs:enable

  /**
   * {@inheritdoc}
   *
   * Check if the user is a sub-admin of the orchestra group.
   */
  public function beforeController($controller, $methodName)
  {
    $reflectionMethod = new ReflectionMethod($controller, $methodName);
    if (!$this->hasAnnotationOrAttribute($reflectionMethod, Attributes\NoGroupMemberRequired::class)) {
      if (!$this->authorizationService->authorized(null, AuthorizationService::PERMISSION_FRONTEND)) {
        throw new NotAdminException($this->l->t('Logged in user must be a member of the orchestra group'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Return 403 page in case of an exception
   */
  public function afterException($controller, $methodName, Exception $exception)
  {
    if (!($exception instanceof NotAdminException)) {
      throw $exception;
    }
    // @todo: decide whether to use ExceptionMiddleware or not
    $response = $this->templateResponse('403', [], Constants::RENDER_AS_GUEST, appName: 'core');
    $response->setStatus(Http::STATUS_FORBIDDEN);
    return $response;
  }
}

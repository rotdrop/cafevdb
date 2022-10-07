<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;

use OCA\CAFEVDB\Service\ConfigService;

/**
 * Verifies whether an user has at least subadmin rights.
 * To bypass use the `@NoSubadminRequired` annotation
 */
class GroupMemberMiddleware extends Middleware
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var IControllerMethodReflector */
  protected $reflector;

  /**
   * @param ControllerMethodReflector $reflector
   * @param ConfigService $configService
   */
  public function __construct(IControllerMethodReflector $reflector,
                              ConfigService $configService) {
    $this->reflector = $reflector;
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * Check if the user is a sub-admin of the orchestra group
   * @param Controller $controller
   * @param string $methodName
   * @throws \Exception
   */
  public function beforeController($controller, $methodName) {
    if (!$this->reflector->hasAnnotation('NoGroupMemberRequired')) {
      if (!$this->configService->inGroup()) {
        throw new NotAdminException($this->l->t('Logged in user must be a member of the orchestra group'));
      }
    }
  }

  /**
   * Return 403 page in case of an exception
   * @param Controller $controller
   * @param string $methodName
   * @param \Exception $exception
   * @return TemplateResponse
   * @throws \Exception
   */
  public function afterException($controller, $methodName, \Exception $exception) {
    if ($exception instanceof NotAdminException) {
      $response = new TemplateResponse('core', '403', [], 'guest');
      $response->setStatus(Http::STATUS_FORBIDDEN);
      return $response;
    }
    throw $exception;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

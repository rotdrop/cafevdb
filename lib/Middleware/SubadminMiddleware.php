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
class SubadminMiddleware extends Middleware {
  /** @var ConfigService */
  protected $configService;

  /** @var IControllerMethodReflector */
  protected $reflector;

  /** @var IL10N */
  private $l;

  /**
   * @param ControllerMethodReflector $reflector
   * @param ConfigService $configService
   * @param IL10N $l
   */
  public function __construct(IControllerMethodReflector $reflector,
                              ConfigService $configService,
                              IL10N $l) {
    $this->reflector = $reflector;
    $this->configService = $configService;
    $this->l = $l;
  }

  /**
   * Check if the user is a sub-admin of the orchestra group
   * @param Controller $controller
   * @param string $methodName
   * @throws \Exception
   */
  public function beforeController($controller, $methodName) {
    if ($this->reflector->hasAnnotation('SubadminRequired')) {
      if (!$this->configService->isSubAdminOfGroup()) {
        throw new NotAdminException($this->l->t('Logged in user must be a subadmin of the orchestra group'));
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

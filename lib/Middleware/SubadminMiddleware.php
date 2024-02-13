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

namespace OCA\CAFEVDB\Middleware;

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OCP\AppFramework\Http;
use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;

use OCA\CAFEVDB\Http\TemplateResponse;
use OCA\CAFEVDB\Service\ConfigService;

/**
 * Verifies whether an user has at least subadmin rights.
 * To enforce use the `@SubadminRequired` annotation
 */
class SubadminMiddleware extends Middleware
{
  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IControllerMethodReflector $reflector,
    protected ConfigService $configService,
    private IL10N $l
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
    if ($this->reflector->hasAnnotation('SubadminRequired')) {
      if (!$this->configService->isSubAdminOfGroup()) {
        throw new NotAdminException($this->l->t('Logged in user must be a subadmin of the orchestra group'));
      }
    }
    if ($this->reflector->hasAnnotation('ServiceAccountRequired')) {
      if ($this->configService->getUserId() != $this->configService->getConfigValue(ConfigService::SHAREOWNER_KEY)) {
        throw new NotAdminException($this->l->t('Logged in user account must be the service-account of the orchester app'));
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Return 403 page in case of an exception
   */
  public function afterException($controller, $methodName, \Exception $exception)
  {
    if ($exception instanceof NotAdminException) {
      $response = new TemplateResponse('core', '403', [], 'guest');
      $response->setStatus(Http::STATUS_FORBIDDEN);
      return $response;
    }
    throw $exception;
  }
}

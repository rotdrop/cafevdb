<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024 Claus-Justus Heine
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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Exceptions;

/**
 * Deny all request while config-lock is active.
 */
class ConfigLockMiddleware extends Middleware
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IControllerMethodReflector $reflector,
    protected ConfigService $configService,
    protected IL10N $l,
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
    if ($this->reflector->hasAnnotation('IgnoreConfigLock')) {
      return;
    }
    if (!empty($this->configService->getConfigValue(ConfigService::CONFIG_LOCK_KEY))) {
      throw new Exceptions\ConfigLockedException;
    }
  }

  /**
   * {@inheritdoc}
   *
   * Return maintenance-mode response in case of an error.
   */
  public function afterException($controller, $methodName, \Exception $exception)
  {
    if ($exception instanceof Exceptions\ConfigLockedException) {
      $response = $this->templateResponse('update.user', [], self::RENDER_AS_GUEST, appName: 'core');
      $response->setStatus(Http::STATUS_LOCKED);
      return $response;
    }
    throw $exception;
  }
}

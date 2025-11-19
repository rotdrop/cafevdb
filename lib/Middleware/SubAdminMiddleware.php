<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine
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
use ReflectionMethod;

use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IL10N;
use OC\AppFramework\Middleware\Security\Exceptions\NotAdminException;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Attributes;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Settings\ConfigConstants;

/**
 * Verifies whether an user has at least sub-admin rights.
 * To enforce use the `@SubAdminRequired` annotation
 */
class SubAdminMiddleware extends Middleware
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\HasAnnotationOrAttributeTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
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
    if ($this->hasAnnotationOrAttribute($reflectionMethod, Attributes\SubAdminRequired::class)) {
      // $this->logInfo('Middleware attribute match');
      if (!$this->configService->isSubAdminOfGroup()) {
        throw new NotAdminException($this->l->t('Logged in user must be a sub-admin of the orchestra group'));
      }
    }
    if ($this->hasAnnotationOrAttribute($reflectionMethod, Attributes\ServiceAccountRequired::class)) {
      if ($this->configService->getUserId() != $this->configService->getConfigValue(ConfigConstants::SHAREOWNER_KEY)) {
        throw new NotAdminException($this->l->t('Logged in user account must be the service-account of the orchester app'));
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
    $response = $this->templateResponse('403', [], Constants::RENDER_AS_GUEST, appName: 'core');
    $response->setStatus(Http::STATUS_FORBIDDEN);
    return $response;
  }
}

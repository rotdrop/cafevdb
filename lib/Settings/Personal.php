<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\IUserSession;
use Psr\Log\LoggerInterface as ILogger;

use OCA\CAFEVDB\Service\AuthorizationService;

/**
 * Personal settings.
 *
 * @todo These contain tons of group-sub-admin settings. Perhaps rework with
 * admin settings delegation.
 */
class Personal implements ISettings
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const ERROR_TEMPLATE = "errorpage";

  /** {@inheritdoc} */
  public function __construct(
    private string $appName,
    private ?string $userId,
    private AuthorizationService $authorizationService,
    private IUserSession $userSession,
    protected ILogger $logger,
  ) {
  }

  /** {@inheritdoc} */
  public function getForm()
  {
    if (!$this->authorizationService->authorized($this->userId, AuthorizationService::PERMISSION_FRONTEND)) {
      return new TemplateResponse(
        $this->appName,
        self::ERROR_TEMPLATE,
        [
          'error' => 'notamember',
          'userId' => $this->userId,
        ], 'blank');
    }
    return \OC::$server->query(PersonalForm::class)->getForm();
  }

  /** {@inheritdoc} */
  public function getSection()
  {
    return $this->appName;
  }

  /** {@inheritdoc} */
  public function getPriority()
  {
    return 50;
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Exception;

use OCA\CAFEVDB\Http\TemplateResponse;
use OCA\CAFEVDB\Service\AuthorizationService;

/** Generate frontend HTML page with error information. */
class ErrorService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const ERROR_TEMPLATE = "errorpage";

  /** @var OrganizationalRolesService */
  private $rolesService;

  // phpcs:disabled Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ConfigService $configService,
    OrganizationalRolesService $rolesService,
  ) {
    $this->configService = $configService;
    $this->rolesService = $rolesService;
  }
  // phpcs:enable

  /**
   * @param Exception $e
   *
   * @param string $renderAs
   *
   * @return TemplateResponse
   */
  public function exceptionTemplate(Exception $e, string $renderAs = 'blank'):TemplateResponse
  {
    $admin = implode(',', array_map(
      fn($contact) => $contact['name'] . ' <' . $contact['email'] . '>',
      $this->rolesService->cloudAdminContact()
    ));

    return new TemplateResponse(
      $this->appName(),
      self::ERROR_TEMPLATE,
      [
        'userId' => $this->userId(),
        'error' => 'exception',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debug' => true,
        'admin' => $admin,
      ],
      $renderAs
    );
  }
}

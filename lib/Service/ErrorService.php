<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Http\TemplateResponse;

class ErrorService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const ERROR_TEMPLATE = "errorpage";

  /** @var OrganizationalRolesService */
  private $rolesService;

  public function __construct(
    ConfigService $configService
    , OrganizationalRolesService $rolesService) {
    $this->configService = $configService;
    $this->rolesService = $rolesService;
  }

  public function exceptionTemplate(\Exception $e, $renderAs = 'blank')
  {
    $admin = implode(',', array_map(
      function($contact) { return $contact['name'] . ' <' . $contact['email'] . '>'; },
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

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

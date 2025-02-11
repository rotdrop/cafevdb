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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use OCA\CAFEVDB\Service\AuthorizationService;

/**
 * Provide a navigationItem() method for page-renderers which need a current
 * project.
 */
trait FinanceModeNavigationItemTrait
{
  use ProjectModeNavigationItemTrait;

  /*** {@inheritdoc} */
  public static function navigationItem(?int $projectId = null, ?string $projectName = null):array
  {
    return array_merge(
      parent::navigationItem($projectId, $projectName), [
        'templateParameters' => [ 'projectId' => $projectId, 'projectName' =>  $projectName ],
        'permissions' => static::requiredPermissions(),
      ]);
  }

  /*** {@inheritdoc} */
  public static function requiredPermissions():int
  {
    return AuthorizationService::PERMISSION_FRONTEND|AuthorizationService::PERMISSION_FINANCE;
  }
}

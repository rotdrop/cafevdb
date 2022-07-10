<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Traits;

use OCP\IGroupManager;

trait CloudAdminTrait
{
  /**
   * Contact information for the overall admins.
   */
  protected function getCloudAdminContacts(IGroupManager $groupManager, bool $implode = false)
  {
    $adminGroup = $groupManager->get('admin');
    $adminUsers = $adminGroup->getUsers();
    $contacts = [];
    foreach ($adminUsers as $adminUser) {
      $contacts[] = [
        'name' => $adminUser->getDisplayName(),
        'email' => $adminUser->getEmailAddress(),
      ];
    }

    if ($implode) {
      $adminEmail = [];
      foreach ($contacts as $admin) {
        $adminEmail[] = empty($admin['name']) ? $admin['email'] : $admin['name'].' <'.$admin['email'].'>';
      }
      $contacts = implode(',', $adminEmail);
    }

    return $contacts;
  }
}

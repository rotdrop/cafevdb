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

use OCP\IAddressBook;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

trait ContactsTrait
{
  /**
   * @param array<int, IAddressBook> $addressBooks
   *
   * @return array<int, array> Flattened address-books
   */
  static protected function flattenAdressBooks(array $addressBooks):array
  {
    $result = [];
    /** @var IAddressBook $addressBook */
    foreach ($addressBooks as $addressBook) {
      $key = $addressBook->getKey();
      $result[$key] = [
        'displayName' => $addressBook->getDisplayName(),
        'key' => $key,
        'uri' => $addressBook->getUri(),
        'isShared' => $addressBook->isShared(),
        'isSystemAddressBook' => $addressBook->isSystemAddressBook(),
        'permissions' => $addressBook->getPermissions(),
      ];
    }
    return $result;
  }

  static protected function cardDataToEntity(array $cardData):Entities\Musician
  {
  }
}

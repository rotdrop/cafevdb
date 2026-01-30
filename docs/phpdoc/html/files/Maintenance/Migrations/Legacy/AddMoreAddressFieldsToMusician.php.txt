<?php
/**
 * Orchestra member, musicion and project management application.
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

namespace OCA\CAFEVDB\Maintenance\Migrations\Legacy;

/**
 * Add title field to musicians table.
 */
class AddMoreAddressFieldsToMusician extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS job_title VARCHAR(255) DEFAULT NULL",
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS organization VARCHAR(255) DEFAULT NULL",
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS address_book_uri VARCHAR(255) DEFAULT NULL",
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS po_box VARCHAR(128) DEFAULT NULL",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add more address fields to the musician entity for the sake of vCard synchronization and in order to support business contacts.');
  }
}

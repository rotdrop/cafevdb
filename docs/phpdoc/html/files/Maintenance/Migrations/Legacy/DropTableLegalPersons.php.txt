<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024, 2025 Claus-Justus Heine
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
 * Was a "stupid" idea, so drop it again ...
 */
class DropTableLegalPersons extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE Invoices DROP FOREIGN KEY IF EXISTS FK_93594DC372757D19",
      "ALTER TABLE Invoices DROP FOREIGN KEY IF EXISTS FK_93594DC33DA3F86F",
      "ALTER TABLE LegalPersons DROP FOREIGN KEY IF EXISTS FK_60D5184F9523AA8A",
      "DROP TABLE IF EXISTS LegalPersons",
      "ALTER TABLE Invoices DROP FOREIGN KEY IF EXISTS FK_93594DC372757D19",
      "ALTER TABLE Invoices DROP FOREIGN KEY IF EXISTS FK_93594DC33DA3F86F",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC372757D19 FOREIGN KEY IF NOT EXISTS (debitor_id) REFERENCES Musicians (id)",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC33DA3F86F FOREIGN KEY IF NOT EXISTS (originator_id) REFERENCES Musicians (id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Drop the legal persons table.');
  }
}

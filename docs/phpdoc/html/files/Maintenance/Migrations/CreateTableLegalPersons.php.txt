<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

/**
 * Remember the id of a mailing list.
 */
class CreateTableLegalPersons extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS LegalPersons (
  id INT AUTO_INCREMENT NOT NULL,
  musician_id INT DEFAULT NULL,
  contact_uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid_binary)',
  created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  deleted DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX UNIQ_60D5184F9523AA8A (musician_id),
  UNIQUE INDEX UNIQ_60D5184F9523AA8AF980D259 (musician_id, contact_uuid),
  PRIMARY KEY(id)
)",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC372757D19 FOREIGN KEY IF NOT EXISTS (debitor_id) REFERENCES LegalPersons (id)",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC33DA3F86F FOREIGN KEY IF NOT EXISTS (originator_id) REFERENCES LegalPersons (id)",
      "ALTER TABLE LegalPersons ADD CONSTRAINT FK_60D5184F9523AA8A FOREIGN KEY IF NOT EXISTS (musician_id) REFERENCES Musicians (id)",
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS legal_person_id INT DEFAULT NULL",
      "ALTER TABLE Musicians ADD CONSTRAINT FK_3CC48982CDB28416 FOREIGN KEY IF NOT EXISTS (legal_person_id) REFERENCES LegalPersons (id)",
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_3CC48982CDB28416 ON Musicians (legal_person_id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Provide a table modelling legal persons.');
  }
}

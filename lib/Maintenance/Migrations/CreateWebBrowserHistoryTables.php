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

namespace OCA\CAFEVDB\Maintenance\Migrations;

/**
 * Sightly over-engineered web-browser history storage. Perhaps one just
 * should store an encrypted blob ...
 */
class CreateWebBrowserHistoryTables extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS WebBrowserHistoryData
  (hash VARCHAR(64) NOT NULL, data LONGBLOB NOT NULL, PRIMARY KEY(hash))",
      "CREATE TABLE IF NOT EXISTS WebBrowserHistoryEntry
  (next VARCHAR(16) DEFAULT NULL, prev VARCHAR(16) DEFAULT NULL, state_id INT DEFAULT NULL, data_hash VARCHAR(64) NOT NULL, `key` VARCHAR(16) NOT NULL, UNIQUE INDEX UNIQ_DD9282C442F103C (next), UNIQUE INDEX UNIQ_DD9282C4BCE28855 (prev), INDEX IDX_DD9282C45D83CC1 (state_id), INDEX IDX_DD9282C46AF7A95A (data_hash), PRIMARY KEY(`key`))",
      "CREATE TABLE IF NOT EXISTS WebBrowserHistoryState
  (id INT AUTO_INCREMENT NOT NULL, pos_key VARCHAR(16) NOT NULL, user_id VARCHAR(256) NOT NULL, updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_5520CD4FD06B458A (pos_key), PRIMARY KEY(id))",
      "ALTER TABLE WebBrowserHistoryEntry
   ADD CONSTRAINT FK_DD9282C442F103C FOREIGN KEY IF NOT EXISTS (next) REFERENCES WebBrowserHistoryEntry (`key`)",
      "ALTER TABLE WebBrowserHistoryEntry
   ADD CONSTRAINT FK_DD9282C4BCE28855 FOREIGN KEY IF NOT EXISTS (prev) REFERENCES WebBrowserHistoryEntry (`key`)",
      "ALTER TABLE WebBrowserHistoryEntry
   ADD CONSTRAINT FK_DD9282C45D83CC1 FOREIGN KEY IF NOT EXISTS (state_id) REFERENCES WebBrowserHistoryState (id)",
      "ALTER TABLE WebBrowserHistoryEntry
   ADD CONSTRAINT FK_DD9282C46AF7A95A FOREIGN KEY IF NOT EXISTS (data_hash) REFERENCES WebBrowserHistoryData (hash)",
      "ALTER TABLE WebBrowserHistoryState
   ADD CONSTRAINT FK_5520CD4FD06B458A FOREIGN KEY IF NOT EXISTS (pos_key) REFERENCES WebBrowserHistoryEntry (`key`)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Create tables for storing the web-browser history between sessions.');
  }
}

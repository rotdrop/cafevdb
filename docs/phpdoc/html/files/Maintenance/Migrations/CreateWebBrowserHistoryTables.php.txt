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
      "CREATE TABLE IF NOT EXISTS
  WebBrowserHistoryData
  (
    hash CHAR(64) NOT NULL COLLATE `ascii_general_ci`,
    data LONGBLOB NOT NULL COMMENT 'JSON encrypted',
    PRIMARY KEY(hash)
  )",
      "CREATE TABLE IF NOT EXISTS
  WebBrowserHistoryEntries
  (
    state_id INT NOT NULL,
    `key` NUMERIC(16, 3) NOT NULL,
    path VARCHAR(32768) CHARACTER SET ascii NOT NULL COLLATE `ascii_bin`,
    data_hash CHAR(64) NOT NULL COLLATE `ascii_general_ci`,
    INDEX IDX_2059233F5D83CC1 (state_id),
    INDEX IDX_2059233F6AF7A95A (data_hash),
    PRIMARY KEY(state_id, `key`)
  )",
      "CREATE TABLE IF NOT EXISTS
  WebBrowserHistoryStates
  (
    id INT AUTO_INCREMENT NOT NULL,
    pos_state_id INT DEFAULT NULL,
    pos_key NUMERIC(16, 3) DEFAULT NULL,
    user_id VARCHAR(256) NOT NULL,
    created DATETIME(6) NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_FD38B3C74CDC76F1D06B458A (pos_state_id, pos_key),
    UNIQUE INDEX UNIQ_FD38B3C7A76ED395B23DB7B8 (user_id, created),
    PRIMARY KEY(id)
  )",
      "ALTER TABLE WebBrowserHistoryEntries
  ADD CONSTRAINT FK_2059233F5D83CC1
  FOREIGN KEY IF NOT EXISTS (state_id) REFERENCES WebBrowserHistoryStates (id)",
      "ALTER TABLE WebBrowserHistoryEntries
  ADD CONSTRAINT FK_2059233F6AF7A95A
  FOREIGN KEY IF NOT EXISTS (data_hash) REFERENCES WebBrowserHistoryData (hash)",
      "ALTER TABLE WebBrowserHistoryStates
  ADD CONSTRAINT FK_FD38B3C74CDC76F1D06B458A
  FOREIGN KEY IF NOT EXISTS (pos_state_id, pos_key) REFERENCES WebBrowserHistoryEntries(state_id, `key`)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Create tables for storing the web-browser history between sessions.');
  }
}

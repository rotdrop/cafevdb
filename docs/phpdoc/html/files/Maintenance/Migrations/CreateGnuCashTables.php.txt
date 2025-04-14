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
 * Generate an empty set of GnuCash tables in order to connect a GnuCash book
 * stored as SQL database to the finance part of the app. We do not populate
 * the tables here; that is done by a separate configuration service.
 */
class CreateGnuCashTables extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS GnuCashAccounts (
  guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  commodity_guid CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
  parent_guid CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
  name VARCHAR(2028) NOT NULL,
  account_type VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
  commodity_scu INT NOT NULL,
  non_std_scu INT NOT NULL,
  code VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
  description VARCHAR(2028) NOT NULL,
  hidden TINYINT(1) NOT NULL,
  placeholder TINYINT(1) NOT NULL,
  INDEX IDX_1C4A70F24F9CBEC7 (commodity_guid),
  INDEX IDX_1C4A70F2168CF906 (parent_guid),
  PRIMARY KEY(guid)
)",
      "CREATE TABLE IF NOT EXISTS GnuCashBooks (
  guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  root_account_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  root_template_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  UNIQUE INDEX UNIQ_A26D411FD96A93A7 (root_account_guid),
  UNIQUE INDEX UNIQ_A26D411FA501DD19 (root_template_guid),
  PRIMARY KEY(guid)
)",
      "CREATE TABLE IF NOT EXISTS GnuCashCommodities (
  guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  namespace VARCHAR(2024) NOT NULL COLLATE `ascii_general_ci`,
  mnemonic VARCHAR(2028) NOT NULL,
  fullname VARCHAR(2028) NOT NULL,
  cusip VARCHAR(2028) NOT NULL,
  fraction INT NOT NULL,
  quote_flag TINYINT(1) NOT NULL,
  quote_source VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
  quote_tz VARCHAR(2028) NOT NULL COLLATE `ascii_general_ci`,
  PRIMARY KEY(guid)
)",
      "CREATE TABLE IF NOT EXISTS GnuCashSlots (
  id INT AUTO_INCREMENT NOT NULL,
  obj_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  name VARCHAR(4096) NOT NULL,
  slot_type INT NOT NULL,
  int64_val INT DEFAULT NULL,
  string_val VARCHAR(4096) DEFAULT NULL,
  double_val DOUBLE PRECISION DEFAULT NULL,
  timespec_val DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  guid_val CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
  numeric_val_num INT DEFAULT NULL,
  numeric_val_denom INT DEFAULT NULL,
  gdate_val DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
  INDEX slots_guid_index (obj_guid),
  PRIMARY KEY(id)
)",
      "CREATE TABLE IF NOT EXISTS GnuCashSplits (
  guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  tx_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  account_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  memo VARCHAR(2028) NOT NULL,
  action VARCHAR(2028) NOT NULL,
  reconcile_state CHAR(1) NOT NULL COLLATE `ascii_general_ci`,
  reconcile_date DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  value_num INT NOT NULL,
  value_denom INT NOT NULL,
  quantity_num INT NOT NULL,
  quantity_denom INT NOT NULL,
  lot_guid CHAR(32) DEFAULT NULL COLLATE `ascii_general_ci`,
  INDEX splits_tx_guid_index (tx_guid),
  INDEX splits_account_guid_index (account_guid),
  PRIMARY KEY(guid)
)",
      "CREATE TABLE IF NOT EXISTS GnuCashTransactions (
  guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  currency_guid CHAR(32) NOT NULL COLLATE `ascii_general_ci`,
  num VARCHAR(2028) NOT NULL,
  post_date DATETIME(6) DEFAULT '1970-01-01 00:00:00.000000' NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  enter_date DATETIME(6) DEFAULT '1970-01-01 00:00:00.000000' NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  description VARCHAR(2028) DEFAULT NULL,
  INDEX IDX_403125FA1D88CC6 (currency_guid),
  INDEX tx_post_date_index (post_date),
  PRIMARY KEY(guid)
)",
      "ALTER TABLE GnuCashAccounts
  ADD CONSTRAINT FK_1C4A70F24F9CBEC7 FOREIGN KEY
    IF NOT EXISTS
    (commodity_guid) REFERENCES GnuCashCommodities (guid)",
      "ALTER TABLE GnuCashAccounts
  ADD CONSTRAINT FK_1C4A70F2168CF906 FOREIGN KEY
    IF NOT EXISTS
    (parent_guid) REFERENCES GnuCashAccounts (guid)",
      "ALTER TABLE GnuCashBooks
  ADD CONSTRAINT FK_A26D411FD96A93A7 FOREIGN KEY
    IF NOT EXISTS
    (root_account_guid) REFERENCES GnuCashAccounts (guid)",
      "ALTER TABLE GnuCashBooks
  ADD CONSTRAINT FK_A26D411FA501DD19 FOREIGN KEY
    IF NOT EXISTS
    (root_template_guid) REFERENCES GnuCashAccounts (guid)",
      "ALTER TABLE GnuCashSplits
   ADD CONSTRAINT FK_E2EE9395D252EC5E FOREIGN KEY
     IF NOT EXISTS
     (tx_guid) REFERENCES GnuCashTransactions (guid)",
      "ALTER TABLE GnuCashSplits
  ADD CONSTRAINT FK_E2EE9395A7FC4818 FOREIGN KEY
    IF NOT EXISTS
   (account_guid) REFERENCES GnuCashAccounts (guid)",
      "ALTER TABLE GnuCashTransactions
  ADD CONSTRAINT FK_403125FA1D88CC6 FOREIGN KEY
    IF NOT EXISTS
    (currency_guid) REFERENCES GnuCashCommodities (guid)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Create some relevant GnuCash tables.');
  }
}

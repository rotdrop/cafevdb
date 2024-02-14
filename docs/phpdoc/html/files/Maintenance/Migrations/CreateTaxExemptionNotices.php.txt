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

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Remember the id of a mailing list.
 */
class CreateTaxExemptionNotices extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS TaxExemptionNotices (
 id INT NOT NULL AUTO_INCREMENT,
 written_notice_id INT DEFAULT NULL,
 tax_type enum('corporate income tax','sales tax','VAT') DEFAULT 'corporate income tax' NOT NULL COMMENT 'enum(corporate income tax,sales tax,VAT)(DC2Type:EnumTaxType)',
 assessment_period_start INT NOT NULL,
 assessment_period_end INT NOT NULL,
 tax_office VARCHAR(256) NOT NULL,
 tax_number VARCHAR(256) NOT NULL,
 date_issued DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)',
 beneficiary_purpose VARCHAR(4096) NOT NULL,
 membership_fees_are_donations TINYINT(1) DEFAULT '0' NOT NULL,
 created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
 updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
 deleted DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
 UNIQUE INDEX UNIQ_6417EA3735D82D9 (written_notice_id),
 UNIQUE INDEX UNIQ_6417EA3905158D116BA0728A3C1F02B (tax_type, assessment_period_start, assessment_period_end),
 PRIMARY KEY(id)
)",
      "ALTER TABLE TaxExemptionNotices ADD CONSTRAINT FK_6417EA3735D82D9 FOREIGN KEY (written_notice_id) REFERENCES DatabaseStorageDirEntries (id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Provide a table for recording tax exemption notices.');
  }
}

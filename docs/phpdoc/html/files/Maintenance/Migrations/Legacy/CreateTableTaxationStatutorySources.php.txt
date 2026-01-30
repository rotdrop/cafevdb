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


use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

/**
 * Remember the id of a mailing list.
 */
class CreateTableTaxationStatutorySources extends AbstractMigration
{
  private const INITIAL_SOURCES = [
    [ 'type' => Types\EnumTaxType::SALES, 'law' => '§19 Abs. 1 UStG', 'hint' => 'Kleinunternehmerregelung' ],
    [ 'type' => Types\EnumTaxType::SALES, 'law' => '§4 Nr. 20a UStG', 'hint' => null ],
    [ 'type' => Types\EnumTaxType::CORPORATE_INCOME, 'law' => '§5 Abs. 1 Nr. 9 KStG', 'hint' => null ],
    [ 'type' => Types\EnumTaxType::TRADE, 'law' => '§3 Nr. 6 GewStG', 'hint' => null ],
  ];

  protected static $sql = [
  self::STRUCTURAL => [
    "CREATE TABLE IF NOT EXISTS TaxExemptionItems (
  tax_exemption_notice_id INT NOT NULL,
  taxation_statutory_source_id INT NOT NULL,
  INDEX IDX_9D0F193734E7630B (tax_exemption_notice_id),
  INDEX IDX_9D0F193766FAD11 (taxation_statutory_source_id),
  PRIMARY KEY (tax_exemption_notice_id, taxation_statutory_source_id)
)",
    "CREATE TABLE IF NOT EXISTS TaxationStatutorySources (
  id INT AUTO_INCREMENT NOT NULL,
  tax_type enum(
    'corporate income tax', 'sales tax', 'trade tax', 'VAT'
  ) DEFAULT 'corporate income tax' NOT NULL COMMENT 'enum(corporate income tax,sales tax,trade tax,VAT)(DC2Type:EnumTaxType)',
  rate DOUBLE PRECISION DEFAULT '0' NOT NULL,
  country CHAR(2) NOT NULL COLLATE `ascii_general_ci`,
  law VARCHAR(255) NOT NULL,
  hint VARCHAR(1024) DEFAULT NULL,
  created DATETIME (6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated DATETIME (6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  deleted DATETIME (6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  UNIQUE INDEX UNIQ_8F39BDDD905158D1C0B552F (tax_type, law),
  PRIMARY KEY (id)
)",
// ALTER TABLE TaxExemptionItems ADD CONSTRAINT FK_9D0F193734E7630B FOREIGN KEY (
//   tax_exemption_notice_id
// ) REFERENCES TaxExemptionNotices (id);
// ALTER TABLE TaxExemptionItems ADD CONSTRAINT FK_9D0F193766FAD11 FOREIGN KEY (
//   taxation_statutory_source_id
// ) REFERENCES TaxationStatutorySources (id) ON DELETE CASCADE;
    "DROP INDEX IF EXISTS UNIQ_6417EA3905158D116BA0728A3C1F02B ON TaxExemptionNotices",
// "ALTER TABLE TaxExemptionNotices DROP tax_type",
    "ALTER TABLE Invoices ADD COLUMN IF NOT EXISTS taxation_statutory_source_id INT DEFAULT NULL",
    "CREATE INDEX IF NOT EXISTS IDX_93594DC366FAD11 ON Invoices (taxation_statutory_source_id)",
//     "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC366FAD11 FOREIGN KEY IF NOT EXISTS (
//   taxation_statutory_source_id
// ) REFERENCES TaxationStatutorySources (id)",

    ],
    self::TRANSACTIONAL => [],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Provide a table for taxation statutory sources.');
  }

  /**
   * {@inheritdoc}
   *
   * Execute the SQL instructions defined in AbstractMigration::$sql
   */
  public function execute():bool
  {
     // make sure the basic sources exist
    foreach (self::INITIAL_SOURCES as $data) {
      $hint = $data['hint'] === null ? 'NULL' : "'{$data['hint']}'";
      self::$sql[self::TRANSACTIONAL][] = "INSERT IGNORE INTO TaxationStatutorySources
(tax_type, law, country, hint, updated, created)
VALUES ('{$data['type']}', '{$data['law']}', 'DE', $hint, NOW(), NOW())";
    }
    // populate the join table
    self::$sql[self::TRANSACTIONAL][] = "INSERT IGNORE INTO TaxExemptionItems
(tax_exemption_notice_id, taxation_statutory_source_id)
SELECT
    ten.id AS tax_exemption_notice_id,
    tss.id AS taxation_statutory_source_id
FROM (
  SELECT
    '" . Types\EnumTaxType::CORPORATE_INCOME . "' AS jc,
    t1.*
  FROM TaxExemptionNotices t1
  UNION
  SELECT
    '" . Types\EnumTaxType::TRADE . "' AS jc,
    t2.*
  FROM TaxExemptionNotices t2
) ten
LEFT JOIN TaxationStatutorySources tss
ON ten.jc = tss.tax_type";

    $this->logInfo('TRANSACTIONAL "' . print_r(self::$sql[self::TRANSACTIONAL], true) . '".');

    // The following is wrong but otherwise there would be errors. The
    // production version has no Invoices table anyway ...
    self::$sql[self::TRANSACTIONAL][] = "Update Invoices
  SET taxation_statutory_source_id = (SELECT tts.id FROM TaxationStatutorySources tts
  WHERE tts.tax_type = '" . self::INITIAL_SOURCES[0]['type'] . "' AND tts.law = '" . self::INITIAL_SOURCES[0]['law'] . "')";
    if (!parent::execute()) {
      return false;
    }
    self::$sql[self::TRANSACTIONAL] = [];
    self::$sql[self::STRUCTURAL] = [
      "ALTER TABLE TaxExemptionItems ADD CONSTRAINT
  FK_9D0F193734E7630B FOREIGN KEY IF NOT EXISTS (tax_exemption_notice_id) REFERENCES TaxExemptionNotices (id)",
      "ALTER TABLE TaxExemptionItems ADD CONSTRAINT
  FK_9D0F193766FAD11 FOREIGN KEY IF NOT EXISTS (taxation_statutory_source_id) REFERENCES TaxationStatutorySources (id) ON DELETE CASCADE",
      "ALTER TABLE Invoices CHANGE taxation_statutory_source_id taxation_statutory_source_id INT NOT NULL",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC366FAD11 FOREIGN KEY (taxation_statutory_source_id) REFERENCES TaxationStatutorySources (id)",
      "ALTER TABLE TaxExemptionNotices DROP tax_type",
    ];
    return parent::execute();
  }
}

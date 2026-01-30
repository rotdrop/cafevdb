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
 * Add to EnumTaxType and TaxationStatutorySource
 */
class UpdateTableTaxationStatutorySources extends AbstractMigration
{
  private const SOURCES = [
    [ 'type' => Types\EnumTaxType::INSURANCE, 'rate' => '0.19', 'law' => '§6 Abs. 1 VersStG', 'hint' => 'Versicherungssteuer' ],
  ];

  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE TaxationStatutorySources CHANGE
  tax_type
  tax_type enum('corporate income tax','sales tax','trade tax','VAT','insurance tax')
  DEFAULT 'corporate income tax' NOT NULL
  COMMENT 'enum(corporate income tax,sales tax,trade tax,VAT,insurance tax)(DC2Type:EnumTaxType)'",
    ],
    self::TRANSACTIONAL => [],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add insurance tax to taxation statutory sources.');
  }

  /**
   * {@inheritdoc}
   *
   * Execute the SQL instructions defined in AbstractMigration::$sql
   */
  public function execute():bool
  {
    foreach (self::SOURCES as $data) {
      $hint = $data['hint'] === null ? 'NULL' : "'{$data['hint']}'";
      self::$sql[self::TRANSACTIONAL][] = "INSERT IGNORE INTO TaxationStatutorySources
(tax_type, rate, law, country, hint, updated, created)
VALUES ('{$data['type']}', '{$data['rate']}', '{$data['law']}', 'DE', $hint, NOW(), NOW())";
    }
    return parent::execute();
  }
}

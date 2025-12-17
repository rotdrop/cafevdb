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
 * Fix deprecations.
 */
class FixTaxExemptionNotices extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE TaxExemptionItems DROP FOREIGN KEY IF EXISTS FK_9D0F193734E7630B',
      'ALTER TABLE TaxExemptionItems
  ADD CONSTRAINT FK_9D0F193734E7630B
  FOREIGN KEY IF NOT EXISTS (tax_exemption_notice_id)
  REFERENCES TaxExemptionNotices (id) ON DELETE CASCADE',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Fix side-effect of nullable deprecation on join column.');
  }
}

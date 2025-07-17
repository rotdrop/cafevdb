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
 * Do not use floats for monetary values and other exact fractions.
 */
class UseDecimalForExactFractions extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectParticipantFieldsData CHANGE deposit deposit NUMERIC(7, 2) DEFAULT NULL",
      "ALTER TABLE TaxationStatutorySources CHANGE rate rate NUMERIC(2, 2) DEFAULT '0.00' NOT NULL",
      "ALTER TABLE InsuranceRates CHANGE Rate rate NUMERIC(4, 4) NOT NULL COMMENT 'fraction, not percentage, excluding taxes'",
      "ALTER TABLE ProjectParticipantFieldsDataOptions CHANGE deposit deposit NUMERIC(7, 2) DEFAULT NULL",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Use exact decimals for monetary values and other exact fractions.');
  }
}

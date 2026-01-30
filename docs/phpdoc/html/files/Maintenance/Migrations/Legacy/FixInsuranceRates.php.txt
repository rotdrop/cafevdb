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

/**
 * Fix constraints.
 */
class FixInsuranceRates extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE InsuranceRates
   ADD CONSTRAINT FK_CB75C3526CC064FC_ONUPDATE_CASCADE
   FOREIGN KEY IF NOT EXISTS
     (broker_id) REFERENCES InsuranceBrokers (short_name)
   ON UPDATE CASCADE",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Fix explicitly set constraints on insurance rates table.');
  }
}

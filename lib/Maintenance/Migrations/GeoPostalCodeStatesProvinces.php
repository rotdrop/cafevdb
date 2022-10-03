<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * Fix for previous migration.
 *
 * @see SupportMultipleEmailAddresses
 */
class GeoPostalCodeStatesProvinces extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'CREATE TABLE IF NOT EXISTS GeoStatesProvinces (country_iso CHAR(2) NOT NULL COLLATE `ascii_general_ci`, code CHAR(3) NOT NULL COLLATE `ascii_general_ci`, target CHAR(2) NOT NULL COLLATE `ascii_general_ci`, l10n_name VARCHAR(1024) NOT NULL, INDEX IDX_40C5B1885A7049D0466F2FFC (country_iso, target), PRIMARY KEY(country_iso, code, target))',
      'ALTER TABLE GeoStatesProvinces ADD CONSTRAINT FK_40C5B1885A7049D0466F2FFC FOREIGN KEY IF NOT EXISTS (country_iso, target) REFERENCES GeoCountries (iso, target)',
      'ALTER TABLE GeoPostalCodes ADD COLUMN IF NOT EXISTS state_province CHAR(3) DEFAULT NULL COLLATE `ascii_general_ci`',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add optional two letter state code to postal code database entity.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

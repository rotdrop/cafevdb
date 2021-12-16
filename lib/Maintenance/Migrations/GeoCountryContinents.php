<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Replace a legacy one-table solution by a clean join-table.
 */
class GeoCountryContinents extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE GeoContinents CHANGE translation l10n_name VARCHAR(1024) NOT NULL',
      'ALTER TABLE GeoCountries ADD continent_code CHAR(2) DEFAULT NULL COLLATE `ascii_general_ci`, CHANGE data l10n_name VARCHAR(1024) NOT NULL',
      'ALTER TABLE GeoCountries ADD CONSTRAINT FK_7DF803716C569B466F2FFC FOREIGN KEY (continent_code, target) REFERENCES GeoContinents (code, target)',
      'CREATE INDEX IDX_7DF803716C569B466F2FFC ON GeoCountries (continent_code, target)',
    ],
    self::TRANSACTIONAL => [
      // Alter all columns
      'SET FOREIGN_KEY_CHECKS = 0',
      "UPDATE GeoCountries gc
LEFT JOIN GeoCountries gc2
ON gc.iso = gc2.iso AND gc2.target = '->'
SET gc.continent_code = gc2.l10n_name
WHERE NOT gc.target = '->'",
      "DELETE FROM GeoCountries WHERE target = '->'",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Replace the legacy country-continent solution by a simple join-table.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Remember the id of a mailing list.
 */
class SeparateStreetNumberFromStreet extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS street_number VARCHAR(32) DEFAULT NULL',
      'ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS address_supplement VARCHAR(128) DEFAULT NULL',
    ],
    self::TRANSACTIONAL => [
      // this somehow assumes that only old German addresses have to be
      // changed. However, it does not play a role for new installations, so
      // ...
      <<<'EOS'
UPDATE Musicians SET
  street_number = REGEXP_REPLACE(street, "^\\s*([\\w\\s\\d._:/-]+?)\\s+(\\d+.*)$", "\\2"),
  street = REGEXP_REPLACE(street, "^\\s*([\\w\\s\\d._:/-]+?)\\s+(\\d+.*)$", "\\1")
WHERE street REGEXP "^\\s*([\\w\\s\\d._:/-]+?)\\s+(\\d+.*)$"
EOS,
      // supplements cannot be extracted automatically
    ],
  ];

  // SQL check
  //
  // SELECT m.street, m.street_number, m2.street FROM `Musicians` m
  // INNER JOIN cafevdb_musicians_insurances_nocrypt_backup.Musicians m2
  // ON m.id = m2.id
  // WHERE NOT CONCAT(m.street, ' ', m.street_number) = m2.street;
  //
  // SQL restore
  // UPDATE Musicians m
  // INNER JOIN cafevdb_musicians_insurances_nocrypt_backup.Musicians m2
  // ON m.id = m2.id
  // SET m.street = m2.street, m.street_number = NULL;

  public function description():string
  {
    return $this->l->t('Store the street number in a field separate from the street name.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

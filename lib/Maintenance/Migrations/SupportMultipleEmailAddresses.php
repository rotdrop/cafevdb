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
 * Remember the id of a mailing list.
 */
class SupportMultipleEmailAddresses extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS MusicianEmailAddresses (address VARCHAR(254) NOT NULL COLLATE `ascii_general_ci`, musician_id INT NOT NULL, created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_13DF84F69523AA8A (musician_id), PRIMARY KEY(address, musician_id))",
      'ALTER TABLE MusicianEmailAddresses ADD CONSTRAINT FK_13DF84F69523AA8A FOREIGN KEY IF NOT EXISTS (musician_id) REFERENCES Musicians (id)',
      'ALTER TABLE Musicians CHANGE email email VARCHAR(254) NOT NULL COLLATE `ascii_general_ci`',
      // 'ALTER TABLE Musicians ADD CONSTRAINT FK_3CC48982BF396750E7927C74 FOREIGN KEY (id, email) REFERENCES MusicianEmailAddresses (musician_id, address)',
      'CREATE UNIQUE INDEX IF NOT EXISTS email_uniq ON Musicians (id, email)',
    ],
    self::TRANSACTIONAL => [
      // Before adding the final constraint we must first populate the  email-addresses table
      'INSERT IGNORE INTO MusicianEmailAddresses (address, musician_id, created, updated)
SELECT m.email, m.id, NOW(), NOW()
FROM Musicians m
WHERE m.email IS NOT NULL',
    ],
  ];

  public function description():string
  {
    return $this->l->t('Support multiple email addresses for each musician.');
  }

  public function execute():bool
  {
    if (!parent::execute()) {
      return false;
    }
    static::$sql[self::TRANSACTIONAL] = [];
    static::$sql[self::STRUCTURAL] = [
      'ALTER TABLE Musicians ADD CONSTRAINT FK_3CC48982BF396750E7927C74 FOREIGN KEY IF NOT EXISTS (id, email) REFERENCES MusicianEmailAddresses (musician_id, address)',
    ];
    return parent::execute();
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

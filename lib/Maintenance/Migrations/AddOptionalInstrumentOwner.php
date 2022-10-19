<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
class AddOptionalInstrumentOwner extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE InstrumentInsurances ADD COLUMN IF NOT EXISTS  instrument_owner_id INT DEFAULT NULL',
      'ALTER TABLE InstrumentInsurances ADD CONSTRAINT FK_B9BA7EFDF95C1F8 FOREIGN KEY IF NOT EXISTS (instrument_owner_id) REFERENCES Musicians (id)',
      'CREATE INDEX IF NOT EXISTS IDX_B9BA7EFDF95C1F8 ON InstrumentInsurances (instrument_owner_id)',
    ],
  ];

  public function description():string
  {
    return $this->l->t('Instrument insurances: optionally record an instrument owner different from the instrument holder.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

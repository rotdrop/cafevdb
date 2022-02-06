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

use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/**
 * Generate some needed procedures and functions. MySQL specific.
 */
class TimestampedAssociations extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE SepaBulkTransactions ADD COLUMN IF NOT EXISTS sepa_transaction_data_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE Musicians ADD COLUMN IF NOT EXISTS sepa_debit_mandates_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD COLUMN IF NOT EXISTS payments_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE ProjectParticipants ADD COLUMN IF NOT EXISTS participant_fields_data_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Add time-stamps for association fields in order to implement dir-mtime correcty for the cloud-file-system export.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

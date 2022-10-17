<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
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
use OCP\AppFramework\IAppContainer;

/**
 * Remember the id of a mailing list.
 */
class TimeStampProjectPayments extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE ProjectPayments
   ADD COLUMN IF NOT EXISTS created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
   ADD COLUMN IF NOT EXISTS updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
    ],
    self::TRANSACTIONAL => [
      'UPDATE
    ProjectPayments pp
LEFT JOIN CompositePayments cp ON
    pp.composite_payment_id = cp.id
SET
    pp.updated = cp.updated
WHERE
    pp.updated IS NULL AND cp.updated IS NOT NULL',
      'UPDATE
    ProjectPayments pp
LEFT JOIN CompositePayments cp ON
    pp.composite_payment_id = cp.id
SET
    pp.created = cp.created
WHERE
    pp.created IS NULL AND cp.created IS NOT NULL',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Add timestamps to project payments');
  }
}

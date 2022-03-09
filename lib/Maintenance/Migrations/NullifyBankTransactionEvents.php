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

/**
 * Make a couple of columns nullable.
 */
class NullifyBankTransactionEvents extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE SepaBulkTransactions
  CHANGE due_event_uri due_event_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  CHANGE due_event_uid due_event_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  CHANGE submission_event_uri submission_event_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  CHANGE submission_event_uid submission_event_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  CHANGE submission_task_uri submission_task_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  CHANGE submission_task_uid submission_task_uid VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object UID',
  CHANGE pre_notification_event_uri pre_notification_event_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI',
  CHANGE pre_notification_task_uri pre_notification_task_uri VARCHAR(256) DEFAULT NULL COMMENT 'Cloud Calendar Object URI'
",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Make calendar links nullable.');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

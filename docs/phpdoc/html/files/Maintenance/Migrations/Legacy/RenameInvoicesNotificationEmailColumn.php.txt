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
 * Clean up column name.
 */
class RenameInvoicesNotificationEmailColumn extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE Invoices DROP FOREIGN KEY IF EXISTS FK_93594DC3FD22F96C",
      "DROP INDEX IF EXISTS UNIQ_93594DC3FD22F96C ON Invoices",
      "ALTER TABLE Invoices CHANGE notification_email_id notification_message_id VARCHAR(256) CHARACTER SET ascii DEFAULT NULL COLLATE `ascii_bin`",
      "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_93594DC3A808B60B ON Invoices (notification_message_id)",
      "ALTER TABLE Invoices ADD CONSTRAINT FK_93594DC3A808B60B
  FOREIGN KEY IF NOT EXISTS (notification_message_id)
  REFERENCES SentEmails (message_id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Cleanup a join column name.');
  }
}

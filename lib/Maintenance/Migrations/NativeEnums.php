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

namespace OCA\CAFEVDB\Maintenance\Migrations;

/**
 * DBAL 4.0.0 has removed the DC2Type comments, account for that. This is the
 * second part after switching to native enums and the builtin ORM support for
 * them.
 */
class NativeEnums extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "ALTER TABLE SepaBulkTransactions
  CHANGE sepa_transaction sepa_transaction ENUM('debit_note', 'bank_transfer') NOT NULL",
      "ALTER TABLE Files
  CHANGE type type ENUM('generic', 'image', 'encrypted') NOT NULL",
      "ALTER TABLE DatabaseStorageDirEntries
  CHANGE type type ENUM('generic', 'folder', 'file') NOT NULL",
      "ALTER TABLE FileData
  CHANGE type type ENUM('generic', 'image', 'encrypted') NOT NULL",
      "ALTER TABLE Musicians
  CHANGE default_participation_status default_participation_status ENUM('associated', 'conductor', 'passive', 'regular', 'soloist', 'temporary')
    DEFAULT 'regular' NOT NULL,
  CHANGE gender gender ENUM('male', 'female', 'diverse')
    DEFAULT NULL",
      "ALTER TABLE Projects
  CHANGE type type ENUM('temporary', 'permanent', 'template')
    DEFAULT 'temporary' NOT NULL",
      "ALTER TABLE ProjectEvents
  CHANGE type type ENUM('VEVENT', 'VTODO', 'VJOURNAL', 'VCARD') NOT NULL",
      "ALTER TABLE ProjectParticipants
  CHANGE participation_status participation_status ENUM('associated', 'conductor', 'passive', 'regular', 'soloist', 'temporary')
    DEFAULT 'regular' NOT NULL",
      "ALTER TABLE ProjectParticipantFields
  CHANGE data_type data_type ENUM('boolean', 'cloud-file', 'cloud-folder', 'date', 'datetime', 'db-file', 'float', 'html', 'integer', 'liabilities', 'receivables', 'text')
    DEFAULT 'text' NOT NULL,
  CHANGE multiplicity multiplicity ENUM('simple', 'single', 'multiple', 'parallel', 'recurring', 'groupofpeople', 'groupsofpeople') NOT NULL,
  CHANGE participant_access participant_access ENUM('none', 'read', 'read-write')
    DEFAULT 'none' NOT NULL,
  CHANGE participation_context participation_context ENUM('associates', 'participants', 'unrestricted')
    DEFAULT 'unrestricted' NOT NULL",
      "ALTER TABLE TaxationStatutorySources
  CHANGE tax_type tax_type ENUM('corporate income tax', 'sales tax', 'trade tax', 'VAT', 'insurance tax')
    DEFAULT 'corporate income tax' NOT NULL",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Renove DBAL DC2Type comments part two.');
  }
}

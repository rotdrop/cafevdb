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

use OCA\CAFEVDB\Database\EntityManager;

/**
 * Support linking of multiple payments or receivables to one supporting document.
 */
class OneSupportingDocumentForMultiplePayments extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      'ALTER TABLE CompositePayments DROP INDEX IF EXISTS UNIQ_65D9920C2423759C, ADD INDEX IF NOT EXISTS IDX_65D9920C2423759C (supporting_document_id)',
      'ALTER TABLE ProjectParticipantFieldsData DROP INDEX IF EXISTS UNIQ_E1AAA1E92423759C, ADD INDEX IF NOT EXISTS IDX_E1AAA1E92423759C (supporting_document_id)',
      'ALTER TABLE project_balance_supporting_document_encrypted_file DROP INDEX IF EXISTS UNIQ_C2B8C544EC15E76C, ADD INDEX IF NOT EXISTS IDX_C2B8C544EC15E76C (encrypted_file_id)',
      'ALTER TABLE project_balance_supporting_document_encrypted_file DROP FOREIGN KEY IF EXISTS FK_C2B8C544EC15E76C',
      'ALTER TABLE project_balance_supporting_document_encrypted_file ADD CONSTRAINT FK_C2B8C544EC15E76C FOREIGN KEY IF NOT EXISTS (encrypted_file_id) REFERENCES Files (id) ON DELETE CASCADE',
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Support linking of multiple payments or receivables to one supporting document.');
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

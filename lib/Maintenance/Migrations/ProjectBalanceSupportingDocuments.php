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
class ProjectBalanceSupportingDocuments extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS ProjectBalanceSupportingDocuments (sequence INT NOT NULL, project_id INT NOT NULL, created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', deleted DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_41CACE26166D1F9C (project_id), PRIMARY KEY(project_id, sequence))",
      "CREATE TABLE IF NOT EXISTS project_balance_supporting_document_encrypted_file (project_id INT NOT NULL, sequence INT NOT NULL, encrypted_file_id INT NOT NULL, INDEX IDX_C2B8C544166D1F9C5286D72B (project_id, sequence), UNIQUE INDEX UNIQ_C2B8C544EC15E76C (encrypted_file_id), PRIMARY KEY(project_id, sequence, encrypted_file_id))",
      "ALTER TABLE ProjectBalanceSupportingDocuments ADD CONSTRAINT FK_41CACE26166D1F9C FOREIGN KEY IF NOT EXISTS (project_id) REFERENCES Projects (id)",
      "ALTER TABLE project_balance_supporting_document_encrypted_file ADD CONSTRAINT FK_C2B8C544166D1F9C5286D72B FOREIGN KEY IF NOT EXISTS (project_id, sequence) REFERENCES ProjectBalanceSupportingDocuments (project_id, sequence)",
      "ALTER TABLE project_balance_supporting_document_encrypted_file ADD CONSTRAINT FK_C2B8C544EC15E76C FOREIGN KEY IF NOT EXISTS (encrypted_file_id) REFERENCES Files (id)",
      "ALTER TABLE Projects ADD COLUMN IF NOT EXISTS financial_balance_supporting_documents_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
      "ALTER TABLE ProjectBalanceSupportingDocuments ADD COLUMN IF NOT EXISTS documents_changed DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
    ],
  ];

  public function description():string
  {
    return $this->l->t('Manage enumerated support documents for the financial project balance');
  }
};

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

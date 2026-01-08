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
 * Create the ProjectApplications.
 */
class CreateTableProjectApplications extends AbstractMigration
{
  protected static $sql = [
    self::STRUCTURAL => [
      "CREATE TABLE IF NOT EXISTS ProjectApplications (
  email VARCHAR(254) NOT NULL COLLATE `ascii_general_ci`,
  project_id INT NOT NULL,
  musician_id INT DEFAULT NULL,
  password_hash VARCHAR(254) DEFAULT NULL COLLATE `ascii_general_ci`,
  data JSON DEFAULT '{}' NOT NULL COMMENT '(DC2Type:json)',
  deleted DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  created DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  updated DATETIME(6) DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  INDEX IDX_5F0E8E19166D1F9C (project_id),
  INDEX IDX_5F0E8E199523AA8A (musician_id),
  PRIMARY KEY(project_id, email))",
      "ALTER TABLE ProjectApplications
  ADD CONSTRAINT FK_5F0E8E19166D1F9C FOREIGN KEY IF NOT EXISTS (project_id) REFERENCES Projects (id)",
      "ALTER TABLE ProjectApplications
  ADD CONSTRAINT FK_5F0E8E199523AA8A FOREIGN KEY IF NOT EXISTS (musician_id) REFERENCES Musicians (id)",
    ],
  ];

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t('Provide a table for project applications via a web-form.');
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

declare(strict_types=1);

namespace OCA\CAFEVDB\Maintenance\Migrations;

use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;
use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractStructuralMigration;

/**
 * Introduce a dummy column into file-data in order to silence an error
 * emitted by the schema tool of >= ORM v3.7.
 */
final class Version20261009150233 extends AbstractStructuralMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Add a new constant "true" column to the file-data table (technical reasons, no real change).');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE FileData ADD lazy_file_data_dummy TINYINT DEFAULT 1 NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (file_id, lazy_file_data_dummy)');
    $this->addSql('CREATE INDEX IDX_969FA96893CB796C ON FileData (file_id)');
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('DROP INDEX IDX_969FA96893CB796C ON FileData');
    $this->addSql('ALTER TABLE FileData DROP lazy_file_data_dummy, DROP PRIMARY KEY, ADD PRIMARY KEY (file_id)');
  }
}

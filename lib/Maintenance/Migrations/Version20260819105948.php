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
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260819105948 extends AbstractStructuralMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Part 3: replace the web-browser history entry key by the history stack position.');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries CHANGE position position INT UNSIGNED NOT NULL');
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries DROP PRIMARY KEY, ADD PRIMARY KEY (state_id, position)');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates DROP FOREIGN KEY `FK_FD38B3C74CDC76F1D06B458A`');
    $this->addSql('DROP INDEX IDX_FD38B3C74CDC76F1D06B458A ON WebBrowserHistoryStates');
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries DROP `key`');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates DROP pos_key');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates
   ADD CONSTRAINT FK_FD38B3C74CDC76F1F28AEC5
   FOREIGN KEY (pos_state_id, pos_position)
   REFERENCES WebBrowserHistoryEntries (state_id, position)');
    $this->addSql('CREATE INDEX IDX_FD38B3C74CDC76F1F28AEC5 ON WebBrowserHistoryStates (pos_state_id, pos_position)');
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries ADD `key` NUMERIC(16, 3) UNSIGNED DEFAULT NULL');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates ADD pos_key NUMERIC(16, 3) UNSIGNED DEFAULT NULL');
  }
}

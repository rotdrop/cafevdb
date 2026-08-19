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
final class Version20260819094146 extends AbstractStructuralMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Part 1: replace the web-browser history entry key by the history stack position.');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries ADD position INT UNSIGNED DEFAULT NULL');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates ADD pos_position INT UNSIGNED DEFAULT NULL');
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries DROP PRIMARY KEY, ADD PRIMARY KEY (state_id, `key`)');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates DROP FOREIGN KEY IF EXISTS FK_FD38B3C74CDC76F1F28AEC5');
    $this->addSql('DROP INDEX IF EXISTS IDX_FD38B3C74CDC76F1F28AEC5 ON WebBrowserHistoryStates');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates
   ADD CONSTRAINT `FK_FD38B3C74CDC76F1D06B458A`
   FOREIGN KEY IF NOT EXISTS (pos_state_id, pos_key)
   REFERENCES WebBrowserHistoryEntries (state_id, `key`)');
    $this->addSql('CREATE INDEX IF NOT EXISTS IDX_FD38B3C74CDC76F1D06B458A ON WebBrowserHistoryStates (pos_state_id, pos_key)');
    $this->addSql('ALTER TABLE WebBrowserHistoryEntries DROP IF EXISTS position');
    $this->addSql('ALTER TABLE WebBrowserHistoryStates DROP IF EXISTS pos_position');
  }
}

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

use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260819094422 extends AbstractTransactionalMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Part 2: replace the web-browser history entry key by the history stack position.');
  }

  /** {@inheritdoc} */
  public function preUp(Schema $schema): void
  {
    $this->addSql('UPDATE WebBrowserHistoryEntries SET position = `key`*1000');
    $this->addSql('UPDATE WebBrowserHistoryStates SET pos_position = pos_key*1000');
  }

  /** {@inheritdoc} */
  public function preDown(Schema $schema): void
  {
    $this->addSql('UPDATE WebBrowserHistoryEntries SET `key` = position / 1000');
    $this->addSql('UPDATE WebBrowserHistoryStates SET pos_key = pos_position / 1000');
  }
}

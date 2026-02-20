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
final class Version20260130130553 extends AbstractStructuralMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Add an association from the project to the project registratition calendar event.');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE Projects ADD registration_calendar_event_id INT DEFAULT NULL');
    $this->addSql('ALTER TABLE Projects ADD CONSTRAINT FK_A5E5D1F2CCE7523B FOREIGN KEY (registration_calendar_event_id) REFERENCES ProjectEvents (id)');
    $this->addSql('CREATE UNIQUE INDEX UNIQ_A5E5D1F2CCE7523B ON Projects (registration_calendar_event_id)');
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE Projects DROP FOREIGN KEY FK_A5E5D1F2CCE7523B');
    $this->addSql('DROP INDEX UNIQ_A5E5D1F2CCE7523B ON Projects');
    $this->addSql('ALTER TABLE Projects DROP registration_calendar_event_id');
  }
}

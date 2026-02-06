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
 * 03:14:07 UTC on January 19, 2038 is the time when 32 bits are no longer
 * enough to store the Unix epoch. Stumbled over thin the context of a yearly
 * repeating calendar event.
 */
final class Version20260206193722 extends AbstractStructuralMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('The recurrence-id of calendar event must 64bits to prevent integer overflow.');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE ProjectEvents CHANGE recurrence_id recurrence_id BIGINT DEFAULT 0 NOT NULL');
  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->addSql('ALTER TABLE ProjectEvents CHANGE recurrence_id recurrence_id INT DEFAULT 0 NOT NULL');
  }
}

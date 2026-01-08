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

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractMigration;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version19700101000002 extends AbstractMigration
{
  private const FAMILY_NAMES = [
    'strings',
    'string',
    'plucked',
    'wind',
    'wood',
    'brass',
    'percussion',
    'keyboard',
    'miscellaneous',
    'not an instrument',
  ];

  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('Add standard instrument families');
  }

  /** {@inheritdoc} */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs

  }

  /** {@inheritdoc} */
  public function down(Schema $schema): void
  {
    // this down() migration is auto-generated, please modify it to your needs

  }

  /** {@inheritdoc} */
  public function preUp(Schema $schema): void
  {
    foreach (self::FAMILY_NAMES as $family) {
      $family = new Entities\InstrumentFamily()
        ->setFamily($family)
        ;
      $this->entityManager->persist($family);
    }
    $this->entityManager->flush();
  }
}

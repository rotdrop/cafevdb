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

use RuntimeException;

use OCA\CAFEVDB\Database\Doctrine\Migrations\AbstractTransactionalMigration;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Schema\Schema;

/**
 * Destructive down of the recurrence-id -> bigint migration. This will error
 * out unless in testing-mode.
 */
final class Version20260207000624 extends AbstractTransactionalMigration
{
  /** {@inheritdoc} */
  public function getDescription(): string
  {
    return $this->l->t('This is a do-nothing migration which, however, must exist for technical reasons.');
  }

  /** {@inheritdoc} */
  public function postDown(Schema $schema): void
  {
    if ((\ROT_DROP_PHPUNIT ?? null) !== true) {
      throw new RuntimeException('This migration cannot be undone in production mode.');
    }
    $qb = $this->entityManager->createQueryBuilder();
    $qb->delete(Entities\ProjectEvent::class, 'pe')->where($qb->expr()->gt('pe.recurrenceId', 0x7fffffff));
    $qb->getQuery()->execute();
  }
}

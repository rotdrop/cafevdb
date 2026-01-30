<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use DateTimeInterface;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;

/** Database repository for CompositePayments entities. */
class CompositePaymentsRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Toolkit\Traits\FakeTranslationTrait;

  /**
   * Fetch the maximum due-date of the underlying receivables.
   *
   * @param int|array|Entities\CompositePayment $identifier
   *
   * @return DateTimeInterface
   *
   * @throws Exceptions\DatabaseMissingIdentifierException
   */
  public function getReceivablesDueDate(int|array|Entities\CompositePayment $identifier):DateTimeInterface
  {
    $id = null;
    if (filter_var($identifier, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
      $id = $identifier;
    } elseif (is_array[$identifier]) {
      $id = $identifier['id'] ?? null;
    } else {
      $id = $identifier->getId();
    }
    if ($id === null) {
      throw new Exceptions\DatabaseMissingIdentifierException(
        sprintf(self::t('The identifier is missing for a query to find an instance of "%1$s".'), $this->entityName),
        entityClassName: $this->entityName,
        incompleteIdentifier: $identifier,
      );
    }

    $qb = $this->createQueryBuilder('cp')
      ->select('GREATEST(ppf.dueDate) AS dueDate')
      ->leftJoin('cp.projectPayments', 'pp')
      ->leftJoin('pp.field', 'ppf')
      ->groupBy('cp')
      ->where('cp.id', ':id')
      ->setParameter('id', $id);
    return $qb->getQuery()->getSingleScalarResult();
  }
}

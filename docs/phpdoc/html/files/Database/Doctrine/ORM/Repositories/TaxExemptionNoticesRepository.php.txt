<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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
use DateTimeImmutable;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TaxExemptionNotice as Entity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumTaxType as TaxType;

/** Database repository for CompositePayments entities. */
class TaxExemptionNoticesRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\FindLikeTrait;

  private const ALIAS = 'ten';

  /**
   * Return the most recent valid tax exemption notice, or \null if no valid
   * notice can be found. Valid means that the notice has been issued at or
   * before $dateOfUsage und is still valid at $dateOfUsage. If there are more
   * thatn 1 candidate notice, then the most recent is returned.
   *
   * @param string|TaxType $taxType Specify the tax-type, defaults to
   * corporate income tax.
   *
   * @param null|DateTimeInterface $dateOfUsage
   *
   * @return null|Entity
   */
  public function getLatestValid(
    string|TaxType $taxType = TaxType::CORPORATE_INCOME,
    ?DateTimeInterface $dateOfUsage = null,
  ):null|Entity {
    if ($dateOfUsage === null) {
      $dateOfUsage = new DateTimeImmutable;
    }
    $qb = $this->getQueryBuilder(self::ALIAS);

    $qb->select(self::ALIAS)
      ->where($qb->expr()->eq(self::ALIAS . '.taxType', ':taxType'))
      ->andWhere($qb->expr()->le(self::ALIAS . '.dateIssued', ':dateOfUsage'))
      ->andWhere(
        $qb->expr()->orX(
          $qb->expr()->isNull(self::ALIAS . '.deleted'),
          $qb->expr()->gt(self::ALIAS . '.deleted', ':dateOfUsage')
        )
      )
      ->orderBy(self::ALIAS . '.assessmentPeriodEnd', 'DESC')
      ->addOrderBy(self::ALIAS . 'assessmentPeriodStart', 'DESC')
      ->setParameter('taxType', $taxType)
      ->setParameter('dateOfUsage', $dateOfUsage)
      ;

    // As we use the "deleted" property of SoftDeleteable we need to disable
    // the query-filter in order to be able to retrieve entries for past
    // dates -- if for whatever reason we want to do that ...
    $entityManager = $this->getEntityManager();
    $filters = $entityManager->getFilters();
    $softDeleteable = $filters->isEnabled(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    if ($softDeleteable) {
      $filters->disable(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    }

    $result = $qb->getQuery()->getOneOrNullResult();

    if ($softDeleteable) {
      $filters->enable(DecoratedEntityManager::SOFT_DELETEABLE_FILTER);
    }

    return $result;
  }
}

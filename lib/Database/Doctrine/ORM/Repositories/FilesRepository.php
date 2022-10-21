<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine
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
use OCA\CAFEVDB\Common\Util;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Mapping as ORM;

/** Entity-repository for file enitites. */
class FilesRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /**
   * @param array $criteria
   *
   * @param string $function
   *
   * @param string $field
   *
   * @return DateTimeInterface
   *
   * @todo This function is obsoleted by the FindLikeTrait and should be replaced by it.
   */
  protected function fetchAggregate(array $criteria, string $function, string $field):DateTimeInterface
  {
    $qb = $this->createQueryBuilder('f');
    $qb->select($function . '(' . 'f.' . $field . ')');
    if (!empty($criteria)) {
      $qb->groupBy('f.id');
      $andX = $qb->expr()->andX();
      foreach ($criteria as $key => &$value) {
        $value = str_replace('*', '%', $value);
        if (strpos($value, '%') !== false) {
          $andX->add($qb->expr()->like('m'.'.'.$key, ':'.$key));
        } else {
          $andX->add($qb->expr()->eq('m'.'.'.$key, ':'.$key));
        }
      }
      $qb->where($andX);
      foreach ($criteria as $key => $value) {
        $qb->setParameter($key, $value);
      }
    }
    $date = $qb->getQuery()->getSingleScalarResult();
    if (empty($date)) {
      return (new DateTimeImmutable)->setTimestamp(0);
    }
    return self::convertToDateTime($date);
  }

  /**
   * @param array $criteria
   *
   * @return DateTimeInterface
   */
  public function fetchLatestModifiedTime(array $criteria = []):DateTimeInterface
  {
    return $this->fetchAggregate($criteria, 'MAX', 'updated');
  }

  /**
   * @param array $criteria
   *
   * @return DateTimeInterface
   */
  public function fetchLatestCreatedTime(array $criteria = []):DateTimeInterface
  {
    return $this->fetchAggregate($criteria, 'MAX', 'created');
  }
}

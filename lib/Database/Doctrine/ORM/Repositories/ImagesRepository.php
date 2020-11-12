<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library se Doctrine\ORM\Tools\Setup;is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

use Doctrine\ORM\EntityRepository;
use Doctrine\DBAL\Logging\DebugStack;

class ImagesRepository extends EntityRepository
{
  use \OCA\CAFEVDB\Database\Doctrine\ORM\Traits\LogTrait;

  /**
   * Find images for the given "using" entity class.
   *
   * @param string $joinTableEntity Full featured or partial class name of
   * the join table linking to the image table.
   *
   * @param int $ownerId Entity id of the "using" table/entity.
   *
   * @param int $limit Limit number of results (default: no limit)
   *
   * @return Entities\Image[]
   */
  public function findForEntity(string $joinTableEntity, int $ownerId, int $limit = -1): array
  {
    $logger = new DebugStack();
    $this->getEntityManager()->getConfiguration()->setSQLLogger($logger);

    $joinTableEntity = $this->getJoinTableCompletionEntity($joinTableEntity);
    $qb = $this->getEntityManager()->createQueryBuilder();
    $qb = $qb->select('jt.imageId')
             ->from($joinTableEntity, 'jt')
             ->where('jt.ownerId = :ownerId')
             ->setParameter('ownerId', $ownerId);

    if ($limit > 0) {
      $qb = $qb->setMaxResults($limit);
    }

    $imageIds = $qb->getQuery()
                   ->getResult('COLUMN_HYDRATOR');

    $qb = $this->createQueryBuilder('im');
    $images = $qb->select('im')
                 ->where($qb->expr()->in('im.imageId', $imageIds))
                 ->getQuery()
                 ->getResult();

    $this->log(print_r($logger->queries, true));

    return $images;
  }

  /**
   * Find the first or only image for the given using entity.
   *
   * @copydoc findForEntity
   *
   * @return Entities\Image
   */
  public function findOneForEntity(string $entityClass, int $ownerId):Image {
    return $this->findForEntity($entityClass, $ownerId, 1)[0];
  }


  private function getJoinTableCompletionEntity($joinTableEntity)
  {
    $backSlashPos = strrpos($joinTableEntity, '\\');
    if ($backSlashPos === false) {
      // compute class prefix
      $imageEntityClass = $this->getEntityName();
      $backSlashPos = strrpos($imageEntityClass, '\\');
      $joinTableEntity = substr_replace($imageEntityClass, $joinTableEntity, $backSlashPos+1);
    }
    $this->log("entity: ".$joinTableEntity);
    return $joinTableEntity;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

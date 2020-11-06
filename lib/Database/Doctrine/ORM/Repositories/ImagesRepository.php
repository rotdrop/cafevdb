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

class ImagesRepository extends EntityRepository
{
  /**
   * Find images for the given "using" entity class.
   *
   * @param string $joinTableEntity Full featured or partial class name of
   * the join table linking to the image table.
   *
   * @param int $ownerId Entity id of the "using" table/entity.
   *
   * @return Entities\Image[]
   */
  public function findForEntity(string $joinTableEntity, int $ownerId): array
  {
    $joinTableEntity = $this->completeJoinTableEntity($joinTableEntity);
    $qb = $this->getEntityManager()->createQueryBuilder();
    $imageIds = $qb->select('jt.image_Id')
                   ->from($joinTableEntity, 'jt')
                   ->where('owner_id = :ownerId')
                   ->setParameter('ownerId', $ownerId)
                   ->getQuery()
                   ->getResult('COLUMN_HYDRATOR');

    $qb = $this->createQueryBuilder('im');
    $qb->select('im')
       ->where($qb->expr()->in('image_id', $imageIds))
       ->getQuery()
       ->getResult();
  }

  /**
   * Find the first or only image for the given using entity.
   *
   * @copydoc findForEntity
   *
   * @return Entities\Image
   */
  public function findOneForEntity(string $entityClass, int $ownerId):Image {
  }


  private function completeJoinTableEntity($joinTableEntity)
  {
    $backSlashPos = strrpos($joinTableEntity, '\\');
    if ($backSlachPos === false) {
      // compute class prefix
      $imageEntityClass = $this->getEntityName();
      $backSlashPos = strrpos($imageEntityClass, '\\');
      $joinTableEntity = substr_replace($imageEntityClass, $backSlashPos+1, $joinTableEntity);
    }
    return $joinTableEntity;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

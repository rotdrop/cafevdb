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
use Doctrine\ORM\Mapping as ORM;

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

    $joinTableEntity = $this->resolveJoinTableEntity($joinTableEntity);
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

    if (empty($imageIds)) {
      // no image found is just ok.
      return [];
    }

    $qb = $this->createQueryBuilder('im');
    $images = $qb->select('im')
                 ->where($qb->expr()->in('im.id', $imageIds))
                 ->getQuery()
                 ->getResult();

    self::log(print_r($logger->queries, true));
    $this->getEntityManager()->getConfiguration()->setSQLLogger(null);

    return $images;
  }

  /**
   * Find the first or only image for the given using entity.
   *
   * @copydoc findForEntity
   *
   * @return null|Entities\Image
   */
  public function findOneForEntity(string $entityClass, int $ownerId) {
    $images = $this->findForEntity($entityClass, $ownerId, 1);
    return empty($images) ? null : $images[0];
  }

  /**
   * Persist the given image for the given owner.  Depending on the
   * association type between the owner and the join table
   * (i.e. OneToOne or OneToMany) the image will replace an existing
   * image or just added the collection of images.
   *
   * @param string Possibly partial join-table entity class.
   *
   *
   */
  public function persistForEntity(string $joinTableEntity, int $ownerId, \OCP\Image $image):Entities\Image
  {
    $entityManager = $this->getEntityManager();

    $logger = new DebugStack();
    $entityManager->getConfiguration()->setSQLLogger($logger);

    // Get full class name
    $joinTableEntityClass = $this->resolveJoinTableEntity($joinTableEntity);

    // Get meta-data of owner mapping
    $mapping = $entityManager->getClassMetadata($joinTableEntityClass)->getAssociationMapping('owner');
    if (empty($mapping)) {
      throw new \Exception("Unable to read owner mapping meta-data");
    }

    $ownerEntityClass = $mapping['targetEntity'];
    $uniqueImage = $mapping['type'] == ORM\ClassMetadataInfo::ONE_TO_ONE;

    // data entity
    $imageData = $image->data();
    $dbImageData = Entities\ImageData::create()
                 ->setData($imageData, 'binary');

    // image entity
    $dbImage = Entities\Image::create()
             ->setWidth($image->width())
             ->setHeight($image->height())
             ->setMimeType($image->mimeType())
             ->setMd5(md5($imageData))
             ->setImageData($dbImageData);
    $dbImageData->setImage($dbImage);

    if ($uniqueImage) {
      $joinTableRepository = $entityManager->getRepository($joinTableEntityClass);
      $joinTableEntity = $joinTableRepository->findOneBy(['ownerId' => $ownerId]);
    }
    if (empty($joinTableEntity)) {
      // owner reference with just the id
      $owner = $entityManager->getReference($ownerEntityClass, [ 'id' => $ownerId ]);
      $joinTableEntity = $joinTableEntityClass::create()->setOwner($owner);
      //self::log("OwnerEntityId: ".$owner->getId());
      $entityManager->persist($joinTableEntity); // why is this necessary?
    }
    $joinTableEntity->setImage($dbImage);
    $joinTableEntity = $entityManager->merge($joinTableEntity);

    // flush in order to get the last insert id
    $entityManager->flush();

    $dbImage = $joinTableEntity->getImage();
    $imageId = $dbImage->getId();

    self::log("Stored image with id ".$imageId." mime ".$image->mimeType());

    self::log(print_r($logger->queries, true));
    $entityManager->getConfiguration()->setSQLLogger(null);

    return $dbImage;
  }

  /**
   * Complete a possibly incomplete join-table entity class name by
   * looking the parent namespaces of this class.
   *
   * @param string $joinTableEntity
   *
   * @return string Possibly completed class-name.
   */
  public function joinTableClass(string $joinTableEntity):string
  {
    return $this->resolveJoinTableEntity($joinTableEntity);
  }

  private function resolveJoinTableEntity(string $joinTableEntity):string
  {
    $backSlashPos = strrpos($joinTableEntity, '\\');
    if ($backSlashPos === false) {
      // compute class prefix
      $imageEntityClass = $this->getEntityName();
      $backSlashPos = strrpos($imageEntityClass, '\\');
      $joinTableEntity = substr_replace($imageEntityClass, $joinTableEntity, $backSlashPos+1);
    }
    //$this->log("entity: ".$joinTableEntity);
    return $joinTableEntity;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

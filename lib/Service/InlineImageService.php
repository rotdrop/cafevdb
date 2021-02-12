<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
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

namespace OCA\CAFEVDB\Service;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class InlineImageService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const TABLE = 'ImageData';
  const IMAGE_DATA = 1;
  const IMAGE_META_DATA = 2;
  const IMAGE_DATA_MASK = 3;

  /** @var string Current data-base table. */
  protected $itemTable;

  /** @var string Current image-data. */
  protected $imageData;

  /**
   * @var int
   *
   * Current image id, i.e. id of row in self::$itemTable.
   */
  protected $itemId;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;

    $this->itemTable = $table;
    $this->imageData = null;
    $this->itemId = -1;
  }

  /**
   * Fetch an inline image from the data-base.
   *
   * @param int $id Id of the image in the data-base table.
   *
   * @return string Inline image suitable for an HTML image tag.
   */
  public function fetch(int $id, $fieldSelector = self::IMAGE_DATA_MASK): string {

    $this->imageData = null;
    $this->itemId = -1;

    if (!($fieldSelector & self::IMAGE_DATA_MASK)) {
      return null;
    }

    $fields = [];
    if ($fieldSelector & self::IMAGE_META_DATA) {
      $fields[] = 'id.mime_type';
      $fields[] = 'id.md5';
    }
    if ($fields & self::IMAGE_DATA) {
      $fields[] = 'id.data';
    }

    $query = $this->queryBuilder()
                  ->select($fields)
                  ->from(Entities\ImageData::class, 'id')
                  ->getQuery()
                  ->where($this->expr->eq('item_id', ':itemId'))
                  ->andWhere($this->expr->eq('item_table', ':itemTable'))
                  ->setParameter('itemId', $itemId)
                  ->setParameter('itemTable', $itemTable);
    $result = $query->execute();
    if (!empty(result) && count($result) == 1) {
      $this->imageData = $result[0];
      $this->itemId = $itemId;
    }

    return $this->imageData;
  }

  /**
   * @return string Image file name for placeholder image.
   */
  public function placeHolder()
  {
      return strtolower($this->itemTable).'-placeholder.png';
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

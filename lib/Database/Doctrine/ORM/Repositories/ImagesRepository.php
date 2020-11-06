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
   * @param string $entityClass Full featured or partial class name of
   * the entity we are searching images for.
   *
   * @param int $entityId Entity id of the "using" table/entity.
   *
   * @return Entities\Image[]
   */
  public function findForEntity(string $entityClass, int $entityId): array {
  }

  /**
   * Find the first or only image for the given using entity.
   *
   * @copydoc findForEntity
   *
   * @return Entities\Image
   */
  public function findOneForEntity(string $entityClass, int $entityId):Image {
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

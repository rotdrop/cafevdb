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

use Doctrine\ORM\Query;
use Doctrine\ORM\EntityRepository;

class ProjectExtraFieldsRepository extends EntityRepository
{
  const ALIAS = 'pexf';

  /**
   * Disable the given field by settings its "disable" flag.
   *
   * @param mixed $fieldOrId The field or fieldId to disable.
   *
   * @param bool $disable Whether to enable or disable
   */
  public function disable($fieldOrId, bool $disable = true)
  {
    $entityManager = $this->getEntityManager();
    if ($fieldOrId instanceof Entities\Field) {
      $field = $fieldOrId;
      $field->setDisabled($disabled);
      $getEntityManager()->flush();
    } else {
      $fieldId = $fieldOrId;
      $qb = $entitiyManager->createQueryBuilder()
                           ->update($this->getEntityName(), self::ALIAS)
                           ->set(self::ALIAS.'.disabled', true)
                           ->where(self::ALIAS.'.id = :fieldId')
                           ->setParameter('fieldId', $fieldId);
      $qb->getQuery()->execute();
    }
  }

  public function enable(bool $enable = true)
  {
    return $this->disabled(!$enable);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

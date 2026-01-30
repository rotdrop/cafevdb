<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2025, 2026 Claus-Justus Heine
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

use UnexpectedValueException;

use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Wrapped\Doctrine\ORM\EntityManagerInterface;
use OCA\CAFEVDB\Database\EntityManager as EntityManagerDecorator;
use OCA\CAFEVDB\Database\Doctrine\ORM\Mapping\ClassMetadataDecorator;
use OCA\CAFEVDB\Wrapped\Doctrine\Persistence\Mapping\ClassMetadata as ClassMetadataInterface;

/** Base class for all of our repositories. */
class EntityRepository extends \OCA\CAFEVDB\Toolkit\Doctrine\ORM\EntityRepository
{
  /** {@inheritdoc} */
  public function __construct(
    protected EntityManagerDecorator $entityManagerDecorator,
    protected ClassMetadataInterface $classMetaData,
    protected ?IAppContainer $appContainer = null,
  ) {
    if (!($classMetaData instanceof ClassMetadataDecorator)) {
      throw new UnexpectedValueException('Class-meta-data should be an instance of "' . ClassMetadataDecorator::class . '", but is an instance of "' . get_class($classMetaData) . '".');
    }
    $classMetaData = $classMetaData->getWrappedObject();
    parent::__construct($entityManagerDecorator, $classMetaData);
  }

  /**
   * Public export of parent function.
   *
   * @return EntityManagerInterface
   */
  public function getEntityManager():EntityManagerDecorator
  {
    return parent::getEntityManager();
  }
}

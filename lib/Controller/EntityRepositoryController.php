<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Attributes;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntitySerializer;

/** Export entities to the frontend. */
class EntityRepositoryController extends OCSController
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    private EntityManager $entityManager,
    private EntitySerializer $entitySerializer,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * Parameters are subemitted via query-string, except for the entity name.
   *
   * @param string $entityName
   *
   * @param string $findBy Base64 encode array of search criteria as
   * understood by
   * \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\EntityRepository::findBy).
   *
   * @param null|int $limit
   *
   * @param int $offset
   *
   * @param int $depth
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\ApiRoute(
    verb: 'GET',
    url: '/v1/entities/{entityName}/{findBy}',
    defaults: ['depths' => 2],
  )]
  public function getEntities(
    string $entityName,
    string $findBy,
    ?int $limit = null,
    int $offset = 0,
    int $depth = 2,
  ): JSONResponse {
    $criteria = json_decode($findBy, associative: true);
    $repository = $this->entityManager->getRepository($entityName);
    $entities = $repository->findBy($criteria, limit: $limit, offset: $offset);
    foreach ($entities as $entity) {
      $this->entitySerializer->addEntity($entity, $depth);
    }
    return $this->entitySerializer->export();
  }
}

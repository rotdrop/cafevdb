<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2024-2026 Claus-Justus Heine
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
/**
 * @file Access the data-base records of musicians. Return flattened DB entities.
 */

namespace OCA\CAFEVDB\Controller;

use ReflectionClass;

use OCP\AppFramework\OCSController;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

use OCA\CAFEVDB\Database\Constants as DBConstants;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Util\EntitySerializer;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Common\Uuid;

/**
 * Make the stored personal data accessible for the web-interface. This is
 * meant for newer parts of the web-interface in contrast to the legacy PME
 * stuff.
 */
class MusiciansController extends OCSController
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    private EntitySerializer $entitySerializer,
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    protected IL10N $l,
  ) {
    parent::__construct($appName, $request);
  }
  // phpcs:enable

  /**
   * Search by user-id and names. Pattern may contain wildcards (* and %).
   *
   * @param string $pattern
   *
   * @param null|int $limit
   *
   * @param null|int $offset
   *
   * @param null|string $projectName
   *
   * @param null|int $projectId
   *
   * @param array $ids
   *
   * @param string $scope
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\ApiRoute(verb: 'GET', url: '/musicians/search/{pattern}', defaults: ['pattern' => ''])]
  public function search(
    string $pattern,
    ?int $limit = null,
    ?int $offset = null,
    ?string $projectName = null,
    ?int $projectId = null,
    array $ids = [],
    string $scope = EnumMusiciansSearchScope::MUSICIANS->value,
  ): DataResponse {

    $scope = EnumMusiciansSearchScope::get($scope);

    switch ($scope) {
      case EnumMusiciansSearchScope::ADDRESSBOOK:
      case EnumMusiciansSearchScope::CLOUD_USERS:
        return self::grumble($this->l->t('Picking users from the cloud or the address-books is not yet supported, sorry.'));
      case EnumMusiciansSearchScope::EXECUTIVE_BOARD:
        $projectId = $this->getExecutiveBoardProjectId();
        break;
      case EnumMusiciansSearchScope::CLUB_MEMBERS:
        $projectId = $this->getClubMembersProjectId();
        break;
      case EnumMusiciansSearchScope::MUSICIANS:
        // just go
        break;
    }

    if ($projectName !== null && $projectId === null) {
      // our findLikeTrait cannot iterate to projectParticipation.project.name
      $project = $this->getDatabaseRepository(Entities\Project::class)->findOneBy([ 'name' => $projectName ]);
      $project = $project->getId();
    } else {
      $project = $projectId;
    }

    if (empty($pattern)) {
      $criteria = [];
    } else {
      $pattern = str_replace('*', '%', $pattern);

      if (strpos($pattern, '%') === false) {
        if ($pattern[0] != '^') {
          $pattern = '%' . $pattern;
        }
        if (substr($pattern, -1) != '$') {
          $pattern = $pattern . '%';
        }
      }

      // Attention: userIdSlug can only be compared against ASCII, so a
      // transliteration is necessary here.

      $criteria = [
        DBConstants::QUERY_OPTIONS_KEY => [ DBConstants::QUERY_OPTION_WILDCARDS => true ],
        '(|surName' => $pattern,
        'firstName' => $pattern,
        'displayName' => $pattern,
        'nickName' => $pattern,
        'userIdSlug' => $this->transliterate($pattern),
        'organization' => $pattern,
        'email' => $pattern,
        ')' => true,
      ];
    }

    if ($project !== null) {
      $criteria[] = [ 'projectParticipation.project' => $project ];
    }

    $repository = $this->getDatabaseRepository(Entities\Musician::class);

    $musicians = $repository->findBy($criteria, [
      'surName' => 'ASC',
      'firstName' => 'ASC'
    ], $limit, $offset);

    if (count($ids) > 0) {
      $criteria = [ 'id' => $ids ];
      if ($project !== null) {
        $criteria['projectParticipation.project'] = $project;
      }
      $byIdMusicians = $repository->findBy($criteria);
      $musicians = array_merge($musicians, $byIdMusicians);
    }

    $this->entitySerializer->reset();
    $entityNameSpace = new ReflectionClass(Entities\Musician::class)->getNamespaceName();
    $this->entitySerializer->setCommonPrefix($entityNameSpace);
    /** @var Entities\Musician $musician */
    foreach ($musicians as $musician) {
      $this->entitySerializer->addEntity($musician, 0);
    }
    return new DataResponse($this->entitySerializer->export());
  }
}

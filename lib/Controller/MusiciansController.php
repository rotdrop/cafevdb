<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * @file Handle various requests associated with asymmetric encryption
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;

use OCA\CAFEVDB\Common\Uuid;

/**
 * Make the stored personal data accessible for the web-interface. This is
 * meant for newer parts of the web-interface in contrast to the legacy PME
 * stuff.
 */
class MusiciansController extends Controller
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\FlattenEntityTrait;

  public const SCOPE_MUSICIANS = 'musicians';
  public const SCOPE_CLUB_MEMBERS = 'club-members';
  public const SCOPE_EXECUTIVE_BOARD = 'executive-board';
  public const SCOPE_CLOUD_USERS = 'cloud-users';
  public const SCOPE_ADDRESSBOOK = 'addressbook';

  /** @var Repositories\MusiciansRepository */
  private $musiciansRepository;

  /** @var array */
  private $countryNames;

  public function __construct(
    string $appName
    , IRequest $request
    , $userId
    , IL10N $l10n
    , ILogger $logger
    , EntityManager $entityManager
    , ConfigService $configService
  ) {
    parent::__construct($appName, $request);
    $this->l = $l10n;
    $this->logger = $logger;
    $this->entityManager = $entityManager;
    $this->musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);
    $this->configService = $configService;
    $this->countryNames = $this->localeCountryNames();
  }

  /**
   * @NoAdminRequired
   *
   * Get all the data of the given musician. This mess removes "circular"
   * associations as we are really only interested into the data for this
   * single person.
   *
   * @param int $musicianId
   *
   * @return DataResponse
   */
  public function get(int $musicianId):Response
  {
    $musician = $this->musiciansRepository->find($musicianId);

    $musicianData = $this->getFlatMusician($musician);

    return self::dataResponse($musicianData);
  }

  /**
   * @NoAdminRequired
   *
   * Search by user-id and names. Pattern may contain wildcards (* and %).
   */
  public function search(string $pattern, ?int $limit = null, ?int $offset = null, ?string $projectName = null, ?int $projectId = null, array $ids = [], string $scope = self::SCOPE_MUSICIANS):Response
  {

    switch ($scope) {
      case self::SCOPE_ADDRESSBOOK:
      case self::SCOPE_CLOUD_USERS:
        return self::grumble($this->l->t('Picking users from the cloud or the address-books is not yet supported, sorry.'));
      case self::SCOPE_EXECUTIVE_BOARD:
        $projectId = $this->getExecutiveBoardProjectId();
        break;
      case self::SCOPE_CLUB_MEMBERS:
        $projectId = $this->getClubMembersProjectId();
        break;
      case self::SCOPE_MUSICIANS:
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

      $criteria = [
        '(|surName' => $pattern,
        'firstName' => $pattern,
        'displayName' => $pattern,
        'nickName' => $pattern,
        'userIdSlug' => $pattern,
      ];
      $criteria[] = [ ')' => true ];
    }

    if ($project !== null) {
      $criteria[] = [ 'projectParticipation.project' => $project ];
    }

    $musicians = $this->musiciansRepository->findBy($criteria, [
      'surName' => 'ASC',
      'firstName' => 'ASC'
    ], $limit, $offset);

    if ($limit !== null && count($ids) > 0) {
      $criteria = [ [ 'id' => $ids ] ];
      if ($project !== null) {
        $criteria[] = [ 'projectParticipation.project' => $project ];
      }
      $byIdMusicians = $this->musiciansRepository->findBy($criteria);
      $musicians = array_merge($musicians, $byIdMusicians);
    }

    $musiciansData = [];
    /** @var Entities\Musician $musician */
    foreach ($musicians as $musician) {
      if (Uuid::asUuid($musician->getSurName()) !== null) {
        continue; // skip dummy musicians
      }
      $musiciansData[] = $this->getFlatMusician($musician, only: []);
    }

    return self::dataResponse($musiciansData);
  }

  private function getFlatMusician(Entities\Musician $musician, array $only = null)
  {
    return array_merge([
      'id' => $musician->getId(),
      'firstName' => $musician->getFirstName(),
      'surName' => $musician->getSurName(),
      'displayName' => $musician->getDisplayName(),
      'nickName' => $musician->getNickName(),
      'formalDisplayName' => $musician->getPublicName(firstNameFirst: false),
      'informalDisplayName' => $musician->getPublicName(firstNameFirst: true),
      'userId' => $musician->getUserIdSlug(),
      'countryName' => $this->countryNames[$musician->getCountry()] ?? '',
    ], $this->flattenMusician($musician, only: [])
    );
  }

  /**
   * @NoAdminRequired
   *
   * Get a short description of the project with no extra data.
   */
  public function getProject(int $projectId):Response
  {
    $project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);

    return self::dataResponse($this->flattenProject($project));
  }

  /**
   * @NoAdminRequired
   *
   * Search by user-id and names. Pattern may contain wildcards (* and %).
   */
  public function searchProjects(string $pattern, ?int $limit = null, ?int $offset = null, ?int $year = null): Response
  {
    $repository = $this->getDatabaseRepository(Entities\Project::class);

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
      $criteria = [
        'name' => $pattern,
      ];
    }

    $projects = $repository->findBy($criteria, [
      'year' => 'DESC',
      'name' => 'ASC',
    ], $limit, $offset);

    return self::dataResponse(array_map(fn($project) => $this->flattenProject($project), $projects));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

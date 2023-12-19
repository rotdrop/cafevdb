<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Service;

use Throwable;
use Exception;
use RuntimeException;
use InvalidArgumentException;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\SystemTag\ISystemTag;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\TagAlreadyExistsException;
use OCP\SystemTag\TagNotFoundException;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectsRepository;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumMemberStatus as MemberStatus;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Exceptions;

use OCA\DokuWiki\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo\Service\RPC as WebPagesRPC;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Constants;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class ProjectService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\DateTimeTrait;

  const DBTABLE = 'Projects';

  const FOLDER_TYPE_PROJECT = 'project';
  const FOLDER_TYPE_PARTICIPANTS = 'participants';
  const FOLDER_TYPE_PARTICIPANTS_TEMPLATE = self::FOLDER_TYPE_PARTICIPANTS . 'template';
  const FOLDER_TYPE_POSTERS = 'posters';
  const FOLDER_TYPE_DOWNLOADS = 'downloads';
  const FOLDER_TYPE_BALANCE = 'balance';

  const WEBPAGE_TYPE_CONCERT = 'concert';
  const WEBPAGE_TYPE_REHEARSALS = 'rehearsals';

  public const PROJECT_FOLDER_CONFIG_KEYS = [
    ConfigService::PROJECTS_FOLDER,
    ConfigService::PROJECT_PARTICIPANTS_FOLDER,
    ConfigService::PROJECT_POSTERS_FOLDER,
    ConfigService::PROJECT_PUBLIC_DOWNLOADS_FOLDER,
    ConfigService::BALANCES_FOLDER,
  ];

  /**
   * @var array<string, string>
   *
   * Cached array of project folder skeleton paths.
   */
  private $skeletonPaths = null;

  /** @var UserStorage */
  private $userStorage;

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  /** @var WikiRPC */
  private $wikiRPCInstance;

  /** @var OCA\Redaxo\Service\RPC */
  private $webPagesRPCInstance;

  /** @var ProjectsRepository */
  private $repository;

  /** @var IEventDispatcher */
  private $eventDispatcher;

  /** @var MusicianService */
  private $musicianService;

  /** @var ISystemTagManager */
  private $systemTagManager;

  /** {@inheritdoc} */
  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    UserStorage $userStorage,
    ProjectParticipantFieldsService $participantFieldsService,
    MusicianService $musicianService,
    IEventDispatcher $eventDispatcher
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->userStorage = $userStorage;
    $this->participantFieldsService = $participantFieldsService;
    $this->musicianService = $musicianService;
    $this->eventDispatcher = $eventDispatcher;

    try {
      $this->repository = $this->getDatabaseRepository(Entities\Project::class);
    } catch (Throwable $t) {
      $this->logError('HELLO');
      /** @var \OCP\IRequest $request */
      $request = \OC::$server->query(\OCP\IRequest::class);
      $this->logError('HELLO2');
      $userId = $this->userId();
      if ($request) {
        $this->logError('User "'.$userId.'" request uri "'.$request->getRequestUri().'"');
      } else {
        $this->logError('User "'.$userId.'", no request?!');
      }

      $this->logError('SERVER '.print_r($_SERVER, true));
      $this->logError('POST '.print_r($_REQUEST, true));
      $this->repository = null;
      $this->logException($t);
    }
    $this->l = $this->l10n();

    // refresh repository in case entity-manager is reopened
    $this->eventDispatcher->addListener(
      Events\EntityManagerBoundEvent::class,
      function(Events\EntityManagerBoundEvent $event) {
        $this->clearDatabaseRepository();
        $this->repository = $this->getDatabaseRepository(Entities\Project::class);
      }
    );
  }

  /**
   * Lazy getter for WikiRPC.
   *
   * @return WikiRPC
   */
  private function wikiRPC():WikiRPC
  {
    if (empty($this->wikiRPCInstance)) {
      $this->wikiRPCInstance = $this->di(WikiRPC::class);
      $this->wikiRPCInstance->errorReporting(WikiRPC::ON_ERROR_THROW);
    }
    return $this->wikiRPCInstance;
  }

  /**
   * Lazy getter for WebPagesRPC.
   *
   * @return WebPagesRPC
   */
  private function webPagesRPC():WebPagesRPC
  {
    if (empty($this->webPagesRPCInstance)) {
      $this->webPagesRPCInstance = $this->di(WebPagesRPC::class);
      $this->webPagesRPCInstance->errorReporting(WebPagesRPC::ON_ERROR_THROW);
    }
    return $this->webPagesRPCInstance;
  }

  /**
   * Lazy getter for ISystemTagManager.
   *
   * @return ISystemTagManager
   */
  private function systemTagManager():ISystemTagManager
  {
    if (empty($this->systemTagManager)) {
      $this->systemTagManager = $this->di(ISystemTagManager::class);
    }
    return $this->systemTagManager;
  }

  /**
   * Generate an option table with all participants, suitable to be
   * staffed into Navigation::selectOptions(). This is a single
   * select, only one musician may be preselected. The key is the
   * musician id. The options are meant for a single-choice select
   * box.
   *
   * @param int $projectId The id of the project to fetch the musician options from.
   *
   * @param null|string $projectName Optional project name, will be queried from
   * DB if not specified.
   *
   * @param int $selectedMusicianId A pre-selected musician, defaults to -1.
   *
   * @return array
   */
  public function participantOptions(int $projectId, ?string $projectName = null, int $selectedMusicianId = -1):array
  {
    $participants = $this->getDatabaseRepository(Entities\ProjectParticipant::class)->fetchParticipantNames($projectId);
    $options = [];
    foreach ($participants as $participant) {
      $musicianId = $participant['musicianId'];
      $flags = ($musicianId == $selectedMusicianId) ? Navigation::SELECTED : 0;
      $options[] = [
        'value' => $musicianId,
        'name' => $participant['firstName'].' '.$participant['surName'],
        'flags' => $flags,
      ];
    }
    return $options;
  }

  /**
   * Find the participant given by $projectOrId and $musicianOrId
   *
   * @param int|Entities\Project $projectOrId Database entity or id.
   *
   * @param int|Entities\Project $musicianOrId Database entity or id.
   *
   * @return null|Entities\ProjectParticipant
   */
  public function findParticipant(mixed $projectOrId, mixed $musicianOrId):?Entities\ProjectParticipant
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipant::class)
                ->find([ 'project' => $projectOrId, 'musician' => $musicianOrId]);
  }

  /**
   * Generate an selection options array.
   *
   * @param array $criteria Filter criteria for the projects database.
   *
   * @param int $selectedProject A pre-selected project-id.
   *
   * @return array
   *
   * @see OCA\CAFEVDB\PageRenderer\Util\Navigation::selectOptions()
   */
  public function projectOptions(array $criteria = [], int $selectedProject = 0):array
  {
    $projects = $this->repository->findBy($criteria, [ 'year' => 'DESC', 'name' => 'ASC' ]);
    $options = [];
    foreach ($projects as $project) {
      $flags = ($project['id'] == $selectedProject) ? Navigation::SELECTED : 0;
      $name = $project['name'];
      $year = $project['year'];
      $shortName = str_replace($year, '', $name);
      $options[] = [
        'value' => $project['id'],
        'name' => $name,
        'label' => $shortName,
        'flags' => $flags,
        'group' => $year,
        'type' => $project['type'],
      ];
    }
    return $options;
  }

  /**
   * Fetch the instrumentation balance for the given project.
   *
   * @param int|Entities\Project $projectOrId Database entity or id.
   *
   * @param bool $sumVoices Sum-up all voices into a single instrument field.
   *
   * @return array<string, array>
   * ```
   * [
   *   INSTRUMENT:VOICE => [
   *     'instrument' => INSTRUMENT,
   *     'voice' => VOICE,
   *     'registered' => (REQUIRED - REGISTERED),
   *     'confirmed' => (REQUIRED - CONFIRMED),
   *   ]
   * ]
   * ```
   *
   * If $sumVoices == true, the voice field is not present and the key
   * consists of the instrument only.
   *
   * @see \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectInstrumentationNumbersRepository::fetchInstrumentationBalance()
   */
  public function instrumentationBalance(mixed $projectOrId, bool $sumVoices = false):array
  {
    $projectId = ($projectOrId instanceof Entities\Project) ? $projectOrId['id'] : $projectOrId;


    $balanceData = $this->getDatabaseRepository(Entities\ProjectInstrumentationNumber::class)
                        ->fetchInstrumentationBalance($projectId);

    $balance = [];
    foreach ($balanceData as $row) {
      $instrument = $row['instrument'];
      if ($sumVoices) {
        $key = $instrument;
      } else {
        $voice = $row['voice'] > 0 ? $row['voice'] : 0;
        $key = $row['instrument'].':'.$voice;
      }
      if (empty($balance[$key])) {
        $balance[$key] = [
          'instrument' => $instrument,
          'required' => 0,
          'registered' => 0,
          'confirmed' => 0,
        ];
      }
      $required = $row['required'];
      foreach (['registered', 'confirmed'] as $field) {
        $balance[$key][$field] += $required - $row[$field];
      }
      $balance[$key]['required'] += $required;
      if (!$sumVoices) {
        $balance[$key]['voice'] = $voice;
      }
    }
    return $balance;
  }

  /**
   * Find a project by its id.
   *
   * @param int $projectId
   *
   * @return null|Entities\Project
   */
  public function findById(int $projectId):?Entities\Project
  {
    return $this->repository->find($projectId);
  }

  /**
   * Find a project by its name.
   *
   * @param string $projectName
   *
   * @return null|Entities\Project
   */
  public function findByName(string $projectName):?Entities\Project
  {
    return $this->repository->findOneBy([
      'name' => $projectName,
      'deleted' => null,
    ]);
  }

  /**
   * Persist and flush the database.
   *
   * @param Entities\Project $project Database entity.
   *
   * @return void
   *
   * @see OCA\CAFEVDB\Controller\PersonalSettingsController::setApp()
   */
  public function persistProject(Entities\Project $project):void
  {
    $this->persist($project);
    $this->flush();
  }

  /**
   * @param int $projectId Database entity id.
   *
   * @return null|string Fetch the project-name name corresponding to $projectId.
   */
  public function fetchName(int $projectId):?string
  {
    $project = $this->repository->find($projectId);
    if ($project == null) {
      return null;
    }
    return $project->getName();
  }

  /**
   * @return Just return the collection of all projects.
   */
  public function fetchAll():array
  {
    return $this->repository->findAll();
  }

  /**
   * Fetch some project matching the specified criteria.
   *
   * @param array $criteria
   *
   * @param array $orderBy Sorting criteria. Default is to index by project id
   * and sort descending by type, descending by year, ascending by name.
   *
   * @return array<int, Entities\Project>
   */
  public function fetch(array $criteria, ?array $orderBy = []):array
  {
    if (empty($orderBy)) {
      $orderBy['type'] = 'DESC';
      $orderBy['year'] = 'DESC';
      $orderBy['name'] = 'ASC';
    }
    if (!in_array('INDEX', array_values($orderBy))) {
      $orderBy['id'] = 'INDEX';
    }
    return $this->repository->findBy($criteria, $orderBy);
  }

  /**
   * Compute the effective project registration deadline, that is: if there is
   * a user-defined deadline, then this is it. Otherwise take minimum date
   * minus 1 day of the registered rehearsal and concert events for the
   * project. If neither is defined return null (no deadline is imposed).
   *
   * The deadline may be used by the project registration form in order to
   * limit the project selection menu or to assert a warning if an expired
   * registration form is submitted.
   *
   * @param mixed $projectOrId
   *
   * @param bool $ignoreExplicit Ignore any explicitly set deadline and just
   * return the date implied by the rehearsal and concert events.
   *
   * @return null|DateTimeInterface
   */
  public function getProjectRegistrationDeadline(mixed $projectOrId, bool $ignoreExplicit = false):?DateTimeInterface
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);
    if (!$ignoreExplicit) {
      $deadline = $project->getRegistrationDeadline();
      if (!empty($deadline)) {
        return $deadline;
      }
    }

    /** @var EventsService $eventsService */
    $eventsService = $this->di(EventsService::class);

    $events = array_filter(
      $eventsService->events($project) ?? [],
      fn(array $event) => $event['calendarUri'] == ConfigService::REHEARSALS_CALENDAR_URI || $event['calendarUri'] == ConfigService::CONCERTS_CALENDAR_URI,
    );

    if (empty($events)) {
      return null;
    }

    $startDates = array_map(fn(array $event) => $event['start'], $events);

    $deadline = min($startDates)->modify('-1 day');

    // strip the time information
    return self::convertToDateTime($deadline);
  }

  /**
   * Depending on the type of the project generate the partial project-folder
   * path:
   * - ProjectType::TEMPORARY -- path is YEAR/PROJECT_NAME
   * - ProjectType::PERMANENT -- path is just PROJECT_NAME
   * - ProjectType::TEMPLATE -- path is L10N_TEMPLATE_NAME/PROJECT_NAME
   *
   * @param array|Entities\Project $project
   *
   * @return array
   */
  public function getProjectPathComponents(mixed $project):array
  {
    switch ($project['type']) {
      case ProjectType::TEMPORARY:
        return [ $project['year'], $project['name'] ];
      case ProjectType::PERMANENT:
        return [ $project['name'], ];
      case ProjectType::TEMPLATE:
        return [ $this->l->t('templates'), $project['name'] ];
    }
    return null;
  }

  /**
   * Depending on the type of the project generate the partial project-folder
   * path:
   * - ProjectType::TEMPORARY -- path is YEAR/PROJECT_NAME
   * - ProjectType::PERMANENT -- path is just PROJECT_NAME
   * - ProjectType::TEMPLATE -- path is L10N_TEMPLATE_NAME/PROJECT_NAME
   *
   * @param array|Entities\Project $project
   *
   * @return string
   */
  public function getProjectPathPrefix(mixed $project):string
  {
    return implode(Constants::PATH_SEP, $this->getProjectPathComponents($project));
  }

  /**
   * Get the configured name of the or all or the specified folder.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param string|null $only Get the name of only this folder if not
   * null. $only can be one of the PROJECTS_..._FOLDER constants of
   * the ConfigService class, @see ConfigService.
   *
   * @return mixed Either the array of all paths or the path requested by $only.
   */
  public function getProjectFolder($projectOrId, ?string $only = null)
  {
    $project = $this->repository->ensureProject($projectOrId);
    $pathPrefix = $this->getProjectPathPrefix($project);
    $sharedFolder = Constants::PATH_SEP . $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $folders = $only ? [ $only ] : self::PROJECT_FOLDER_CONFIG_KEYS;

    $paths = [];
    foreach ($folders as $key) {
      switch ($key) {
        case ConfigService::PROJECTS_FOLDER:
        case ConfigService::PROJECT_PARTICIPANTS_FOLDER:
        case ConfigService::PROJECT_POSTERS_FOLDER:
        case ConfigService::PROJECT_PUBLIC_DOWNLOADS_FOLDER:
          $projectsFolder = $sharedFolder
            . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
            . Constants::PATH_SEP . $pathPrefix;
          if ($key == ConfigService::PROJECTS_FOLDER) {
            $paths[$key] = $projectsFolder;
            break;
          }
          $paths[$key] = $projectsFolder . Constants::PATH_SEP . $this->getConfigValue($key);
          break;
        case ConfigService::BALANCES_FOLDER:
          $paths[$key] = $sharedFolder
            . Constants::PATH_SEP . $this->getConfigValue(ConfigService::FINANCE_FOLDER)
            . Constants::PATH_SEP . $this->getConfigValue(ConfigService::BALANCES_FOLDER)
            . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
            . Constants::PATH_SEP . $pathPrefix;
          break;
      }
    }
    return empty($only) ? $paths : $paths[$only];
  }

  /**
   * Return an array with the project skeleton paths, indexed by folder-type.
   *
   * @return array
   *
   * @see ensureProjectFolders()
   */
  public function getProjectSkeletonPaths():array
  {
    if ($this->skeletonPaths !== null) {
      return $this->skeletonPaths;
    }
    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $skeletonPath = implode(Constants::PATH_SEP, [
      $sharedFolder,
      $this->getConfigValue(ConfigService::DOCUMENT_TEMPLATES_FOLDER),
      $projectsFolder,
      $this->appL10n()->t(ConfigService::PROJECT_SKELETON_FOLDER),
    ]);
    $participantsFolder = $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $postersFolder = $this->getConfigValue(ConfigService::PROJECT_POSTERS_FOLDER);
    $downloadsFolder = $this->getConfigValue(ConfigService::PROJECT_PUBLIC_DOWNLOADS_FOLDER);
    $balancesFolder  = $this->getConfigValue(ConfigService::BALANCES_FOLDER);

    $managementSkeleton = $skeletonPath . Constants::PATH_SEP . $this->appL10n()->t(ConfigService::PROJECT_MANAGEMENT_SKELETON_FOLDER);
    $balanceSkeleton = $skeletonPath . Constants::PATH_SEP . $balancesFolder;

    $this->skeletonPaths = [
      self::FOLDER_TYPE_BALANCE => $balanceSkeleton,
      self::FOLDER_TYPE_PROJECT => $managementSkeleton,
      self::FOLDER_TYPE_PARTICIPANTS => $managementSkeleton . Constants::PATH_SEP . $participantsFolder,
      self::FOLDER_TYPE_POSTERS => $managementSkeleton . Constants::PATH_SEP . $postersFolder,
      self::FOLDER_TYPE_DOWNLOADS => $managementSkeleton . Constants::PATH_SEP . $downloadsFolder,
    ];
    $this->skeletonPaths[self::FOLDER_TYPE_PARTICIPANTS_TEMPLATE] =
      $this->skeletonPaths[self::FOLDER_TYPE_PARTICIPANTS]
      . Constants::PATH_SEP
      . $this->appL10n()->t(ConfigService::PROJECT_PARTICIPANTS_SKELETON_FOLDER);

    return $this->skeletonPaths;
  }

  /**
   * Check for the existence of the project folders. Returns an array
   * of folders (balance and general files).
   *
   * @param int|Entities\Project $projectOrId Database entity or id.
   *
   * @param null|string $only If a string create only this folder, can be one
   * of self::FOLDER_TYPE_PROJECT, self::FOLDER_TYPE_BALANCE,
   * self::FOLDER_TYPE_PARTICIPANTS, self::FOLDER_TYPE_POSTERS,
   * self::FOLDER_TYPE_DOWNLOADS.
   *
   * @param bool $dry Just create the name, but do not perform any
   * file-system operations.
   *
   * @return array Array of created folders.
   */
  public function ensureProjectFolders(
    mixed $projectOrId,
    ?string $only = null,
    bool $dry = false
  ):array {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);

    if (empty($project)) {
      throw new Exception('CANNOT FIND PROJECT FOR ID ' . $projectOrId);
    }

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $financeFolder = $this->getConfigValue(ConfigService::FINANCE_FOLDER);
    $participantsFolder = $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $postersFolder = $this->getConfigValue(ConfigService::PROJECT_POSTERS_FOLDER);
    $downloadsFolder = $this->getConfigValue(ConfigService::PROJECT_PUBLIC_DOWNLOADS_FOLDER);
    $balancesFolder  = $this->getConfigValue(ConfigService::BALANCES_FOLDER);

    $skeletonPaths = $this->getProjectSkeletonPaths();
    // TRANSLATORS: this is a placeholder for the login id. It must in particular remain all lowercase.
    $skeletonExclude = '/' . preg_quote($this->appL10n()->t(ConfigService::PROJECT_PARTICIPANTS_SKELETON_FOLDER), '/') . '/';

    $pathPrefix = $this->getProjectPathPrefix($project);

    $projectPaths = [
      self::FOLDER_TYPE_PROJECT => [
        $sharedFolder,
        $projectsFolder,
        $pathPrefix,
      ],
      self::FOLDER_TYPE_BALANCE => [
        $sharedFolder,
        $financeFolder,
        $balancesFolder,
        $projectsFolder,
        $pathPrefix,
      ],
      self::FOLDER_TYPE_PARTICIPANTS => [
        $sharedFolder,
        $projectsFolder,
        $pathPrefix,
        $participantsFolder,
      ],
      self::FOLDER_TYPE_POSTERS => [
        $sharedFolder,
        $projectsFolder,
        $pathPrefix,
        $postersFolder,
      ],
      self::FOLDER_TYPE_DOWNLOADS => [
        $sharedFolder,
        $projectsFolder,
        $pathPrefix,
        $downloadsFolder,
      ],
    ];

    $returnPaths = [];
    foreach ($projectPaths as $key => $chain) {
      if (!empty($only) && $key != $only) {
        continue;
      }
      try {
        $destinationPath = Constants::PATH_SEP . implode(Constants::PATH_SEP, $chain);
        if (!$dry) {
          $this->userStorage->ensureFolderChain($chain);
          if (!empty($only)
              || $key == self::FOLDER_TYPE_PROJECT
              || $key == self::FOLDER_TYPE_BALANCE) {
            $skeletonPath = $skeletonPaths[$key];
            $this->userStorage->copyTree($skeletonPath, $destinationPath, $skeletonExclude);
          }
        }
        $returnPaths[$key] = $destinationPath;
      } catch (Throwable $t) {
        if (!empty($only)) {
          throw new Exception(
            $this->l->t(
              'Unable to ensure existence of folder "%s".',
              Constants::PATH_SEP . implode(Constants::PATH_SEP, $chain)),
            $t->getCode(),
            $t);
        } else {
          $this->logException($t);
        }
      }
    }
    return $returnPaths;
  }

  /**
   * Remove the folder for the given project.
   *
   * @param int|Entities\Project|array $projectOrId Project entity or
   * plain query result or project id.
   *
   * @return bool Status
   *
   * @todo It is probably not necessary to remove the sub-folders separately
   */
  public function removeProjectFolders($projectOrId):bool
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);

    $pathPrefix = $this->getProjectPathPrefix($project);

    $sharedFolder   = Constants::PATH_SEP . $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $sharedFolder
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
      . Constants::PATH_SEP . $pathPrefix;
    // $participantsFolder = $projectsFolder . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    // $postersFolder  = $projectsFolder . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECT_POSTERS_FOLDER);
    // $downloadsFolder = $projectsFolder . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECT_PUBLIC_DOWNLOADS_FOLDER);
    $balanceFolder  = $sharedFolder
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::FINANCE_FOLDER)
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::BALANCES_FOLDER)
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
      . Constants::PATH_SEP . $pathPrefix;

    $projectPaths = [
      // self::FOLDER_TYPE_POSTERS => $postersFolder,
      // self::FOLDER_TYPE_PARTICIPANTS => $participantsFolder,
      self::FOLDER_TYPE_PROJECT => $projectsFolder,
      self::FOLDER_TYPE_BALANCE => $balanceFolder,
    ];

    foreach ($projectPaths as $path) {
      $this->userStorage->delete($path);
    }

    return true;
  }

  /**
   * Restore the folders for the given project in order to undelete or
   * during error recovery.
   *
   * @param Entities\Project $project Project entity.
   *
   * @param null|array $timeInterval Unfortunately the time-stamp in
   * the trash bin is hard to get hold of. If $timeInterval is given,
   * only try to restore folders in the given interval.
   *
   * @return bool Status
   */
  private function restoreProjectFolders(Entities\Project $project, ?array $timeInterval = null):bool
  {
    $pathPrefix = $this->getProjectPathPrefix($project);

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $sharedFolder
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
      . Constants::PATH_SEP . $pathPrefix;
    $balanceFolder  = $sharedFolder
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::FINANCE_FOLDER)
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::BALANCES_FOLDER)
      . Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
      . Constants::PATH_SEP . $pathPrefix;

    $projectPaths = [
      self::FOLDER_TYPE_PROJECT => $projectsFolder,
      self::FOLDER_TYPE_BALANCE => $balanceFolder,
    ];

    $count = 0;
    foreach ($projectPaths as $path) {
      $result = $this->userStorage->restore($path, $timeInterval);
      $count += (int)!!$result;
    }

    return $count == count($projectPaths);
  }

  /**
   * Rename the associated project folders after project rename.
   *
   * @param array|Entities\Project $newProject Array-like object,
   * "id", "name" and "year" keys need to be present.
   *
   * @param array|Entities\Project $oldProject Array-like object,
   * "id", "name" and "year" keys need to be present.
   *
   * @return array Array with new paths.
   */
  public function renameProjectFolder($newProject, $oldProject)
  {
    if (!isset($newProject['id'])) {
      $newProject['id'] = $oldProject['id'];
    }
    if (!isset($newProject['year'])) {
      $newProject['year'] = $oldProject['year'];
    }
    if (!isset($newProject['type'])) {
      $newProject['type'] = $oldProject['type'];
    }

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $financeFolder = $this->getConfigValue(ConfigService::FINANCE_FOLDER);
    $balancesFolder  = $this->getConfigValue(ConfigService::BALANCES_FOLDER);

    $prefixPath = [
      self::FOLDER_TYPE_PROJECT => (
        Constants::PATH_SEP . $sharedFolder
        . Constants::PATH_SEP . $projectsFolder
        . Constants::PATH_SEP),
      self::FOLDER_TYPE_BALANCE => (
        Constants::PATH_SEP . $sharedFolder
        . Constants::PATH_SEP . $financeFolder
        . Constants::PATH_SEP . $balancesFolder
        . Constants::PATH_SEP . $projectsFolder
        . Constants::PATH_SEP),
    ];

    $returnPaths = [];
    foreach ($prefixPath as $key => $prefix) {

      $oldPath = $prefix . $this->getProjectPathPrefix($oldProject);

      $newPathComponents = $this->getProjectPathComponents($newProject);

      if (count($newPathComponents) > 1) {
        $newPrefixPath = $prefix . $newPathComponents[0];
      }
      $newPath = $newPrefixPath . Constants::PATH_SEP . array_pop($newPathComponents);

      $oldDir = $this->userStorage->get($oldPath);
      if (!empty($oldDir)) {
        // If the year has changed it may be necessary to create a new
        // directory.
        $this->userStorage->ensureFolder($newPrefixPath);
        $this->userStorage->rename($oldPath, $newPath);
        $returnPaths[$key] = $newPath;
      } else {
        try {
          // Otherwise there is nothing to move; we simply create the new directory.
          $returnPaths = array_merge(
            $returnPaths,
            $this->ensureProjectFolders($newProject, only: $key));
        } catch (Throwable $t) {
          $this->logException($t);
        }
      }
    }

    return $returnPaths;
  }

  /**
   * Make sure the per-project posters folder exists for the given project.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param bool $dry If true then just create the name, do not
   * perform any file-system operations.
   *
   * @return string Folder path.
   */
  public function ensurePostersFolder($projectOrId, bool $dry = false)
  {
    list(self::FOLDER_TYPE_POSTERS => $path,) = $this->ensureProjectFolders($projectOrId, only: self::FOLDER_TYPE_POSTERS, dry: $dry);
    return $path;
  }

  /**
   * Make sure the per-project downloads folder exists for the given project and is shared via link.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param bool $dry If true then just create the name, do not
   * perform any file-system operations.
   *
   * @return string Folder path.
   */
  public function ensureDownloadsFolder($projectOrId, bool $dry = false)
  {
    list(self::FOLDER_TYPE_DOWNLOADS => $path,) = $this->ensureProjectFolders($projectOrId, only: self::FOLDER_TYPE_DOWNLOADS, dry: $dry);
    return $path;
  }

  /**
   * Make sure the per-project downloads folder exists for the given project and is shared via link.
   *
   * @param int|Entities\Project $projectOrId Entity or entity id for a project.
   *
   * @param bool $noCreate Do not create the share if it does not exist, defaults to \false.
   *
   * @return array [ 'share' => URL, 'folder' => PATH, 'expires' => DATE ]
   */
  public function ensureDownloadsShare($projectOrId, bool $noCreate = false):array
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);
    try {
      if ($noCreate) {
        $path = $this->ensureDownloadsFolder($projectOrId, dry: true);
        $node = $this->userStorage->get($path);
        if (empty($node)) {
          return [
            'share' => null,
            'folder' => null,
            'expires' => null,
          ];
        }
      } else {
        $path = $this->ensureDownloadsFolder($projectOrId, dry: false);
        $node = $this->userStorage->get($path);
        $expires = null;
      }
      /** @var SimpleSharingService $sharingService */
      $sharingService = $this->di(SimpleSharingService::class);

      $shareOwnerUid = $this->getConfigValue(ConfigService::SHAREOWNER_KEY);
      // try to create or use the folder and share it by a public link
      $url = $sharingService->linkShare(
        $node,
        $shareOwnerUid,
        sharePerms: \OCP\Constants::PERMISSION_READ|\OCP\Constants::PERMISSION_SHARE,
        expirationDate: false, // ignore
        noCreate: $noCreate,
      );
      if (!empty($url)) {
        try {
          $expires = $sharingService->getLinkExpirationDate($url);
          if ($expires === null) {
            $expires = new DateTimeImmutable($project->getYear() . '-12-31');
            $now =  (new DateTimeImmutable)->setTime(0, 0, 0);
            if ($expires < $now) {
              $expires = $now->add(DateInterval::createFromDateString('1 week'));
            }
            $expires = $sharingService->expireLinkShare($url, $expires);
          }
        } catch (ShareNotFound $e) {
          $expires = null;
        }
        if (empty($expires)) {
          throw new Exceptions\Exception(
            $this->l->t(
              'Unable set expiration date for the public download link "%1$s" for the project "%2$s".',
              [ $url, $project->getName(), ]
            ),
            0,
            $e
          );
        }
      }
    } catch (Throwable $t) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t('Unable to create the public donwload link for the project "%s".', $project->getName()),
        0,
        $t
      );
    }
    return [
      'share' => $url,
      'folder' => $path,
      'expires' => $expires ?? null,
    ];
  }

  /**
   * Make sure the per-project per-participant folder exists for the given
   * project and musician. If a skeleton folder exists then its contents is
   * also copied over.
   *
   * @param Entities\Project $project
   *
   * @param Entities\Musician $musician
   *
   * @param bool $dry If true then just create the name, do not
   * perform any file-system operations.
   *
   * @return string Folder path.
   */
  public function ensureParticipantFolder(Entities\Project $project, Entities\Musician $musician, bool $dry = false):string
  {
    list(self::FOLDER_TYPE_PARTICIPANTS => $parentPath,) = $this->ensureProjectFolders($project, only: self::FOLDER_TYPE_PARTICIPANTS, dry: $dry);
    $userIdSlug = $this->musicianService->ensureUserIdSlug($musician);
    $participantFolder = $parentPath . Constants::PATH_SEP . $userIdSlug;
    if (!$dry) {
      $this->userStorage->ensureFolder($participantFolder);
      $this->getProjectSkeletonPaths();
      $participantSkeleton = $this->skeletonPaths[self::FOLDER_TYPE_PARTICIPANTS_TEMPLATE];
      $this->userStorage->copyTree($participantSkeleton, $participantFolder);
    }
    return $participantFolder;
  }

  /**
   * More leight-weight construction of the participant folder, assuming
   * everything else is just is ok.
   *
   * @param Entities\Project $project Database entity.
   *
   * @param Entities\Musician $musician Database entity.
   *
   * @return string
   */
  public function getParticipantFolder(Entities\Project $project, Entities\Musician $musician):string
  {
    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $participantsFolder = $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $userIdSlug = $musician->getUserIdSlug();
    if (empty($userIdSlug)) {
      return null;
    }

    return Constants::PATH_SEP . implode(
      Constants::PATH_SEP, [
        $sharedFolder,
        $projectsFolder,
        $this->getProjectPathPrefix($project),
        $participantsFolder,
        $userIdSlug,
      ]);
  }

  /**
   * Avoid duplicated "extensions" in file-names, i.e. use
   * passport-ClausJustusHeine.pdf instead of passport-claus-justus.heine.pdf
   *
   * @param string $base Filename base.
   *
   * @param string|Entities\Musician $musicianOrSlug Entity or its user-id-slug.
   *
   * @param bool $ignoreExtension If \false (the default) then an extension
   * present in $base will be stripped from $base and appended to the
   * resulting file-name. If \true any dots in $base will just be replaced by
   * dashes.
   *
   * @return string
   */
  public function participantFilename(string $base, $musicianOrSlug, bool $ignoreExtension = false):string
  {
    if ($musicianOrSlug instanceof Entities\Musician) {
      $userIdSlug = $this->musicianService->ensureUserIdSlug($musicianOrSlug);
    } else {
      $userIdSlug = $musicianOrSlug;
    }
    return MusicianService::slugifyFileName($base, $userIdSlug, $ignoreExtension);
  }

  /**
   * Generatet the full path to the parent folder of the files attached to the
   * given field data (if there are any)
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @param bool $includeDeleted Include deleted entities in the result.
   *
   * @return null|string
   */
  public function getParticipantFieldFolderPath(
    Entities\ProjectParticipantField $field,
    bool $includeDeleted = false,
  ):?string {
    if (!$includeDeleted && ($field->isDeleted())) {
      return null;
    }
    if ($field->getMultiplicity() == FieldMultiplicity::SIMPLE) {
      $dirName = '';
    } else {
      $dirName = $this->participantFieldsService->getFileSystemFieldName($field);
    }

    switch ($field->getDataType()) {
      case FieldDataType::RECEIVABLES:
      case FieldDataType::LIABILITIES:
        $subDirPrefix =
          $this->getSupportingDocumentsFolderName()
          . Constants::PATH_SEP
          . $this->getReceivablesFolderName();
        $dirName = empty($dirName) ? $subDirPrefix : $subDirPrefix . Constants::PATH_SEP . $dirName;
        break;
    }

    return $dirName;
  }

  /**
   * Fetch the file for a field-datum and return path-info like information
   * for the file. If an Entities\EncryptedFile instance is given then use
   * this for determining the 'dbFileName' and extension as if the given
   * file-entity would be used instead of the stored entity.
   *
   * Otherwise use just the information from the stored directory entry.
   *
   * The generated paths are relative to the encrypted per-participant
   * database-storage mount.
   *
   * @param Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @param null|Entities\EncryptedFile $newFile
   *
   * @param bool $includeDeleted Include deleted entities in the result.
   *
   * @return null|array
   * ```
   * [
   *   'dirEntry' => DATBASE_STORAGE_FILE_ENTITY, // null if $newFile is given
   *   'file' => FILE_ENTITY,
   *   'baseName' => BASENAME, // generated
   '   'dirName' => DIRENAME, // generated
   *   'extension' => FILE EXTENSION, // from db-file
   *   'fileName' => FILENAME, // basename without extension
   *   'pathName' => DIRNAME/BASENAME,
   *   'dbFileName' => FILENAME_AS_STORED_IN_DB_TABLE,
   * ]
   * ```
   */
  public function participantFileInfo(
    Entities\ProjectParticipantFieldDatum $fieldDatum,
    ?Entities\EncryptedFile $newFile = null,
    bool $includeDeleted = false,
  ):?array {
    if (!$includeDeleted && $fieldDatum->isDeleted()) {
      return null;
    }
    /** @var Entities\ProjectParticipantField $field */
    $field = $fieldDatum->getField();
    if (!$includeDeleted && $field->isDeleted()) {
      return null;
    }
    /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
    $fieldOption = $fieldDatum->getDataOption();
    if (!$includeDeleted && $fieldOption->isDeleted()) {
      return null;
    }
    $dataType = $field->getDataType();
    if (empty($newFile)) {
      /** @var Entities\DatabaseStorageFile $file */
      switch ($dataType) {
        case FieldDataType::DB_FILE:
          $fileId = (int)$fieldDatum->getOptionValue();
          $dirEntry = $this->findEntity(Entities\DatabaseStorageFile::class, $fileId);
          break;
        case FieldDataType::RECEIVABLES:
        case FieldDataType::LIABILITIES:
          $dirEntry = $fieldDatum->getSupportingDocument();
          break;
        default:
          return null;
      }
      if (empty($dirEntry)) {
        return null;
      }
      $file = $dirEntry->getFile();
      $dbFileName = $dirEntry->getName();
      $pathName = $dirEntry->getPathName();
      list(
        'basename' => $baseName,
        'dirname' => $dirName,
        'extension' => $extension,
        'filename' => $fileName,
      ) = pathinfo($pathName);
    } else {
      $dirEntry = null;
      $file = $newFile;
      /** @var Entities\EncryptedFile $file */
      $dbFileName = $file->getFileName();
      $extension = pathinfo($dbFileName, PATHINFO_EXTENSION);
      $fieldName = $this->participantFieldsService->getFileSystemFieldName($field);

      $musician = $fieldDatum->getMusician();

      if ($field->getMultiplicity() == FieldMultiplicity::SIMPLE) {
        // construct the file-name from the field-name
        $fileName = $this->participantFilename($fieldName, $musician, ignoreExtension: true);
      } else {
        // construct the file-name from the option label if non-empty or the file-name of the DB-file
        $optionLabel = $this->participantFieldsService->getFileSystemOptionLabel($fieldOption);
        if (!empty($optionLabel)) {
          $fileName = $this->participantFilename($optionLabel, $musician, ignoreExtension: true);
        } else {
          $fileName = basename($dbFileName, '.' . $extension);
        }
      }

      // the following duplicates some of the logic above, but we need the path-name in other places
      $dirName = $this->getParticipantFieldFolderPath($field, $includeDeleted);

      $baseName = $fileName . '.' . $extension;
      $pathName = empty($dirName) ? $baseName : $dirName . Constants::PATH_SEP . $baseName;
    }

    return compact(
      'dirEntry',
      'file',
      'baseName',
      'dirName',
      'extension',
      'fileName',
      'pathName',
      'dbFileName',
    );
  }

  /**
   * Rename all project-participants folders in order to reflect changes in
   * the user-id-slug (== user-name). This functions registers suitable
   * Common\IUndoable actions with the EntityManager which are executed pre-commit.
   *
   * @param Entities\Musician $musician Database entity.
   *
   * @param string $oldUserIdSlug Old slug.
   *
   * @param string $newUserIdSlug New slug.
   *
   * @return void
   *
   * @todo This alone does not suffice. Wie also have to rename a bunch of
   * per-project files.
   */
  public function renameParticipantFolders(Entities\Musician $musician, string $oldUserIdSlug, string $newUserIdSlug):void
  {
    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    /** @var Entities\ProjectParticipant $projectParticipant */
    foreach ($musician->getProjectParticipation() as $projectParticipant) {
      $project = $projectParticipant->getProject();

      $participantsFolder = $this->getProjectFolder($project, ConfigService::PROJECT_PARTICIPANTS_FOLDER);

      $newFolderPath = $participantsFolder . Constants::PATH_SEP . $newUserIdSlug;

      if (!empty($oldUserIdSlug)) {

        $oldFolderPath = $participantsFolder . Constants::PATH_SEP . $oldUserIdSlug;

        // apart from the project folder the user-id-slug is also potentially
        // part of the file-name of several files.

        /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
        foreach ($projectParticipant->getParticipantFieldsData() as $fieldDatum) {
          /** @var Entities\ProjectParticipantField $field */
          $field = $fieldDatum->getField();
          if ($field->getDataType() != FieldDataType::CLOUD_FILE) {
            continue;
          }

          $project = $field->getProject();
          $extension = pathinfo($fieldDatum->getOptionValue(), PATHINFO_EXTENSION);

          $fileSystemFieldName = $this->participantFieldsService->getFileSystemFieldName($field);

          // @todo: this should be moved to the ProjectParticipantFieldsService
          if ($field->getMultiplicity() == FieldMultiplicity::SIMPLE) {
            // name based on field name
            $nameBase = $fileSystemFieldName;
            $subDir = '';
          } else {
            // name based on option label
            $nameBase = $this->participantFieldsService->getFileSystemOptionLabel($fieldDatum->getDataOption());
            $subDir = $fileSystemFieldName . Constants::PATH_SEP;
          }
          $oldFilePath =
            $oldFolderPath . Constants::PATH_SEP
            . $subDir
            . $this->participantFilename($nameBase, $oldUserIdSlug)
            . '.' . $extension;

          $newFilePath =
            $oldFolderPath . Constants::PATH_SEP
            . $subDir
            . $this->participantFilename($nameBase, $newUserIdSlug)
            . '.' . $extension;

          $this->logInfo('Try rename files ' . $oldFilePath . ' -> ' . $newFilePath);

          $this->entityManager->registerPreFlushAction(
            new Common\UndoableFileRename($oldFilePath, $newFilePath, true /* gracefully */)
          );
        }

      } else {
        $oldFolderPath = null;
      }

      $this->logInfo('Try rename folders ' . $oldFolderPath . ' -> ' . $newFolderPath);

      // rename the project folder, this is the "easy" part
      $this->entityManager->registerPreFlushAction(
        new Common\UndoableFolderRename($oldFolderPath, $newFolderPath, true /* gracefully */)
      );
    }

    // Adjust the mount-points of the database storage. Undo is not necessary
    // as it is handled by a roll-back.
    $this->entityManager->registerPreFlushAction(
      new Common\GenericUndoable(function() use ($oldUserIdSlug, $newUserIdSlug) {
        $storages = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findBy([
          'storageId' => '%' . $oldUserIdSlug . '%'
        ]);
        /** @var Entities\DatabaseStorage $storage */
        foreach ($storages as $storage) {
          $oldStorageId = $storage->getStorageId();
          $newStorageId = str_replace($oldUserIdSlug, $newUserIdSlug, $oldStorageId);
          $storage->setStorageId($newStorageId);
        }
      }));

    $softDeleteableState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
  }

  /**
   * @param string $pageName The name of the wiki page without namespace.
   *
   * @return string The namespaced wiki page name.
   */
  public function projectWikiLink(string $pageName):string
  {
    $wikiNameSpace = $this->getConfigValue('orchestra');
    $wikiNameSpace = $this->getAppValue('wikinamespace', $wikiNameSpace);
    $projectsNamespace = strtolower($this->getConfigValue(ConfigService::PROJECTS_FOLDER));

    return $wikiNameSpace . ':' . $projectsNamespace . ':' . $pageName;
  }

  /**
   * Generate an automated overview
   *
   * @param array<int> $exclude Excluded project-ids, used e.g. during
   * deletion of projects.
   *
   * @return mixed
   */
  public function generateWikiOverview(array $exclude = []):mixed
  {
/*
  ====== Projekte der Camerata Academica Freiburg e.V. ======

  ==== 2011 ====
  * [[Auvergne2011|Auvergne]]
  * [[Weihnachten2011]]

  ==== 2012 ====
  * [[Listenpunkt]]
  * [[Blah]]

  ==== 2013 ====
  * [[Listenpunkt]]
  */
    $orchestra = $this->getConfigValue('orchestra');
    $orchestra = $this->getConfigValue('streetAddressName01', $orchestra);

    $projects = [];
    foreach (ProjectType::toArray() as $projectType) {
      $projects[$projectType] = $this->repository->findBy(
        [
          [ '!id' => $exclude ],
          [ 'type' => $projectType ],
        ],
        [ 'year' => 'DESC', 'name' => 'ASC' ]);
      if ($projects[$projectType] === null) {
        $this->logInfo('NULL PROJECTS? ' . $projectType);
      }
    }
    $projects = array_merge(
      $projects[ProjectType::PERMANENT],
      $projects[ProjectType::TEMPORARY],
      $projects[ProjectType::TEMPLATE],
    );

    $page = "====== ".($this->l->t('Projects of %s', [$orchestra]))."======\n\n";

    $projectGroup = null;
    /** @var Entities\Project $project */
    foreach ($projects as $project) {
      $currentProjectGroup = $project->getType() == ProjectType::TEMPORARY
        ? $project->getYear()
        : $this->l->t($project->getType());
      if ($currentProjectGroup != $projectGroup) {
        $projectGroup = $currentProjectGroup;
        $page .= "\n==== " . $projectGroup . "====\n";
      }
      $name = $project['name'];

      $matches = false;
      if (preg_match('/^(.*\D)?(\d{4})$/', $name, $matches) == 1) {
        $bareName = $matches[1];
        //$projectYear = $matches[2];
      } else {
        $bareName = $name;
      }

      // A page is tagged with the project name; if this ever should
      // be changed (which is possible), then change-trigger should
      // create a new page as copy from the old one and change the
      // text of the old one to contain a link to the new page.

      $page .= "  * [[".$this->projectWikiLink($name)."|".$bareName."]]\n";
    }

    $projectsName = strtolower($this->getConfigValue(ConfigService::PROJECTS_FOLDER));
    $pageName = $this->projectWikiLink($projectsName);

    return $this->wikiRPC()->putPage(
      $pageName,
      $page, [
        "sum" => "Automatic CAFEVDB synchronization",
        "minor" => true,
      ]);
  }

  /**
   * Generate an almost empty project page. This spares people the
   * need to click on "new page".
   *
   * - We insert a proper title heading
   *
   * - We insert a sub-title "Contacts"
   *
   * - We insert a sub-title "Financial Arrangements"
   *
   * - We insert a sub-title "Location"
   *
   * @param int $projectId Database entity id.
   *
   * @param string $projectName Project name.
   *
   * @return void
   *
   * @see WikiRPC::putPage()
   */
  public function generateProjectWikiPage(int $projectId, string $projectName):void
  {
    $page = $this->l->t(
      '====== Project %s ======

===== Forword =====

This wiki-page is useful to store selected project related
informations in comfortable and structured form. This can be useful
for "permant information" like details about supplementary fees,
contact informations and the like. In particular, this page could be
helpful to reduce unnecessary data-digging in our email box.

===== Contacts =====
Please add any relevant email and mail-adresses here. Please use the wiki-syntax
* [[foobar@important.com||Mister Universe]]

===== Financial Arrangements =====
Please add any special financial arrangements here. For example:
single-room fees, double-roome fees. Please consider using an
unordered list for this like so:
  * single room fee: 3000€
  * double room fee: 6000€
  * supplemenrary fee for Cello-players: 1500€

===== Location =====
Whatever.',
                 [ $projectName ]);

      $pagename = $this->projectWikiLink($projectName);
      $this->wikiRPC()->putPage(
        $pagename,
        $page, [
          "sum" => "Automatic CAFEVDB synchronization, project created",
          "minor" => true,
        ]);
  }

  /**
   * Delete the wiki page.
   *
   * @param array|Entities\Project $project
   *
   * @return int Page version deleted.
   */
  public function deleteProjectWikiPage($project):int
  {
    $wikiRPC = $this->wikiRPC();
    $projectName = $project['name'];
    $pagename = $this->projectWikiLink($projectName);

    list('version' => $pageVersion) = $wikiRPC->getPageInfo($pagename);
    $wikiRPC->putPage(
      $pagename,
      '', [
        "sum" => "Automatic CAFEVDB synchronization, project deleted.",
        "minor" => true,
      ]);
    return $pageVersion;
  }

  /**
   * Restore the wiki page to the given or lastest version.
   *
   * @param array|Entities\Project $project Database entity or "DTO array".
   *
   * @param null|int $version Page version.
   *
   * @return bool Execution status.
   */
  public function restoreProjectWikiPage($project, ?int $version = null):bool
  {
    $wikiRPC = $this->wikiRPC();
    $projectName = $project['name'];
    $pagename = $this->projectWikiLink($projectName);

    if (empty($version)) {
      $pageVersions = $wikiRPC->getPageVersions($pagename);
      $version = $pageVersions[0]['version']??null;
      if (empty($version)) {
        return false;
      }
    }
    $page = $wikiRPC->getPage($pagename, $version);
    $wikiRPC->putPage(
      $pagename, $page,
      [ "sum" => "Automatic CAFEVDB synchronization, undo project deletion.",
        "minor" => true ]);
    return true;
  }

  /**
   * Rename the associated wiki-pages after project rename.
   *
   * @param array|Entities\Project $newProject Array-like object,
   * "name" and "year" keys need to be present.
   *
   * @param array|Entities\Project $oldProject Array-like object,
   * "name" and "year" keys need to be present.
   *
   * @return void
   *
   * @see generateWikiOverview()
   */
  public function renameProjectWikiPage(mixed $newProject, mixed $oldProject):void
  {
    $wikiRPC = $this->wikiRPC();
    $oldName = $oldProject['name'];
    $newName = $newProject['name'];
    $oldPageName = $this->projectWikiLink($oldName);
    $newPageName = $this->projectWikiLink($newName);

    $wikiRPC->renamePage($oldPageName, $newPageName, fn($oldContent) => str_replace($oldName, $newName, $oldContent));

    $this->generateWikiOverview();
  }

  /**
   * @param mixed $articleId Article id or null or a template string for later
   * substitution.
   *
   * @param bool $editMode Whether the CMS url refers to the editor for the page.
   *
   * @return string The CMS url for the given articleid.
   */
  public function webPageCMSURL(mixed $articleId, bool $editMode = false):string
  {
    return $this->webPagesRPC()->redaxoURL($articleId, $editMode);
  }

  /**
   * @return bool Ping status.
   *
   * @see WebPagesRPC::ping()
   */
  public function pingWebPages():bool
  {
    return $this->webPagesRPC()->ping();
  }

  /**
   * Fetch fetch the article entities from the database
   *
   * @param mixed $projectOrId Entity or its id.
   *
   * @return ArrayCollection
   */
  public function fetchProjectWebPages(mixed $projectOrId)
  {
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      return null;
    }

    return $project->getWebPages();
  }

  /**
   * Fetch all articles known to the system.
   *
   * @param mixed $projectOrId Entity or its id.
   *
   * @return bool|array
   * ```
   * [
   *   'projectPages' => WEBPAGES,
   *   'otherPages' => WEBPAGES,
   * ]
   * ```
   */
  public function projectWebPages(mixed $projectOrId)
  {
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      return false;
    }

    $articleIds = [];
    foreach ($project->getWebPages() as $idx => $article) {
      $articleIds[$article['articleId']] = $idx;
    }

    $categories = [ [ 'id' => $this->getConfigValue('redaxoPreview'),
                      'name' => $this->l->t('Preview') ],
                    [ 'id' => $this->getConfigValue('redaxoRehearsals'),
                      'name' => $this->l->t('Rehearsals') ],
                    [ 'id' => $this->getConfigValue('redaxoArchive'),
                      'name' => $this->l->t('Archive') ],
                    [ 'id' => $this->getConfigValue('redaxoTrashbin'),
                      'name' => $this->l->t('Trashbin')] ];
    $projectPages = [];
    $otherPages = [];
    foreach ($categories as $category) {
      // Fetch all articles and remove those already registered
      $pages = $this->webPagesRPC()->articlesByName('.*', $category['id']);
      $this->logDebug("Projects: ".$category['id']);
      if (is_array($pages)) {
        foreach ($pages as $idx => $article) {
          $article['categoryName'] = $category['name'];
          if (isset($articleIds[$article['articleId']])) {
            $projectPages[] = $article;
          } else {
            $otherPages[] = $article;
          }
          $this->logDebug("Projects: ".print_r($article, true));
        }
      }
    }
    //$this->logInfo('PROJECT '.print_r($projectPages, true));
    //$this->logInfo('OTHER '.print_r($otherPages, true));
    return [
      'projectPages' => $projectPages,
      'otherPages' => $otherPages,
    ];
  }

  /**
   * Create and add a new web-page. The first one will have the name
   * of the project, subsequent one have a number attached like
   * Tango2014-5.
   *
   * @param mixed $projectOrId The project entity or its id.
   *
   * @param string $kind One of self::WEBPAGE_TYPE_CONCERT or self::WEBPAGE_TYPE_REHEARSALS.
   *
   * @return array
   *
   * @see WebPagesRPC::addArticle()
   */
  public function createProjectWebPage(mixed $projectOrId, string $kind = self::WEBPAGE_TYPE_CONCERT):array
  {
    $webPagesRPC = $this->webPagesRPC();
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      throw new Exception($this->l->t('Empty project.'));
    }
    $projectName = $project->getName();

    switch ($kind) {
      case self::WEBPAGE_TYPE_REHEARSALS:
        $prefix = $this->l->t('Rehearsals').' ';
        $category = $this->getConfigValue('redaxoRehearsals');
        $module = $this->getConfigValue('redaxoRehearsalsModule');
        break;
      default:
        // Don't care about the archive, new pages go to preview, and the
        // id will be unique even in case of a name clash
        $prefix = '';
        $category = $this->getConfigValue('redaxoPreview');
        $module = $this->getConfigValue('redaxoConcertModule');
        break;
    }

    // General page template
    $pageTemplate = $this->getConfigValue('redaxoSubPageTemplate');

    $pageName = $prefix.$projectName;
    try {
      $articles = $webPagesRPC->articlesByName($pageName.'(-[0-9]+)?', $category);
    } catch (Throwable $t) {
      throw new Exception(
        $this->l->t('Unable to fetch web-pages like "%s".', [ $pageName ]),
        $t->getCode(),
        $t);
    }

    $this->logDebug('ARTICLES ' . print_r($articles, true));

    $names = [];
    foreach ($articles as $article) {
      $names[] = $article['articleName'];
    }
    if (array_search($pageName, $names) !== false) {
      for ($i = 1;; ++$i) {
        if (array_search($pageName.'-'.$i, $names) === false) {
          // this will teminate ;)
          $pageName = $pageName.'-'.$i;
          break;
        }
      }
    }

    try {
      $article = $webPagesRPC->addArticle($pageName, $category, $pageTemplate);
    } catch (Throwable $t) {
      throw new Exception(
        $this->l->t('Unable to create web-page "%s".', [ $pageName ]),
        $t->getCode(),
        $t);
    }

    // just forget about the rest, we can't help it anyway if the
    // names are not unique
    $article = $article[0];

    $this->entityManager->beginTransaction();
    try {
      // insert into the db table to form the link
      $this->attachProjectWebPage($project, $article);
      $webPagesRPC->addArticleBlock($article['articleId'], $module);

      $this->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      throw new Exception(
        $this->l->t('Unable to attach article "%s".', [ $pageName ]),
        $t->getCode(),
        $t);
    }

    return $article;
  }

  /**
   * Delete a web page. This is implemented by moving the page to the
   * Trashbin category, leaving the real cleanup to a human being.
   *
   * @param mixed $projectOrId Project entity or its id.
   *
   * @param mixed $article Either an array or Entities\ProjectWebPage.
   *
   * @param bool $hard Whether to really delete the page.
   *
   * @return void
   */
  public function deleteProjectWebPage(mixed $projectOrId, mixed $article, bool $hard = false):void
  {
    $webPagesRPC = $this->webPagesRPC();
    $articleId = $article['articleId'];
    $categoryId = $article['categoryId'];

    $this->entityManager->beginTransaction();
    try {
      $this->detachProjectWebPage($projectOrId, $articleId);
      if ($hard) {
        $webPagesRPC->deleteArticle($articleId, $categoryId);
      } else {
        $trashCategory = $this->getConfigValue('redaxoTrashbin');
        // try moving to tash if the article exists in its category.
        if (!empty($webPagesRPC->articlesById([ $articleId ], $categoryId))) {
          /* $result = */$webPagesRPC->moveArticle($articleId, $trashCategory);
        }
      }
      $this->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      $projectId = $projectOrId instanceof Entities\Project ? $projectOrId->getId() : $projectOrId;
      throw new Exception($this->l->t('Failed removing web-page %d from project %d', [ $articleId, $projectId ]), $t->getCode(), $t);
    }
  }

  /**
   * Restore the web-pages previously deleted by deleteProjectWebPage()
   *
   * @param mixed $projectOrId Entity or its id.
   *
   * @param mixed $article Either an array or Entities\ProjectWebPage.
   *
   * @return void
   */
  public function restoreProjectWebPage(mixed $projectOrId, mixed $article):void
  {
    // $trashCategory = $this->getConfigValue('redaxoTrashbin');
    /* $result = */$this->webPagesRPC()->moveArticle($article['articleId'], $article['categoryId']);
    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebPage::class);
    /* $projectWebPage = */$webPagesRepository->attachProjectWebPage($projectOrId, $article);
  }

  /**
   * Detach a web page, but do not delete it. Meant as utility routine
   * for the UI (in order to correct wrong associations).
   *
   * @param mixed $projectOrId Entity or its id.
   *
   * @param int $articleId CMS article id.
   *
   * @return void
   */
  public function detachProjectWebPage(mixed $projectOrId, int $articleId):void
  {
    $this->entityManager->beginTransaction();
    try {
      $this->setDatabaseRepository(Entities\ProjectWebPage::class);
      $this->remove([ 'project' => $projectOrId, 'articleId' => $articleId  ]);
      $this->flush();

      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      $projectId = $projectOrId instanceof Entities\Project ? $projectOrId->getId() : $projectOrId;
      throw new Exception($this->l->t('Failed detaching web-page %d from project %d', [ $articleId, $projectId ]), $t->getCode(), $t);
    }
  }

  /**
   * Attach an existing web page to the project.
   *
   * @param mixed $projectOrId Project entity or its id.
   *
   * @param array $article Article description from CMS system.
   *
   * @return void
   */
  public function attachProjectWebPage(mixed $projectOrId, array $article):void
  {
    // Try to remove from trashbin, if appropriate.
    $trashCategory = $this->getConfigValue('redaxoTrashbin');
    if ($article['categoryId'] == $trashCategory) {
      if (stristr($article['articleName'], $this->l->t('Rehearsals')) !== false) {
        $destinationCategory = $this->getConfigValue('redaxoRehearsals');
      } else {
        $destinationCategory = $this->getConfigValue('redaxoPreview');
      }
      $articleId = $article['articleId'];
      $result = $this->webPagesRPC()->moveArticle($articleId, $destinationCategory);
      if ($result === false) {
        $this->logDebug("Failed moving " . $articleId . " to " . $destinationCategory);
      } else {
        $article['categoryId'] = $destinationCategory;
      }
    }

    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebPage::class);
    try {
      /* $projectWebPage = */$webPagesRepository->attachProjectWebPage($projectOrId, $article);
    } catch (Throwable $t) {
      $projectId = $projectOrId instanceof Entities\Project ? $projectOrId->getId() : $projectOrId;
      throw new Exception("Unable to attach web-page " . $articleId." for " . $projectId, $t->getCode(), $t);
    }
  }

  /**
   * Set the name of all registered web-pages to the canonical name,
   * project name given.
   *
   * @param mixed $projectOrId Database entity or its id.
   *
   * @param null|string $projectName Name of the project.
   *
   * @return bool Execution status.
   */
  public function nameProjectWebPages(mixed $projectOrId, ?string $projectName = null):bool
  {
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      return false;
    }
    if (empty($projectName)) {
      $projectName = $project->getName(); // @todo check if already set
    }

    $webPages = $project->getWebPages();

    $rehearsalsName = $this->l->t('Rehearsals');
    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebpage::class);

    $concertNr = 0;
    $rehearsalNr = 0; // should stay at zero
    foreach ($webPages as $article) {
      if (stristr($article['articleName'], $rehearsalsName) !== false) {
        $newName = $rehearsalsName.' '.$projectName;
        if ($rehearsalNr > 0) {
          $newName .= '-'.$rehearsalNr;
        }
        ++$rehearsalNr;
      } else {
        $newName = $projectName;
        if ($concertNr > 0) {
          $newName .= '-'.$concertNr;
        }
        ++$concertNr;
      }
      if ($this->webPagesRPC()->setArticleName($article['articleId'], $newName)) {
        // if successful then also update the data-base entry
        $webPagesRepository->mergeAttributes(
          [ 'articleId' => $article['articleId'] ],
          [ 'articleName' => $newName ]);
      }
    }

    return true;
  }

  /**
   * Search through the list of all projects and attach those with a
   * matching name. Something which should go to the "expert"
   * controls.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @return bool Execution status.
   */
  public function attachMatchingWebPages(mixed $projectOrId):bool
  {
    $webPagesRPC = $this->webPagesRPC();
    $project = $this->repository->ensureProject($projectOrId);
    $projectId = $project->getId();
    $projectName = $project->getName();

    $previewCat    = $this->getConfigtValue('redaxoPreview');
    $archiveCat    = $this->getConfigValue('redaxoArchive');
    $rehearsalsCat = $this->getConfigValue('redaxoRehearsals');

    $cntRe = '(?:-[0-9]+)?';

    $preview = $webPagesRPC->articlesByName($projectName.$cntRe, $previewCat);
    if (!is_array($preview)) {
      return false;
    }
    $archive = $webPagesRPC->articlesByName($projectName.$cntRe, $archiveCat);
    if (!is_array($archive)) {
      return false;
    }
    $rehearsals = $webPagesRPC->articlesByName($this->l->t('Rehearsals').' '.$projectName.$cntRe, $rehearsalsCat);
    if (!is_array($rehearsals)) {
      return false;
    }

    $articles = array_merge($preview, $archive, $rehearsals);

    foreach ($articles as $article) {
      // ignore any error
      $this->attachProjectWebPage($projectId, $article);
    }

    return true;
  }

  /**
   * Add the given musicians to the project.
   *
   * @param array $musicianIds Flat array of the data-base keys for Entities\Musician.
   *
   * @param int $projectId The project-id for the destination project.
   *
   * @todo Perhaps allow project-entities, i.e. "mixed $project"
   * instead of "int $projectId".
   *
   * @return array
   * ```
   * [
   *   'failed' => [
   *     [ 'id' => ID, 'notice' => REMARK, ... ],
   *     ...
   *   ],
   *   'added'  => [
   *      [ id' => ID, 'notice' => REMARK, 'sqlerror' => OPTIONAL ],
   *   ],
   * ]
   * ```
   */
  public function addMusicians(array $musicianIds, int $projectId)
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      throw new Exception($this->l->t('Unabled to retrieve project with id %d', $projectId));
    }

    $statusReport = [
      'added' => [],
      'failed' => [],
    ];
    foreach ($musicianIds as $id) {
      $status = null;
      if ($this->addOneMusician($id, $project, $status)) {
        $statusReport['added'][$id] = $status;
      } else {
        $statusReport['failed'][$id] = $status;
      }
    }

    return $statusReport;
  }

  /**
   * Add one given musician to the project.
   *
   * @param mixed $id Id of the musician, something understood by
   * MusiciansRepository::find().
   *
   * @param Entities\Project $project Database entity.
   *
   * @param array $status Status array by reference.
   *
   * @return bool Execution status.
   *
   * @see Repositories\MusiciansRepository::find()
   */
  private function addOneMusician(mixed $id, Entities\Project $project, ?array &$status):bool
  {
    $musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);

    $status = [];

    /** @var Entities\Musician $musician */
    $musician = $musiciansRepository->find($id);
    if (empty($musician)) {
      $status[] = [
        'id' => $id,
        'notice' => $this->l->t(
          'Unable to fetch the data for the musician-id "%s".', (string)$id),
      ];
      $this->logInfo('STATUS '. print_r($status, true));
      return false;
    }

    $musicianName = $musician->getPublicName(firstNameFirst: true);

    // check for already registered
    $exists = $project->getParticipants()->exists(function($key, Entities\ProjectParticipant $participant) use ($musician) {
      return $participant->getMusician()->getId() == $musician->getId();
    });
    if ($exists) {
      $status[$id][] = [
        'id' => $id,
        'notice' => $this->l->t(
          'The musician %s is already registered with project %s.',
          [ $musicianName, $project['name'] ]),
      ];
      $this->logInfo('MUSICIAN EXISTS');
      return false;
    }

    $this->entityManager->beginTransaction();
    try {

      // The musician exists and is not already registered, so add it.
      $participant = new Entities\ProjectParticipant(musician: $musician, project: $project);
      $this->persist($participant);

      // Try to make a likely default choice for the project instrument.
      $instrumentationNumbers = $project->getInstrumentationNumbers();

      $musicianInstruments = $musician->getInstruments();
      if ($musicianInstruments->isEmpty()) {
        $status[] = [
          'id' => $id,
          'notice' => $this->l->t('The musician %s does not play any instrument.', $musicianName),
        ];
      } else {

        // first find one instrument with best ranking
        $bestInstrument = null;
        $ranking = PHP_INT_MIN;
        /** @var Entities\MusicianInstrument $musicianInstrument */
        foreach ($musicianInstruments as $musicianInstrument) {
          $instrumentId = $musicianInstrument->getInstrument()->getId();
          $numbers =  $instrumentationNumbers->filter(function(Entities\ProjectInstrumentationNumber $number) use ($instrumentId) {
            return $number->getInstrument()->getId() == $instrumentId;
          });
          if ($numbers->isEmpty()) {
            continue;
          }
          $thisRanking = $musicianInstrument->getRanking();
          if ($thisRanking <= $ranking) {
            continue;
          }
          $ranking = $thisRanking;

          // if voice == UNVOICED exist and has a quantity > 0, use it (no
          // voice), otherwise use the one with the least registerd musicians
          // or the potentially less demanding (highest numbered) voice.
          $voice = Entities\ProjectInstrument::UNVOICED;
          $neededMost = PHP_INT_MIN;
          /** @var Entities\ProjectInstrumentationNumber $number */
          foreach ($numbers as $number) {
            if ($number->getVoice() == Entities\ProjectInstrument::UNVOICED) {
              if ($number->getQuantity() > 0) {
                $voice = Entities\ProjectInstrument::UNVOICED;
                break;
              }
              continue;
            }
            $needed = $number->getQuantity() - count($number->getInstruments());
            if ($needed > $neededMost ||
                ($needed == $neededMost &&  $number->getVoice() > $voice)) {
              $neededMost = $needed;
              $voice = $number->getVoice();
            }
          }

          $bestInstrument = [
            'instrument' => $musicianInstrument->getInstrument(),
            'voice' => $voice,
          ];
        }

        if (empty($bestInstrument)) {
          $status[] = [
            'id' => $id,
            'notice' => $this->l->t(
              'The musician %s does not play any instrument registered in the instrumentation list for the project %s.',
              [ $musicianName, $project->getName(), ]),
          ];
        } else {
          $projectInstrument = new Entities\ProjectInstrument(
            $project, $musician, $bestInstrument['instrument'], $bestInstrument['voice'],
          );
          $this->persist($projectInstrument);
        }
      }

      // $now = new DateTimeImmutable;
      // $musician->setUpdated($now); // should we?
      // $project->setUpdated($now); // should we?

      // Enable the cloud account when adding to the club-members or management project
      if ($project->getType() == ProjectType::PERMANENT) {
        $musician->setCloudAccountDisabled(null);
      }

      $this->flush();

      $this->entityManager->registerPreCommitAction(
        new Common\UndoableFolderCreate(
          fn() => $this->ensureParticipantFolder($project, $musician, dry: true),
          gracefully: true,
        ));

      /** @var Entities\ProjectParticipantField $field */
      foreach ($project->getParticipantFields() as $field) {
        if ($field->getDataType() != FieldDataType::CLOUD_FOLDER) {
          continue;
        }

        $readMe = Util::htmlToMarkDown($field->getTooltip());

        $this->entityManager->registerPreCommitAction(
          new Common\UndoableFolderCreate(
            fn() => $this->participantFieldsService->doGetFieldFolderPath($field, $musician),
            gracefully: true,
          )
        )->register(
          new Common\UndoableTextFileUpdate(
            fn() => $this->participantFieldsService->doGetFieldFolderPath($field, $musician) . Constants::PATH_SEP . Constants::README_NAME,
            $readMe,
            gracefully: true)
        );
      }
      // $this->entityManager->registerPreCommitAction(
      //   new Common\GenericUndoable(fn() => throw new Exception('SHOW STOPPER'))
      // );

      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      $status[] = [
        'id' => $id,
        'notice' => $this->l->t(
          'Adding the musician with id %d failed with exception %s',
          [ $id, $t->getMessage(), ]),
      ];
      return false;
    }

    $status[] = [
      'id' => $id,
      'notice' => $this->l->t(
        'The musician %s has been added to project %s.',
        [ $musicianName, $project['name'], ]),
    ];

    return true;
  }

  /**
   * Create a mailing list for the project and add the orchestra email address
   * as list-member.
   *
   * @param string|Entities\Project $projectOrId
   *
   * @return null|array List info.
   *
   * @see MailingListsService::getListInfo()
   */
  public function createProjectMailingList($projectOrId):?array
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);

    $listId = $project->getMailingListId();

    if ($listId === 'keep-empty' || $project->getType() == ProjectType::TEMPLATE) {
      $project->setMailingListId(null);
      $this->flush();
      return null;
    } elseif ($listId === 'create') {
      $listId = null;
      $project->setMailingListId(null);
    }

    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);

    if (!$listsService->isConfigured()) {
      $this->flush();
      return null;
    }

    if (empty($listId)) {
      $listId = strtolower($project->getName());
      $listId .= '.' . $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['domain']);
    }
    $this->logInfo('MAILING LIST ID ' . $listId);
    $new = false;
    if (empty($listsService->getListInfo($listId))) {
      $listsService->createList($listId);
      $new = true;
    }
    try {
      $listInfo = $listsService->getListInfo($listId);
      if (empty($listInfo)) {
        throw new RuntimeException(
          $this->l->t(
            'Unable to create project-mailing list "%1$s" for project "%1$s".',
            [ $listId, $project->getName() ]));
      }
      $displayName = $project->getName();
      $subjectPrefix = $displayName;
      $tag = $this->getConfigValue('bulkEmailSubjectTag');
      if (!empty($tag)) {
        $subjectPrefix = $tag . '-' . $subjectPrefix;
      }
      $subjectPrefix = '[' . $subjectPrefix . ']' . ' ';
      $configuration = [
        'display_name' => $displayName,
        'subject_prefix' => $subjectPrefix,
        'advertised' => 'False',
        'archive_policy' => 'private',
        'subscription_policy' => 'moderate',
        'preferred_language' =>  $this->getLanguage($this->appLocale()),
        'max_message_size' => 0,
        'max_num_recipients' => 0,
      ];
      $listsService->setListConfig($listId, $configuration);

      // Configure Owner
      $defaultOwner = $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['owner']);
      if (!empty($defaultOwner)) {
        if (empty($listsService->getSubscription($listId, $defaultOwner, MailingListsService::ROLE_OWNER))) {
          $listsService->subscribe($listId, email: $defaultOwner, role: MailingListsService::ROLE_OWNER);
        }
      }

      // Configure Moderator
      $defaultModerator = $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['moderator']);
      if (!empty($defaultModerator)) {
        if (empty($listsService->getSubscription($listId, $defaultModerator, MailingListsService::ROLE_MODERATOR))) {
          $listsService->subscribe($listId, email: $defaultModerator, role: MailingListsService::ROLE_MODERATOR);
        }
      }

      // subscribe the bulk-email-sender address if it is not already subscribed
      $bulkEmailFromAddress = $this->getConfigValue('emailfromaddress');
      $bulkEmailFromName = $this->getConfigValue('emailfromname');

      if (!empty($bulkEmailFromAddress)) {
        if (empty($listsService->getSubscription($listId, $bulkEmailFromAddress, MailingListsService::ROLE_MEMBER))) {
          $subscriptionData = [
            MailingListsService::SUBSCRIBER_EMAIL => $bulkEmailFromAddress,
            MailingListsService::MEMBER_DISPLAY_NAME => $bulkEmailFromName,
          ];
          $listsService->subscribe($listId, subscriptionData: $subscriptionData);
        }
      }

      // install the list templates ... this will silently overwrite, so we do
      // not need to check if they are already there.
      $listsService->installListTemplates($listId, MailingListsService::TEMPLATE_TYPE_PROJECTS);

    } catch (Throwable $t) {
      if ($new) {
        try {
          $listsService->deleteList($listId);
          $project->setMailingListId(null);
          $this->flush();
        } catch (Throwable $t1) {
          $this->logException($t1, 'Failure to clean-up failed list generation');
        }
      }
      throw new Exception($this->l->t('Unable to create mailing list "%s".', $listId), 0, $t);
    }
    $project->setMailingListId($listId);
    $this->flush();

    return $listInfo;
  }

  /**
   * @param int|Entities\Project $projectOrId Database entity or its id.
   *
   * @return void
   */
  public function deleteProjectMailingList($projectOrId):void
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);

    $listId = $project->getMailingListId();
    if (empty($listId)) {
      return;
    }

    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);

    if (!$listsService->isConfigured()) {
      return;
    }

    $listsService->deleteList($listId);
    $project->setMailingListId(null);
    $this->flush();
  }

  /**
   * Subscribe the participant to the mailing list if it is not already
   * subscribed.
   *
   * @param Entities\ProjectParticipant $participant The victim.
   *
   * @return null|boolean
   * - null if nothing could be done, no email, no list id, no rest service
   * - true if the musician has newly been added
   * - false if the musician was already subscribed to the mailing list
   */
  public function ensureMailingListSubscription(Entities\ProjectParticipant $participant):?bool
  {
    $listId = $participant->getProject()->getMailingListId();
    $musician = $participant->getMusician();
    $principalEmail = $musician->getEmail();

    if (empty($listId) || empty($principalEmail)) {
      return null;
    }

    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);
    if (!$listsService->isConfigured()) {
      return null;
    }

    $displayName = $participant->getPublicName(firstNameFirst: true);
    $memberStatus = $participant->getMusician()->getMemberStatus();
    $deliveryStatus = ($memberStatus == MemberStatus::CONDUCTOR
        || $memberStatus == MemberStatus::SOLOIST
      || $memberStatus == MemberStatus::TEMPORARY)
      ? MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER
      : MailingListsService::DELIVERY_STATUS_ENABLED;

    foreach ($musician->getEmailAddresses() as $emailEntity) {
      $emailAddress = $emailEntity->getAddress();

      if (!empty($listsService->getSubscription($listId, $emailAddress))) {
        if ($emailAddress == $principalEmail) {
          // leave the preferences alone in order to allow the user to modify
          // its preferences for the primary address.
          continue;
        }
        $listsService->setSubscriptionPreferences($listId, $emailAddress, [
          MailingListsService::MEMBER_DELIVERY_STATUS => MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER,
        ]);
      } else {
        // subscribe the email address, primary with delivery, others without
        $subscriptionData = [
          MailingListsService::SUBSCRIBER_EMAIL => $emailAddress,
          MailingListsService::MEMBER_DISPLAY_NAME => $displayName,
          MailingListsService::MEMBER_DELIVERY_STATUS => (($emailAddress == $principalEmail)
                                                          ? $deliveryStatus
                                                          : MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER),
          MailingListsService::SEND_WELCOME_MESSAGE => ($emailAddress == $principalEmail),
        ];
        $listsService->subscribe($listId, subscriptionData: $subscriptionData);
      }
    }

    return true;
  }

  /**
   * Unsubscribe the participant from the mailing list if it is subscribed
   *
   * @param Entities\ProjectParticipant $participant The victim.
   *
   * @return void
   */
  public function ensureMailingListUnsubscription(Entities\ProjectParticipant $participant):void
  {
    $listId = $participant->getProject()->getMailingListId();
    $musician = $participant->getMusician();
    $principalEmail = $musician->getEmail();

    if (empty($listId) || empty($principalEmail)) {
      return;
    }

    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);

    if (!$listsService->isConfigured()) {
      return;
    }

    $failedAddresses = [];
    foreach ($musician->getEmailAddresses() as $emailEntity) {
      $emailAddress = $emailEntity->getAddress();
      try {
        if (!empty($listsService->getSubscription($listId, $emailAddress))) {
          $listsService->unsubscribe($listId, $emailAddress, silent: $emailAddress != $principalEmail);
        }
      } catch (Throwable $t) {
        $failedAddresses[] = $emailAddress;
      }
    }
    if (!empty($failedAddresses)) {
      if ($participant->getRegistration()) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t(
            'Unable to unsubscribe the confirmed paticipant "%s" from the project mailing list.',
            $participant->getPublicName(true)),
          0, $t);
      } else {
        $this->logException($t, 'Mailing list service not reachable');
      }
    }
  }

  /**
   * Create the infra-structure to the given project. The function
   * assumes that the infrastructure does not yet exist and will
   * remove any existing parts of the infrastructure on error.
   *
   * @param array|int|Entities\Project $projectOrId Either the project entity
   * or an array with at least the entries for the id and name fields.
   *
   * @return void
   */
  public function createProjectInfraStructure($projectOrId):void
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);

    if (empty($project)) {
      throw new InvalidArgumentException($this->l->t('Unable to fetch the project for id "%s".', print_r($projectOrId, true)));
    }

    /** @var EventsService $eventsService */
    $eventsService = $this->appContainer()->get(EventsService::class);
    $eventsService->ensureSystemCategories();

    // not an entity-manager run-queue
    $runQueue = (clone $this->appContainer()->get(Common\UndoableRunQueue::class))
      ->register(new Common\GenericUndoable(
        function() use ($project) {
          $this->ensureProjectFolders($project);
        },
        function() use ($project) {
          $this->logInfo('TRY REMOVE FOLDERS FOR ' . $project->getId());
          $this->removeProjectFolders($project->getId());
        }))
      ->register(new Common\GenericUndoable(
        function() use ($project) {
          $this->generateProjectWikiPage($project->getId(), $project->getName());
          $this->generateWikiOverview();
        },
        function() use ($project) {
          $this->logInfo('TRY DELETE WIKI PAGE FOR ' . $project->getId());
          $this->deleteProjectWikiPage($project);
          $this->generateWikiOverview([ $project->getId(), ]);
        }
      ))
      ->register(new Common\GenericUndoable(
        function() use ($project) {
          // Generate an empty offline page template in the public web-space
          $this->createProjectWebPage($project, self::WEBPAGE_TYPE_CONCERT);
          $this->createProjectWebPage($project, self::WEBPAGE_TYPE_REHEARSALS);
        },
        function() use ($project) {
          $webPages = $project->getWebPages();
          foreach ($webPages as $page) {
            // ignore errors and really remove the mess
            $this->deleteProjectWebPage($project, $page, hard: true);
          }
        }
      ))
      ->register(new Common\GenericUndoable(
        function() use ($project) {
          $this->createProjectMailingList($project);
          return $project->getMailingListId();
        },
        function($listId) use ($project) {
          $this->deleteProjectMailingList($project);
        },
      ))
      ->register(new Common\GenericUndoable(
        function() use ($project) {
          $systemTagManager = $this->systemTagManager();
          try {
            $tag = $systemTagManager->createTag($project->getName(), userVisible: true, userAssignable: true);
          } catch (TagAlreadyExistsException $e) {
            $tag = $systemTagManager->getTag($project->getName(), userVisible: true, userAssignable: true);
          }
          return $tag;
        },
        function(ISystemTag $tag) use ($project) {
          $systemTagManager = $this->systemTagManager();
          try {
            $systemTagManager->deleteTags($tag->getId());
          } catch (TagNotFoundException $e) {
            // ignore, we tried to delete so ...
          }
        },
      ))
      ;

    try {
      $runQueue->executeActions();
    } catch (Exceptions\UndoableRunQueueException $qe) {
      $qe->getRunQueue()->executeUndo();
      $projectId = $project ? $project->getId() : ($projectOrId['id'] ?? -1);
      throw new RuntimeException($this->l->t('Unable to create the project-infrastructure for project id "%d".', $projectId), $qe->getCode(), $qe);
    }
  }

  /**
   * Create a new project with the given name and optional year.
   *
   * @param string $name The name of the new project.
   *
   * @param int|null $year Optional year for "temporary" projects.
   *
   * @param string|Types\EnumProjectTemporalType $type Type of the project.
   *
   * @return null|Entities\Project
   */
  public function createProject(string $name, ?int $year = null, mixed $type = ProjectType::TEMPORARY):?Entities\Project
  {
    /** @var Entities\Project $project */
    $project = null;

    $this->entityManager->beginTransaction();
    try {

      $year = $year?:\date('Y');

      $project = Entities\Project::create()
               ->setName($name)
               ->setYear($year)
               ->setType($type);

      $this->persist($project);

      $this->flush($project);

      $this->createProjectInfraStructure($project);

      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      throw new Exception(
        $this->l->t('Unable to create new project with name "%s".', $name),
        $t->getCode(),
        $t);
    }

    $this->eventDispatcher->dispatchTyped(
      new Events\ProjectCreatedEvent(
        $project->getId(),
        $project->getName(),
        $project->getYear(),
        $project->getType()));

    return $project;
  }

  /**
   * Delete the given project or id.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @return null|Entities\Project Returns null if project was
   * actually deleted, else the updated "soft-deleted" project instance.
   *
   * @todo Check for proper cascading.
   */
  public function deleteProject($projectOrId):?Entities\Project
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      throw new RuntimeException($this->l->t('Unable to find the project to delete (id = %d)', $projectOrId));
    }

    $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $projectId = $project->getId();
    $projectName = $project->getName();
    $projectYear = $project->getYear();
    $projectType = $project->getType();

    $softDelete  = count($project['payments']??[]) > 0;

    $preEvent =  new Events\BeforeProjectDeletedEvent(
      $projectId,
      $projectName,
      $projectYear,
      $projectType,
      $softDelete,
    );
    $postEvent = new Events\AfterProjectDeletedEvent(
      $projectId,
      $projectName,
      $projectYear,
      $projectType,
      $softDelete,
    );

    $this->entityManager
      ->registerPreFlushAction(new Common\GenericUndoable(
        function() use ($project) {
          $startTime = $this->getTimeStamp();
          $this->removeProjectFolders($project);
          // throttle deletion in order to have distinct file-names in the
          // trash-bin. Currently a file's MTIME in NextCloud has only
          // second resolution, so ...
          if ($this->getTimeStamp() < $startTime + 1) {
            time_sleep_until($startTime + 1);
          }
          $endTime = $this->getTimeStamp();
          return [ $startTime, $endTime ];
        },
        function($timeInterval) use ($project) {
          $this->restoreProjectFolders($project, $timeInterval);
        }))
      ->register(new Common\GenericUndoable(
        function() use ($project) {
          try {
            $pageVersion = $this->deleteProjectWikiPage($project);
          } catch (Throwable $t) {
            $this->logException($t, 'Unable to delete wiki-page for project ' . $project->getName());
            $pageVersion = null;
          }
          $this->generateWikiOverview([ $project->getId() ]);
          return $pageVersion;
        },
        function($pageVersion) use ($project) {
          $this->restoreProjectWikiPage($project, $pageVersion);
          $this->generateWikiOverview();
        }))
      ->register(new Common\GenericUndoable(
        function() use ($project) {
          $projectId = $project->getId();
          $webPages = $project->getWebPages();
          foreach ($webPages as $page) {
            // ignore errors
            $this->deleteProjectWebPage($projectId, $page);
          }
          return $webPages;
        },
        function($webPages) use ($project) {
          foreach ($webPages as $webPage) {
            try {
              $this->restoreProjectWebPage($project->getId(), $webPage);
            } catch (Throwable $t) {
              $this->logException($t, 'Unable to restore web-article with id ' . $webPage['articleId']);
            }
          }
        }));
    if ($softDelete) {
      $listId = $project->getMailingListId();
      if (!empty($listId)) {
        $this->entityManager->registerPreFlushAction(new Common\GenericUndoable(
          function() use ($listId) {
            /** @var MailingListsService $listsService */
            $listsService = $this->di(MailingListsService::class);
            $listsService->setListConfig($listId, 'emergency', true);
          },
          function() use ($listId) {
            /** @var MailingListsService $listsService */
            $listsService = $this->di(MailingListsService::class);
            $listsService->setListConfig($listId, 'emergency', false);
          }));
      }
    } else {
      $this->entityManager->registerPreFlushAction(new Common\GenericUndoable(
        function() use ($project) {
          $systemTagManager = $this->systemTagManager();
          try {
            $tag = $systemTagManager->getTag($project->getName(), userVisible: true, userAssignable: true);
            $systemTagManager->deleteTags($tag->getId());
          } catch (TagNotFoundException $e) {
            // ignore
          }
        },
        function() use ($project) {
          $systemTagManager = $this->systemTagManager();
          try {
            $systemTagManager->createTag($project->getName(), userVisible: true, userAssignable: true);
          } catch (TagAlreadyExistsException $e) {
            // ignore
          }
        }
      ));
    }

    $this->entityManager->beginTransaction();
    try {

      $this->eventDispatcher->dispatchTyped($preEvent);

      $this->entityManager->executePreFlushActions();

      if ($softDelete) {
        if (!$project->isDeleted()) {
          $this->remove($project, true);
        }
      } else {

        /** @var Entities\ProjectParticipant $participant */
        foreach ($project->getParticipants() as $participant) {
          $this->deleteProjectParticipant($participant);
        }
        $this->flush();

        /** @var Entities\ProjectParticipantField $participantField */
        foreach ($project->getParticipantFields() as $participantField) {
          $this->participantFieldsService->deleteField($participantField);
        }
        $this->flush();

        // @todo: use cascading to remove
        $deleteTables = [
          Entities\ProjectInstrument::class,
          Entities\ProjectWebPage::class,
          Entities\ProjectEvent::class,
        ];

        foreach ($deleteTables as $table) {
          $this->entityManager
            ->createQueryBuilder()
            ->delete($table, 't')
            ->where('t.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->execute();
          $this->flush();
        }

        if (!$project->isDeleted()) {
          $this->remove($project, true); // soft
        }
        $this->remove($project, true); // hard

      }

      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      $softDeleteableState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      throw new Exception(
        $this->l->t('Failed to remove project "%s", id "%d".', [ $project['name'], $project['id'] ]),
        $t->getCode(),
        $t);
    }

    if (!$softDelete) {
      try {
        $this->deleteProjectMailingList($project);
      } catch (Throwable $t) {
        $this->logException($t, 'Removing the mailing list of the project failed.');
      }
    }

    try {
      $this->eventDispatcher->dispatchTyped($postEvent);
    } catch (Throwable $t) {
      $this->logException($t, 'After project-deleted handlers failed.');
    }

    $softDeleteableState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $softDelete ? $project : null;
  }

  // /**
  //  * Copy the given project, including:
  //  *
  //  * - participant fields structure
  //  * - instrumentation numbers
  //  *
  //  * Everything else is not copied, in particular no project members
  //  * are copied over.
  //  *
  //  * @param int|Entities\Project $project
  //  *
  //  * @param null|string $newName The name of the copied project. If it
  //  * does not contain a year then the current year is used.
  //  *
  //  * @return null|Entities\Project Returns null if project was
  //  * actually deleted, else the updated "soft-deleted" project instance.
  //  *
  //  * @todo Check for proper cascading.
  //  */

  // UNUSED ATM

  // public function copyProject($projectOrId, ?string $newName = null):? Entities\Project
  // {
  //   /** @var Entities\Project $project */
  //   $project = $this->repository->ensureProject($projectOrId);
  //   if (empty($project)) {
  //     throw new RuntimeException($this->l->t('Unable to find the project to copy (id = %d)', $projectOrId));
  //   }

  //   // Road-map:
  //   // - sanitize name
  //   // - copy project and generate folders etc.
  //   // - copy instrumentation numbers
  //   // - copy participant fields

  //   if (empty($newName)) {
  //     $newName = $this->l->t('copy of %s', $project->getName());
  //     if ($project->getType() == ProjectType::TEMPORARY) {
  //       $newName = substr($newName, 0, -4) . date('Y');
  //     }
  //     $this->sanitizeName($projectName, $project->getType() == ProjectType::TEMPORARY);
  //   }
  //   list(, $newYear) = $this->yearFromName($newName);

  //   $this->entityManager->beginTransaction();
  //   try {

  //     /** @var Entities\Project $newProject */
  //     $newProject = clone $project;
  //     $newProject->setName($newName);
  //     $newProject->setYear($newYear);

  //     $this->persist($newProject);
  //     $this->flush();

  //     $this->createProjectInfraStructure($newProject);

  //     $this->entityManager->commit();
  //   } catch (Throwable $t)  {
  //     $this->logException($t);
  //     $this->entityManager->rollback();
  //     throw new Exception(
  //       $this->l->t('Unable to copy project "%1$s" to "%2$s".', [ $project->getName(), $newName ]),
  //       $t->getCode(),
  //       $t);
  //   }

  //   return null;
  // }

  /**
   * Rename the given project or id.
   *
   * @param int|Entities\Project $projectOrId Database entity or its id.
   *
   * @param string|array|Entities\Project $newData New project.
   *
   * @return Entities\Project Returns the renamed and persisted
   * entity.
   */
  public function renameProject(mixed $projectOrId, mixed $newData):Entities\Project
  {
    // This may be inside a transaction where the project-entity
    // already reflects the new state, so rather rely on the data.
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);
    $projectId = $project->getId();

    $oldName = $projectOrId['name'] ?? $project->getName();
    $oldYear = $projectOrId['year'] ?? $project->getYear();

    if (is_string($newData)) {
      list('name' => $newName, 'year' => $newYear) = $this->yearFromName($newData);
      $newName = $newName.($newYear?:'');
    } else {
      $newName = $newData['name'];
      $newYear = $newData['year'];
    }
    $newYear = $newYear ?: $project->getYear();

    if ($oldName == $newName && $oldYear == $newYear) {
      return $project; // nothing to do
    }

    // project-entity is changed during the update.
    $oldProject = [
      'id' => $projectId,
      'name' => $oldName,
      'year' => $oldYear,
      'type' => $project->getType(),
    ];
    $newProject = [
      'id' => $projectId,
      'name' => $newName,
      'year' => $newYear,
      'type' => $project->getType(),
    ];

    /** @var CloudUserConnectorService $cloudService */
    $cloudService = $this->di(CloudUserConnectorService::class);

    $this->entityManager
      ->registerPreFlushAction(new Common\GenericUndoable(
        function() use ($oldProject, $newProject) {
          $this->logInfo('OLD ' . print_r($oldProject, true) . ' NEW ' . print_r($newProject, true));
          $this->renameProjectFolder($newProject, $oldProject);
        },
        function() use ($oldProject, $newProject) {
          $this->renameProjectFolder($oldProject, $newProject);
        }
      ))
      ->register(new Common\GenericUndoable(
        function() use ($oldProject, $newProject) {
          $this->renameProjectWikiPage($newProject, $oldProject);
        },
        function() use ($oldProject, $newProject) {
          $this->renameProjectWikiPage($oldProject, $newProject);
        }
      ))
      ->register(new Common\GenericUndoable(
        function() use ($oldProject, $newProject) {
          $this->nameProjectWebPages($newProject['id'], $newProject['name']);
        },
        function() use ($oldProject, $newProject) {
          $this->nameProjectWebPages($oldProject['id'], $oldProject['name']);
        }
      ))
      ->register(new Common\GenericUndoable(
        function() use ($oldProject, $newProject) {
          $systemTagManager = $this->systemTagManager();
          try {
            $tag = $systemTagManager->getTag($oldProject['name'], userVisible: true, userAssignable: true);
            $systemTagManager->updateTag($tag->getId(), $newProject['name'], userVisible: true, userAssignable: true);
          } catch (TagNotFoundException $e) {
            $tag = $systemTagManager->createTag($newProject['name'], userVisible: true, userAssignable: true);
          }
          return $tag;
        },
        function(ISystemTag $tag) use ($oldProject, $newProject) {
          $systemTagManager = $this->systemTagManager();
          try {
            $systemTagManager->updateTag($tag->getId(), $oldProject['name'], userVisible: true, userAssignable: true);
          } catch (Throwable $e) {
            // ignore
          }
        },
      ))
      ->register(new Common\GenericUndoable(
        function() use ($oldProject, $newProject,) {
          $this->eventDispatcher->dispatchTyped(
            new Events\PreProjectUpdatedEvent($oldProject['id'], $oldProject, $newProject)
          );
        },
        function() use ($oldProject, $newProject) {
          $this->eventDispatcher->dispatchTyped(
            new Events\PreProjectUpdatedEvent($newProject['id'], $newProject, $oldProject)
          );
        }
      ));

    $this->entityManager
      ->registerPreCommitAction(new Common\GenericUndoable(
        function() use ($oldProject, $newProject, $cloudService) {
          $cloudService->synchronizeCloud();
          $this->eventDispatcher->dispatchTyped(
            new Events\PostProjectUpdatedEvent($oldProject['id'], $oldProject, $newProject)
          );
        },
        function() use ($oldProject, $newProject, $cloudService) {
          $cloudService->synchronizeCloud();
          $this->eventDispatcher->dispatchTyped(
            new Events\PostProjectUpdatedEvent($newProject['id'], $newProject, $oldProject)
          );
        }
      ));

    $listId = $project->getMailingListId();
    if (!empty($listId)) {
      /** @var MailingListsService $listsService */
      $listsService = $this->di(MailingListsService::class);
      $this->entityManager->registerPreFlushAction(new Common\GenericUndoable(
        function() use ($oldProject, $newProject) {
          $displayName = $newProject['name'];
          $tag = $this->getConfigValue('bulkEmailSubjectTag');
          if (!empty($tag)) {
            $displayName = $tag . '-' . $displayName;
          }
          $listsService->renameList($listId, displayName: $displayName);
        },
        function() use ($oldProject, $newProject) {
          $displayName = $oldProject['name'];
          $tag = $this->getConfigValue('bulkEmailSubjectTag');
          if (!empty($tag)) {
            $displayName = $tag . '-' . $displayName;
          }
          $listsService->renameList($listId, displayName: $displayName);
        }
      ));
    }

    $this->entityManager->beginTransaction();
    try {

      $this->entityManager->executePreFlushActions();

      $project->setName($newName);
      $project->setYear($newYear);

      $this->persist($project);
      $this->flush();
      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $project->setName($oldName); // needed ?
      $project->setYear($oldYear); // needed ?
      $this->entityManager->rollback();

      throw new Exception(
        $this->l->t(
          'Failed to rename project "%s", id "%d" to new name "%s".',
          [ $project['name'], $project['id'], $newName ]),
        $t->getCode(),
        $t);
    }

    return $project;
  }


  /**
   * Extract the year from the project name if present.
   *
   * @param string $projectName The name of the project.
   *
   * @return array
   *
   * ```
   * [ 'name' => NAME_WITHOUT_YEAR, 'year' => NULL_OR_YEAR ]
   * ```
   */
  public function yearFromName(string $projectName):array
  {
    $projectYear = substr($projectName, -4);
    if (preg_match('/^\d{4}$/', $projectYear) !== 1) {
      $projectYear = null;
    } else {
      $projectName = substr($projectName, 0, -4);
    }
    return [ 'name' => $projectName, 'year' => $projectYear ];
  }

  /**
   * Validate the name, no spaces, camel case, last four characters
   * may be digits of the form 20XX.
   *
   * @param string $projectName The name to validate.
   *
   * @param boolean $requireYear Year in last four characters is
   * mandatory.
   *
   * @return string
   */
  public function sanitizeName(string $projectName, bool $requireYear = false):string
  {
    list('name' => $projectName, 'year' => $projectYear) = $this->yearFromName($projectName);
    if ($requireYear && !$projectYear) {
      return false;
    }

    if ($projectName == strtoupper($projectName) && strlen($projectName) > 4) {
      $projectName = strtolower($projectName);
    }
    if ($projectName != strtoupper($projectName)) {
      $projectName = ucwords($projectName);
    }
    $projectName = preg_replace("/[^[:alnum:]]?[[:space:]]?/u", '', $projectName);

    if ($projectYear) {
      $projectName .= $projectYear;
    }
    return $projectName;
  }

  /**
   * Sanitize the given project, i.e. make sure its infrastructure is
   * up-to-date.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @throws Exception If something goes wrong.
   *
   * @todo This is not really implemented yet.
   *
   * @return void
   */
  public function sanitizeProject(mixed $projectOrId):void
  {
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      throw new  RuntimeException($this->l->t('Project not found.'));
    }
    $projectId = $project->getId();

    // do general stuff here

    if ($projectId == $this->getClubMembersProjectId()) {
      // do stuff here ?
    } elseif ($projectId == $this->getExecutiveBoardProjectId()) {
      // ensure the signature field

      $participantFields = $project->getParticipantFields();

      $signatureNames = $this->translationVariants(ConfigService::SIGNATURE_FIELD_NAME);

      /** @var Entities\ProjectParticipantField $field */
      $signatureFound = $participantFields->exists(function($id, $field) use ($signatureNames) {
        $fieldName = strtolower($field->getName());
        foreach ($signatureNames as $searchItem) {
          if ($fieldName == $searchItem
              && $field->getMultiplicity() == FieldMultiplicity::SIMPLE
              && $field->getDataType() == FieldDataType::DB_FILE) {
            return true;
          }
        }
        return false;
      });
      if (!$signatureFound) {
        $signatureField = $this->participantFieldsService->createField(
          $this->l->t(ucfirst(ConfigService::SIGNATURE_FIELD_NAME)),
          FieldMultiplicity::SIMPLE,
          FieldDataType::DB_FILE,
          $this->l->t(
            'Upload an image with the personal signature to simplify the generation of "official" mails.'
            . ' Preferably the image should have a transparent background and a resolution of 600 DPI or more.'));
        $this->persist($signatureField);
        $this->flush();
      }
    } else {
      throw new RuntimeException($this->l->t('Validation of projects not yet implemented.'));
    }
  }

  /**
   * Delete or disable a project participant.
   *
   * @param Entities\ProjectParticipant $participant The victim.
   *
   * @return void
   */
  public function deleteProjectParticipant(Entities\ProjectParticipant $participant):void
  {
    $publicName = $participant->getPublicName();
    $this->entityManager->beginTransaction();
    try {
      /** @var Entities\ProjectParticipant $participant */
      $this->remove($participant, true); // this should be soft-delete
      if ($participant->unused()) {
        $this->logInfo('Project participant ' . $participant->getPublicName() . ' is unused, issuing hard-delete');

        // For now rather cascade manually. Could also use ORM, of course ...
        /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
        foreach ($participant->getParticipantFieldsData() as $fieldDatum) {
          $this->remove($fieldDatum, true);
          if ($fieldDatum->unused()) {
            $this->remove($fieldDatum, true);
          }
        }
        $this->remove($participant, true); // this should be hard-delete

        $this->entityManager->registerPreCommitAction(
          new Common\UndoableFolderRemove(
            $this->ensureParticipantFolder($participant->getProject(), $participant->getMusician(), dry: true),
            gracefully: true,
            recursively: true,
          )
        );
      }
      $this->entityManager->registerPostCommitAction(
        new Common\GenericUndoable(fn() => $this->ensureMailingListUnsubscription($participant))
      );
      $this->entityManager->commit();
    } catch (Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      throw new Exception($this->l->t('Unable to remove participant "%1$s".', $publicName), 0, $t);
    }
  }
}

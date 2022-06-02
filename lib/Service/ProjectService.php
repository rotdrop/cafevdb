<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\EventDispatcher\IEventDispatcher;

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

use OCA\DokuWikiEmbedded\Service\AuthDokuWiki as WikiRPC;
use OCA\Redaxo4Embedded\Service\RPC as WebPagesRPC;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Common\UndoableRunQueue;
use OCA\CAFEVDB\Common\GenericUndoable;
use OCA\CAFEVDB\Common\UndoableFolderRename;
use OCA\CAFEVDB\Common\UndoableFileRename;
use OCA\CAFEVDB\Common\IUndoable;
use OCA\CAFEVDB\Events;

/**
 * General support service, kind of inconsequent glue between
 * Doctrine\ORM and CAFEVDB\PageRenderer.
 */
class ProjectService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const DBTABLE = 'Projects';

  const FOLDER_TYPE_PROJECT = 'project';
  const FOLDER_TYPE_PARTICIPANTS = 'participants';
  const FOLDER_TYPE_POSTERS = 'posters';
  const FOLDER_TYPE_BALANCE = 'balance';

  const WEBPAGE_TYPE_CONCERT = 'concert';
  const WEBPAGE_TYPE_REHEARSALS = 'rehearsals';

  private const PROJECT_FOLDER_CONFIG_KEYS = [
    ConfigService::PROJECTS_FOLDER,
    ConfigService::PROJECT_PARTICIPANTS_FOLDER,
    ConfigService::PROJECT_POSTERS_FOLDER,
    ConfigService::BALANCES_FOLDER,
  ];

  /** @var UserStorage */
  private $userStorage;

  /** @var ProjectParticipantFieldsService */
  private $participantFieldsService;

  /** @var WikiRPC */
  private $wikiRPCInstance;

  /** @var OCA\Redaxo4Embedded\Service\RPC */
  private $webPagesRPCInstance;

  /** @var ProjectsRepository */
  private $repository;

  /** @var IEventDispatcher */
  private $eventDispatcher;

  /** @var MusicianService */
  private $musicianService;

  public function __construct(
    ConfigService $configService
    , EntityManager $entityManager
    , UserStorage $userStorage
    , ProjectParticipantFieldsService $participantFieldsService
    , MusicianService $musicianService
    , IEventDispatcher $eventDispatcher
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->userStorage = $userStorage;
    $this->participantFieldsService = $participantFieldsService;
    $this->musicianService = $musicianService;
    $this->eventDispatcher = $eventDispatcher;

    try {
      $this->repository = $this->getDatabaseRepository(Entities\Project::class);
    } catch (\Throwable $t) {
      $this->logError('HELLO');
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

  /** Lazy getter for WikiRPC */
  private function wikiRPC():WikiRPC
  {
    if (empty($this->wikiRPCInstance)) {
      $this->wikiRPCInstance = $this->di(WikiRPC::class);
      $this->wikiRPCInstance->errorReporting(WikiRPC::ON_ERROR_THROW);
    }
    return $this->wikiRPCInstance;
  }

  private function WebPagesRPC():WebPagesRPC
  {
    if (empty($this->webPagesRPCInstance)) {
      $this->webPagesRPCInstance = $this->di(WebPagesRPC::class);
      $this->webPagesRPCInstance->errorReporting(WebPagesRPC::ON_ERROR_THROW);
    }
    return $this->webPagesRPCInstance;
  }

  /**
   * Generate an option table with all participants, suitable to be
   * staffed into Navigation::selectOptions(). This is a single
   * select, only one musician may be preselected. The key is the
   * musician id. The options are meant for a single-choice select
   * box.
   *
   * @param $projectId The id of the project to fetch the musician options from
   *
   * @param $projectName Optional project name, will be queried from
   * DB if not specified.
   *
   * @param $musicianId A pre-selected musician, defaults to null.
   */
  public function participantOptions($projectId, $projectName = null, $selectedMusicianId = -1)
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
   * Find the participant given by $projectId and $musicianId
   *
   * @return null|Entities\ProjectParticipant
   */
  public function findParticipant($projectId, $musicianId):?Entities\ProjectParticipant
  {
    return $this->getDatabaseRepository(Entities\ProjectParticipant::class)
                ->find([ 'project' => $projectId, 'musician' => $musicianId]);
  }

  public function projectOptions($criteria = [], $selectedProject = -1)
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
   * Fetch the instrumentation balance for the given project, @see \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\ProjectInstrumentationNumbersRepository::fetchInstrumentationBalance().
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param bool $sumVoices Sum-up all voices into a single instrument field
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
   */
  public function instrumentationBalance($projectOrId, $sumVoices = false)
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

  public function persistProject(Entities\Project $project)
  {
    $this->persist($project);
    $this->flush();
  }

  /**
   * Fetch the project-name name corresponding to $projectId.
   */
  public function fetchName($projectId)
  {
    $project = $this->repository->find($projectId);
    if ($project == null) {
      return null;
    }
    return $project->getName();
  }

  /**
   * Just return the collection of all projects.
   */
  public function fetchAll() {
    return $this->repository->findAll();
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
    $pathSep = UserStorage::PATH_SEP;
    $yearName = $pathSep.$project['year'].$pathSep.$project['name'];
    $sharedFolder = $pathSep.$this->getConfigValue(ConfigService::SHARED_FOLDER);
    $folders = $only ? [ $only ] : self::PROJECT_FOLDER_CONFIG_KEYS;

    $paths = [];
    foreach ($folders as $key) {
      switch ($key) {
      case ConfigService::PROJECTS_FOLDER:
      case ConfigService::PROJECT_PARTICIPANTS_FOLDER:
      case ConfigService::PROJECT_POSTERS_FOLDER:
        $projectsFolder = $sharedFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECTS_FOLDER).$yearName;
        if ($key == ConfigService::PROJECTS_FOLDER) {
          $paths[$key] = $projectsFolder;
          break;
        }
        $paths[$key] = $projectsFolder.$pathSep.$this->getConfigValue($key);
        break;
      case ConfigService::BALANCES_FOLDER:
        $paths[$key] = $sharedFolder
                     . $pathSep . $this->getConfigValue(ConfigService::FINANCE_FOLDER)
                     . $pathSep . $this->getConfigValue(ConfigService::BALANCES_FOLDER)
                     . $pathSep . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
                     . $yearName;
        break;
      }
    }
    return empty($only) ? $paths : $paths[$only];
  }

  /**
   * Check for the existence of the project folders. Returns an array
   * of folders (balance and general files).
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param string $projectName Name of the project.
   *
   * @param null|string $only If a string create only this folder, can
   * be one of self::FOLDER_TYPE_PROJECT, self::FOLDER_TYPE_BALANCE,
   * self::FOLDER_TYPE_PARTICIPANTS, self::FOLDER_TYPE_POSTERS
   *
   * @parm bool $dry Just create the name, but do not perform any
   * file-system operations.
   *
   * @return array Array of created folders.
   *
   */
  public function ensureProjectFolders($projectOrId, $projectName = null, $only = null, $dry= false)
  {
    $project = $this->repository->ensureProject($projectOrId);

    if (empty($project)) {
      throw new \Exception('CANNOT FIND PROJECT FOR ID ' . $projectOrId);
    }

    if (empty($projectName)) {
      $projectName = $project['name'];
    } else if ($projectName !== $project['name']) {
      return false;
    }

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $financeFolder = $this->getConfigValue(ConfigService::FINANCE_FOLDER);
    $participantsFolder = $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $postersFolder = $this->getConfigValue(ConfigService::PROJECT_POSTERS_FOLDER);
    $balancesFolder  = $this->getConfigValue(ConfigService::BALANCES_FOLDER);

    $projectPaths = [
      self::FOLDER_TYPE_PROJECT => [
        $sharedFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
      ],
      self::FOLDER_TYPE_BALANCE => [
        $sharedFolder,
        $financeFolder,
        $balancesFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
      ],
      self::FOLDER_TYPE_PARTICIPANTS => [
        $sharedFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
        $participantsFolder,
      ],
      self::FOLDER_TYPE_POSTERS => [
        $sharedFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
        $postersFolder,
      ],
    ];

    $returnPaths = [];
    foreach ($projectPaths as $key => $chain) {
      if (!empty($only) && $key != $only) {
        continue;
      }
      try {
        if (!$dry) {
          $this->userStorage->ensureFolderChain($chain);
        }
        $returnPaths[$key] = UserStorage::PATH_SEP.implode(UserStorage::PATH_SEP, $chain);
      } catch (\Throwable $t) {
        if (!empty($only)) {
          throw new \Exception(
            $this->l->t('Unable to ensure existence of folder "%s".',
                        UserStorage::PATH_SEP.implode(UserStorage::PATH_SEP, $chain)),
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

    $pathSep = UserStorage::PATH_SEP;
    $yearName = $pathSep.$project->getYear().$pathSep.$project->getName();

    $sharedFolder   = $pathSep.$this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $sharedFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECTS_FOLDER).$yearName;
    $participantsFolder = $projectsFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $postersFolder  = $projectsFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECT_POSTERS_FOLDER);
    $balanceFolder  = $sharedFolder
                    . $pathSep . $this->getConfigValue(ConfigService::FINANCE_FOLDER)
                    . $pathSep . $this->getConfigValue(ConfigService::BALANCES_FOLDER)
                    . $pathSep . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
                    . $yearName;

    $projectPaths = [
      // self::FOLDER_TYPE_POSTERS => $postersFolder,
      // self::FOLDER_TYPE_PARTICIPANTS => $participantsFolder,
      self::FOLDER_TYPE_PROJECT => $projectsFolder,
      self::FOLDER_TYPE_BALANCE => $balanceFolder,
    ];

    foreach($projectPaths as $key => $path) {
      $this->userStorage->delete($path);
      // throttle deletion in order to have distinct file-names in the
      // trash-bin. Currently a file's MTIME in NextCloud has only
      // second resolution, so ...
      sleep(1);
    }

    return true;
  }

  /**
   * Restore the folders for the given project in order to undelete or
   * during error recovery.
   *
   * @param Project|array $project Project entity or plain query result.
   *
   * @param null|array $timeInterval Unfortunately the time-stamp in
   * the trash bin is hard to get hold of. If $timeInterval is given,
   * only try to restore folders in the given interval.
   *
   * @return bool Status
   */
  public function restoreProjectFolders($project, ?array $timeInterval = null):bool
  {
    /** @var OCA\Files_Trashbin\Trash\TrashManager $trashManager */
    $trashManager = $this->di(\OCA\Files_Trashbin\Trash\TrashManager::class);

    $pathSep = UserStorage::PATH_SEP;
    $yearName = $pathSep.$project['year'].$pathSep.$project['name'];

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $sharedFolder.$pathSep.$this->getConfigValue(ConfigService::PROJECTS_FOLDER).$yearName;
    $balanceFolder  = $sharedFolder
                    . $pathSep . $this->getConfigValue(ConfigService::FINANCE_FOLDER)
                    . $pathSep . $this->getConfigValue(ConfigService::BALANCES_FOLDER)
                    . $pathSep . $this->getConfigValue(ConfigService::PROJECTS_FOLDER)
                    . $yearName;

    $projectPaths = [
      self::FOLDER_TYPE_PROJECT => $projectsFolder,
      self::FOLDER_TYPE_BALANCE => $balanceFolder,
    ];

    $count = 0;
    foreach ($projectPaths as $key => $path) {
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

    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $financeFolder = $this->getConfigValue(ConfigService::FINANCE_FOLDER);
    $balancesFolder  = $this->getConfigValue(ConfigService::BALANCES_FOLDER);

    $prefixPath = [
      self::FOLDER_TYPE_PROJECT => '/'.$sharedFolder.'/'.$projectsFolder.'/',
      self::FOLDER_TYPE_BALANCE => '/'.$sharedFolder.'/'.$financeFolder.'/'.$balancesFolder.'/'.$projectsFolder.'/',
    ];

    $returnPaths = [];
    foreach ($prefixPath as $key => $prefix) {

      $oldPath = $prefix.$oldProject['year']."/".$oldProject['name'];
      $newPrefixPath = $prefix.$newProject['year'];

      $newPath = $newPrefixPath.'/'.$newProject['name'];

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
            $this->ensureProjectFolders($newProject, null, $key /* only */));
        } catch (\Throwable $t) {
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
    list(self::FOLDER_TYPE_POSTERS => $path,) = $this->ensureProjectFolders($projectOrId, null, self::FOLDER_TYPE_POSTERS, $dry);
    return $path;
  }

  /**
   * Make sure the per-project per-participant folder exists for the
   * given project and musician.
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
  public function ensureParticipantFolder(Entities\Project $project, $musician, bool $dry = false)
  {
    list(self::FOLDER_TYPE_PARTICIPANTS => $parentPath,) = $this->ensureProjectFolders($project, null, self::FOLDER_TYPE_PARTICIPANTS, $dry);
    $userIdSlug = $this->musicianService->ensureUserIdSlug($musician);
    $participantFolder = $parentPath.UserStorage::PATH_SEP.$userIdSlug;
    if (!$dry) {
      $this->userStorage->ensureFolder($participantFolder);
    }
    return $participantFolder;
  }

  /**
   * More leight-weight construction of the participant folder, assuming
   * everything else is just is ok.
   */
  public function getParticipantFolder(Entities\Project $project, Entities\Musician $musician)
  {
    $sharedFolder   = $this->getConfigValue(ConfigService::SHARED_FOLDER);
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $participantsFolder = $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER);
    $userIdSlug = $musician->getUserIdSlug();
    if (empty($userIdSlug)) {
      return null;
    }

    return UserStorage::PATH_SEP . implode(
      UserStorage::PATH_SEP, [
        $sharedFolder,
        $projectsFolder,
        $project['year'],
        $project['name'],
        $participantsFolder,
        $userIdSlug,
      ]);
  }

  /**
   * e.g. passport-clausjustusheine.pdf
   * e.g. passport-claus-justus-heine.pdf
   */
  public function participantFilename(string $base, $project, $musicianOrSlug)
  {
    if ($musicianOrSlug instanceof Entities\Musician) {
      $userIdSlug = $this->musicianService->ensureUserIdSlug($musicianOrSlug);
    } else {
      $userIdSlug = $musicianOrSlug;
    }
    return $base.'-'.Util::dashesToCamelCase($userIdSlug, true, '_-.');
  }

  /**
   * Fetch the (encrypted) file for a field-datum and return path-info like
   * information for the file.
   *
   * @param Entities\ProjectParticipantFieldDatum $fieldDatum
   *
   * @return null|array
   * ```
   * [
   *   'file' => ENTITY,
   *   'baseName' => BASENAME, // generated, l10n
   '   'dirName' => DIRENAME, // generated, l10n
   *   'extension' => FILE EXTENSION, // from db-file
   *   'fileName' => FILENAME, // basename without extension
   *   'pathName' => DIRNAME/BASENAME,
   *   'dbFileName' => FILENAME_AS_STORED_IN_DB_TABLE,
   * ]
   * ```
   */
  public function participantFileInfo(Entities\ProjectParticipantFieldDatum $fieldDatum, bool $includeDeleted = false):?array
  {
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
    switch ($dataType) {
      case FieldDataType::DB_FILE:
        $fileId = (int)$fieldDatum->getOptionValue();
        $file = $this->findEntity(Entities\File::class, $fileId);
        break;
      case FieldDataType::SERVICE_FEE:
        $file = $fieldDatum->getSupportingDocument();
        break;
      default:
        return null;
    }
    if (empty($file)) {
      return null;
    }
    /** @var Entities\File $file */
    $dbFileName = $file->getFileName();
    $extension = pathinfo($dbFileName, PATHINFO_EXTENSION);
    $fieldName = $field->getName();

    if ($field->getMultiplicity() == FieldMultiplicity::SIMPLE) {
      // construct the file-name from the field-name
      $fileName = $this->participantFilename($fieldName, $fieldDatum->getProject(), $fieldDatum->getMusician());
      $dirName = null;
    } else {
      // construct the file-name from the option label if non-empty or the file-name of the DB-file
      $optionLabel = $fieldOption->getLabel();
      if (!empty($optionLabel)) {
        $fileName = $this->participantFilename($fieldOption->getLabel(), $fieldDatum->getProject(), $fieldDatum->getMusician());
      } else {
        $fileName = basename($dbFileName, '.' . $extension);
      }
      $dirName = $fieldName;
    }
    $baseName = $fileName . '.' . $extension;
    $pathName = empty($dirName) ? $baseName : $dirName . UserStorage::PATH_SEP . $baseName;
    return [
      'file' => $file,
      'baseName' => $baseName,
      'dirName' => $dirName,
      'extension' => $extension,
      'fileName' => $fileName,
      'pathName' => $pathName,
      'dbFileName' => $dbFileName,
    ];
  }

  /**
   * Rename all project-participants folders in order to reflect changes in
   * the user-id-slug (== user-name). This functions registers suitable
   * IUndoable actions with the EntityManager which are executed pre-commit.
   *
   * @param Entities\Musician $musician
   *
   * @param string $oldUserIdSlug
   *
   * @param string $newUserIdSlug
   *
   * @todo This alone does not suffice. Wie also have to rename a bunch of
   * per-project files.
   *
   */
  public function renameParticipantFolders(Entities\Musician $musician, string $oldUserIdSlug, string $newUserIdSlug)
  {
    $softDeleteableState = $this->disableFilter('soft-deleteable');

    /** @var Entities\ProjectParticipant $projectParticipant */
    foreach ($musician->getProjectParticipation() as $projectParticipant) {
      $project = $projectParticipant->getProject();

      $participantsFolder = $this->getProjectFolder($project, ConfigService::PROJECT_PARTICIPANTS_FOLDER);

      $newFolderPath = $participantsFolder . UserStorage::PATH_SEP . $newUserIdSlug;

      if (!empty($oldUserIdSlug)) {

        $oldFolderPath = $participantsFolder . UserStorage::PATH_SEP . $oldUserIdSlug;

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

          // @todo: this should be moved to the ProjectParticipantFieldsService
          if ($field->getMultiplicity() == FieldMultiplicity::SIMPLE) {
            // name based on field name
            $nameBase = $field->getUntranslatedName();
            $subDir = '';
          } else {
            // name based on option label
            $nameBase = $fieldDatum->getDataOption()->getUntranslatedLabel();
            $subDir = $field->getUntranslatedName() . UserStorage::PATH_SEP;
          }
          $oldFilePath =
            $oldFolderPath . UserStorage::PATH_SEP
            . $subDir
            . $this->participantFilename($nameBase, $project, $oldUserIdSlug)
            . '.' . $extension;

          $newFilePath =
            $oldFolderPath . UserStorage::PATH_SEP
            . $subDir
            . $this->participantFilename($nameBase, $project, $newUserIdSlug)
            . '.' . $extension;

          $this->logInfo('Try rename files ' . $oldFilePath . ' -> ' . $newFilePath);

          $this->entityManager->registerPreFlushAction(
            new UndoableFileRename($oldFilePath, $newFilePath, true /* gracefully */)
          );
        }

      } else {
        $oldFolderPath = null;
      }

      $this->logInfo('Try rename folders ' . $oldFolderPath . ' -> ' . $newFolderPath);

      // rename the project folder, this is the "easy" part
      $this->entityManager->registerPreFlushAction(
        new UndoableFolderRename($oldFolderPath, $newFolderPath, true /* gracefully */)
      );
    }

    $softDeleteableState && $this->enableFilter('soft-deleteable');
  }

  public function projectWikiLink($pageName)
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
   */
  public function generateWikiOverview(array $exclude = [])
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

    $projects = $this->repository->findBy(
      [ '!id' => $exclude ],
      [ 'year' => 'DESC', 'name' => 'ASC' ]);

    $page = "====== ".($this->l->t('Projects of %s', [$orchestra]))."======\n\n";

    $year = -1;
    foreach ($projects as $project) {
      if ($project['year'] != $year) {
        $year = $project['year'];
        $page .= "\n==== ".$year."====\n";
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

    return $this->wikiRPC()->putPage($pageName, $page,
                                     [ "sum" => "Automatic CAFEVDB synchronization",
                                       "minor" => true ]);
  }

  /**Generate an almost empty project page. This spares people the
   * need to click on "new page".
   *
   * - We insert a proper title heading
   *
   * - We insert a sub-title "Contacts"
   *
   * - We insert a sub-title "Financial Arrangements"
   *
   * - We insert a sub-title "Location"
   */
  public function generateProjectWikiPage($projectId, $projectName)
  {
    $page = $this->l->t('====== Project %s ======

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
      $this->wikiRPC()->putPage($pagename, $page,
                                [ "sum" => "Automatic CAFEVDB synchronization, project created",
                                  "minor" => true ]);
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
    $wikiRPC->putPage($pagename, '',
                      [ "sum" => "Automatic CAFEVDB synchronization, project deleted.",
                        "minor" => true ]);
    return $pageVersion;
  }

  /**
   * Restore the wiki page to the given or lastest version.
   *
   * @param array|Entities\Project $project
   *
   * @param null|int $version
   */
  public function restoreProjectWikiPage($project, ?int $version = null)
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
   */
  public function renameProjectWikiPage($newProject, $oldProject)
  {
    $wikiRPC = $this->wikiRPC();
    $oldName = $oldProject['name'];
    $newName = $newProject['name'];
    $oldPageName = $this->projectWikiLink($oldName);
    $newPageName = $this->projectWikiLink($newName);

    $oldPage = '  * ' . $this->l->t('%1$s has been renamed to %2$s.', [ $oldPageName, '[['.$newPageName.']]' ])."\n";
    $newPage = $wikiRPC->getPage($oldPageName);

    $this->logInfo('OLD '.$oldPageName.' / '.$oldPage);
    $this->logInfo('NEW '.$newPageName.' / '.$newPage);

    // replace the old project name in the old project page
    $newPage = str_replace($oldName, $newName, $newPage);

    if ($newPage) {
      $wikiRPC->putPage(
        $newPageName, $newPage,
        [ "sum" => "Automatic CAFEVDB page renaming", "minor" => false ]);
      // Generate stuff if there is an old page
      $wikiRPC->putPage(
        $oldPageName, $oldPage,
        [ "sum" => "Automatic CAFEVDB page renaming", "minor" => false ]);
    }

    $this->generateWikiOverview();
  }

  /**
   */
  public function webPageCMSURL($articleId, $editMode = false)
  {
    return $this->webPagesRPC()->redaxoURL($articleId, $editMode);
  }

  public function pingWebPages()
  {
    return $this->webPagesRPC()->ping();
  }

  /**
   * Fetch fetch the article entities from the database
   *
   * @return ArrayCollection
   */
  public function fetchProjectWebPages($projectId)
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      return null;
    }

    return $project->getWebPages();
  }

  /**
   * Fetch all articles known to the system.
   *
   * @return array
   * ```
   * [
   *   'projectPages' => WEBPAGES,
   *   'otherPages' => WEBPAGES,
   * ]
   * ```
   */
  public function projectWebPages($projectId)
  {
    $project = $this->repository->find($projectId);
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
   * @param $projectId Id of the project
   *
   * @param $kind One of self::WEBPAGE_TYPE_CONCERT or self::WEBPAGE_TYPE_REHEARSALS
   *
   * @param $handle Optional active data-base handle.
   */
  public function createProjectWebPage($projectId, $kind = self::WEBPAGE_TYPE_CONCERT)
  {
    $webPagesRPC = $this->webPagesRPC();
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      throw new \Exception($this->l->t('Empty project.'));
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
    $pageTemplate = $this->getConfigValue('redaxoTemplate');

    $pageName = $prefix.$projectName;
    try {
      $articles = $webPagesRPC->articlesByName($pageName.'(-[0-9]+)?', $category);
    } catch (\Throwable $t)  {
      throw new \Exception(
        $this->l->t('Unable to fetch web-pages like "%s".', [ $pageName ]),
        $t->getCode(),
        $t);
    }

    $names = array();
    foreach ($articles as $article) {
      $names[] = $article['articleName'];
    }
    if (array_search($pageName, $names) !== false) {
      for ($i = 1; ; ++$i) {
        if (array_search($pageName.'-'.$i, $names) === false) {
          // this will teminate ;)
          $pageName = $pageName.'-'.$i;
          break;
        }
      }
    }

    try {
      $article = $webPagesRPC->addArticle($pageName, $category, $pageTemplate);
    } catch (\Throwable $t) {
      throw new \Exception(
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
      $this->attachProjectWebPage($projectId, $article);
      $webPagesRPC->addArticleBlock($article['articleId'], $module);

      $this->flush();
      $this->entityManager->commit();
    } catch (\Throwable $t)  {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception(
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
   * @param int $projectId
   *
   * @param mixed $article Either an array or Entities\ProjectWebPage
   */
  public function deleteProjectWebPage($projectId, $article)
  {
    $webPagesRPC = $this->webPagesRPC();
    $articleId = $article['articleId'];
    $categoryId = $article['categoryId'];

    $this->entityManager->beginTransaction();
    try {
      $this->detachProjectWebPage($projectId, $articleId);
      $trashCategory = $this->getConfigValue('redaxoTrashbin');

      // try moving to tash if the article exists in its category.
      if (!empty($webPagesRPC->articlesById([ $articleId ], $categoryId))) {
        $result = $webPagesRPC->moveArticle($articleId, $trashCategory);
      }

      $this->flush();
      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception($this->l->t('Failed removing web-page %d from project %d', [ $articleId, $projectId ]), $t->getCode(), $t);
    }
  }

  /**
   * Restore the web-pages previously deleted by deleteProjectWebPage()
   *
   * @param int $projectId
   *
   * @param mixed $article Either an array or Entities\ProjectWebPage
   */
  public function restoreProjectWebPage($projectId, $article)
  {
    $trashCategory = $this->getConfigValue('redaxoTrashbin');
    $result = $this->webPagesRPC()->moveArticle($article['articleId'], $article['categoryId']);
    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebPage::class);
    $projectWebPage = $webPagesRepository->attachProjectWebPage($projectId, $article);
  }

  /**
   * Detach a web page, but do not delete it. Meant as utility routine
   * for the UI (in order to correct wrong associations).
   */
  public function detachProjectWebPage($projectId, $articleId)
  {
    $this->entityManager->beginTransaction();
    try {
      $this->setDatabaseRepository(Entities\ProjectWebPage::class);
      $this->remove([ 'project' => $projectId, 'articleId' => $articleId  ]);
      $this->flush();

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception($this->l->t('Failed detaching web-page %d from project %d', [ $articleId, $projectId ]), $t->getCode(), $t);
    }
  }

  /**
   * Attach an existing web page to the project.
   *
   * @param $projectId Project Id.
   *
   * @param $article Article array as returned from articlesByName().
   *
   */
  public function attachProjectWebPage($projectId, $article)
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
        $this->logDebug("Failed moving ".$articleId." to ".$destinationCategory);
      } else {
        $article['categoryId'] = $destinationCategory;
      }
    }

    $webPagesRepository = $this->entityManager->getRepository(Entities\ProjectWebPage::class);
    try {
      $projectWebPage = $webPagesRepository->attachProjectWebPage($projectId, $article);
    } catch (\Throwable $t) {
      throw new \Exception("Unable to attach web-page ".$articleId." for ".$projectId, $t->getCode(), $t);
    }
  }

  /**
   * Set the name of all registered web-pages to the canonical name,
   * project name given.
   */
  public function nameProjectWebPages($projectId, $projectName = null)
  {
    $project = $this->repository->find($projectId);
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
   */
  public function attachMatchingWebPages($projectOrId)
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

    //\OCP\Util::writeLog(Config::APP_NAME, "Web pages for ".$projectName.": ".print_r($articles, true), \OCP\Util::DEBUG);

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
  public function addMusicians(array $musicianIds, $projectId)
  {
    $project = $this->repository->find($projectId);
    if (empty($project)) {
      throw new \Exception($this->l->t('Unabled to retrieve project with id %d', $projectId));
    }

    $statusReport = [
      'added' => [],
      'failed' => [],
    ];
    foreach ($musicianIds as $id) {
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
   */
  private function addOneMusician($id, Entities\Project $project, &$status)
  {
    $musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);

    $status = [];

    $musician = $musiciansRepository->find($id);
    if (empty($musician)) {
      $status[] = [
        'id' => $id,
        'notice' => $this->l->t(
          'Unable to fetch the data for the musician-id "%s".', (string)$id),
      ];
      $this->logInfo('STATUS '. print_r($status,true));
      return false;
    }

    $musicianName = $musician['firstName'].' '.$musician['surName'];

    // check for already registered
    $exists = $project['participants']->exists(function($key, $participant) use ($musician) {
      return $participant['musician']['id'] == $musician['id'];
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
      $participant = Entities\ProjectParticipant::create();
      $participant['project'] = $project;
      $participant['musician'] = $musician;
      $this->persist($participant);

      // Try to make a likely default choice for the project instrument.
      $instrumentationNumbers = $project['instrumentationNumbers'];

      if ($musician['instruments']->isEmpty()) {
        $status[] = [
          'id' => $id,
          'notice' => $this->l->t('The musician %s does not play any instrument.', $musicianName),
        ];
      } else {

        // first find one instrument with best ranking
        $bestInstrument = null;
        $ranking = PHP_INT_MIN;
        foreach ($musician['instruments'] as $musicianInstrument) {
          $instrumentId = $musicianInstrument['instrument']['id'];
          $numbers =  $instrumentationNumbers->filter(function($number) use ($instrumentId) {
            return $number['instrument']['id'] == $instrumentId;
          });
          if ($numbers->isEmpty()) {
            continue;
          }
          if ($musicianInstrument['ranking'] <= $ranking) {
            continue;
          }
          $ranking = $musicianInstrument['ranking'];

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
            $needed = $number['quantity'] - count($number['instruments']);
            if ($needed > $neededMost ||
                ($needed == $neededMost &&  $number['voice'] > $voice)) {
              $neededMost = $needed;
              $voice = $number['voice'];
            }
          }

          $bestInstrument = [
            'instrument' => $musicianInstrument['instrument'],
            'voice' => $voice,
          ];
        }

        if (empty($bestInstrument)) {
          $status[] = [
            'id' => $id,
            'notice' => $this->l->t(
              'The musician %s does not play any instrument registered in the instrumentation list for the project %s.',
              [ $musicianName, $project['name'], ]),
          ];
        } else {
          $projectInstrument = Entities\ProjectInstrument::create();
          $projectInstrument['project'] = $project;
          $projectInstrument['musician'] = $musician;
          $projectInstrument['instrument'] = $bestInstrument['instrument'];
          $projectInstrument['voice'] = $bestInstrument['voice'];
          $this->persist($projectInstrument);
        }
      }

      $musician['updated'] = $project['updated'] = new \DateTime;

      $this->flush();

      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }

      $status[] = [
        'id' => $id,
        'notice' => $this->l->t(
          'Adding the musician with id %d failed with exception %s',
          [ $id, $t->getMessage(), ]),
      ];
      return false;
    }

    // make sure the participant sub-folder exists also
    $this->ensureParticipantFolder($project, $musician);

    $status[] = [
      'id' => $id,
      'notice' => $this->l->t('The musician %s has been added to project %s.',
                              [ $musicianName, $project['name'], ]),
    ];

    return true;
  }

  /**
   * Create a mailing list for the project and add the orchestra email address
   * as list-member.
   *
   * @param string|Entities\Project $projectOrId
   */
  public function createProjectMailingList($projectOrId)
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);

    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);

    if (!$listsService->isConfigured()) {
      return;
    }

    $listId = $project->getMailingListId();
    if (empty($listId)) {
      $listId = strtolower($project->getName());
      $listId .= '.' . $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['domain']);
    }
    $new = false;
    if (empty($listsService->getListInfo($listId))) {
      $listsService->createList($listId);
      $new = true;
    }
    try {
      $listInfo = $listsService->getListInfo($listId);
      if (empty($listInfo)) {
        throw new \RuntimeException(
          $this->l->t('Unable to create project-mailing list "%1$s" for project "%1$s".',
                      [ $listId, $project->getName() ]));
      }
      $displayName = $project->getName();
      $tag = $this->getConfigValue('bulkEmailSubjectTag');
      if (!empty($tag)) {
        $displayName = $tag . '-' . $displayName;
      }
      $configuration = [
        'display_name' => $displayName,
        'advertised' => 'False',
        'archive_policy' => 'private',
        'subscription_policy' => 'moderate',
        'preferred_language' =>  $this->getLanguage($this->appLocale()),
      ];
      $listsService->setListConfig($listId, $configuration);
      $defaultOwner = $this->getConfigValue(ConfigService::MAILING_LIST_CONFIG['owner']);
      if (!empty($defaultOwner)) {
        if (empty($listsService->getSubscription($listId, $defaultOwner, MailingListsService::ROLE_OWNER))) {
          $listsService->subscribe($listId, email: $defaultOwner, role: MailingListsService::ROLE_OWNER);
        }
      }
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

    } catch (\Throwable $t) {
      if ($new) {
        try {
          $listsService->deleteList($listId);
          $project->setMailingListId(null);
          $this->flush();
        } catch (\Throwable $t1) {
          $this->logException($t1, 'Failure to clean-up failed list generation');
        }
      }
      throw new \Exception($this->l->t('Unable to create mailing list "%s".', $listId), 0, $t);
    }
    $project->setMailingListId($listId);
    $this->flush();

    return $listInfo;
  }

  public function deleteProjectMailingList($projectOrId)
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
   * @return null|boolean
   * - null if nothing could be done, no email, no list id, no rest service
   * - true if the musician has newly been added
   * - false if the musician was already subscribed to the mailing list
   */
  public function ensureMailingListSubscription(Entities\ProjectParticipant $participant):?bool
  {
    $listId = $participant->getProject()->getMailingListId();
    $email = $participant->getMusician()->getEmail();

    if (empty($listId) || empty($email)) {
      return null;
    }

    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);
    if (!$listsService->isConfigured()) {
      return null;
    }

    if (!empty($listsService->getSubscription($listId, $email))) {
      return false;
    }

    $displayName = $participant->getMusician()->getPublicName(firstNameFirst: true);

    $memberStatus = $participant->getMusician()->getMemberStatus();

    $deliveryStatus = ($memberStatus == MemberStatus::CONDUCTOR
        || $memberStatus == MemberStatus::SOLOIST
      || $memberStatus == MemberStatus::TEMPORARY)
      ? MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER
      : MailingListsService::DELIVERY_STATUS_ENABLED;

    $subscriptionData = [
      MailingListsService::SUBSCRIBER_EMAIL => $email,
      MailingListsService::MEMBER_DISPLAY_NAME => $displayName,
      MailingListsService::MEMBER_DELIVERY_STATUS => $deliveryStatus,
      // MailingListsService::MEMBER_DELIVERY_STATUS => MailingListsService::DELIVERY_STATUS_DISABLED_BY_USER,
    ];

    $listsService->subscribe($listId, subscriptionData: $subscriptionData);

    return true;
  }

  /**
   * Unsubscribe the participant from the mailing list if it is subscribed
   */
  public function ensureMailingListUnsubscription(Entities\ProjectParticipant $participant)
  {
    $listId = $participant->getProject()->getMailingListId();
    $email = $participant->getMusician()->getEmail();

    if (empty($listId) || empty($email)) {
      return;
    }

    /** @var MailingListsService $listsService */
    $listsService = $this->di(MailingListsService::class);

    if (!$listsService->isConfigured()) {
      return;
    }

    if (!empty($listsService->getSubscription($listId, $email))) {
      $listsService->unsubscribe($listId, $email);
    }
  }

  /**
   * Create the infra-structure to the given project. The function
   * assumes that the infrastructure does not yet exist and will
   * remove any existing parts of the infrastructure on error.
   *
   * @param array|int|Entities\Project $projectOdId Either the project entity
   * or an array with at least the entries for the id and name fields.
   *
   */
  public function createProjectInfraStructure($projectOrId)
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);

    // not an entity-manager run-queue
    $runQueue = (new UndoableRunQueue($this->Logger(), $this->l10n()))
      ->register(new GenericUndoable(
        function() use ($project) {
          $projectPaths = $this->ensureProjectFolders($project->getId(), $project->getName());
        },
        function() use ($project) {
          $this->logInfo('TRY REMOVE FOLDERS FOR ' . $project->getId());
          $this->removeProjectFolders($project->getId());
        }))
      ->register(new GenericUndoable(
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
      ->register(new GenericUndoable(
        function() use ($project) {
          // Generate an empty offline page template in the public web-space
          $this->createProjectWebPage($project->getId(), self::WEBPAGE_TYPE_CONCERT);
          $this->createProjectWebPage($project->getId(), self::WEBPAGE_TYPE_REHEARSALS);
        },
        function() use ($project) {
           $webPages = $project->getWebPages();
           foreach ($webPages as $page) {
             // ignore errors
             $this->deleteProjectWebPage($project->getId(), $page);
           }
        }
      ))
      ->register(new GenericUndoable(
        function() use ($project) {
          $this->createProjectMailingList($project);
          return $project->getMailingListId();
        },
        function($listId) use ($project) {
          $this->deleteProjectMailingList($project);
        },
      ));

    try {
      $runQueue->executeActions();
    } catch (Exceptions\UndoableRunQueueException $qe) {
      $qe->getRunQueue()->executeUndo();
      throw new \RuntimeException($this->l->t('Unable to create the project-infrastructure for project id "%d".', $project->getId()), $qe->getCode(), $qe);
    }
  }

  /**
   * Create a new project with the given name and optional year.
   *
   * @param string $name The name of the new project.
   *
   * @param int|null $year Optional year for "temporary" projects.
   *
   * @param mixed $type Type of the project, @see Types\EnumProjectTemporalType
   */
  public function createProject(string $name, ?int $year = null, $type = ProjectType::TEMPORARY):?Entities\Project
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
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception(
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
   * @param int|Entities\Project $project
   *
   * @return null|Entities\Project Returns null if project was
   * actually deleted, else the updated "soft-deleted" project instance.
   *
   * @todo Check for proper cascading.
   */
  public function deleteProject($projectOrId):? Entities\Project
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      throw new \RuntimeException($this->l->t('Unable to find the project to delete (id = %d)', $projectOrId));
    }

    $projectId = $project->getId();
    $projectName = $project->getName();

    $softDelete  = count($project['payments']??[]) > 0;

    $this->entityManager
      ->registerPreFlushAction(new GenericUndoable(
        function() use ($project) {
          $startTime = $this->getTimeStamp();
          $this->removeProjectFolders($project);
          $endTime = $this->getTimeStamp();
          return [ $startTime, $endTime ];
        },
        function($timeInterval) use ($project) {
          $this->restoreProjectFolders($project, $timeInterval);
        }))
      ->register(new GenericUndoable(
        function() use ($project) {
          try {
            $pageVersion = $this->deleteProjectWikiPage($project);
          } catch (\Throwable $t) {
            $this->logException($t, 'Unable to delete wiki-page for project ' . $project->getName());
            $pageVersion = null;
          }
          $this->generateWikiOverview([ $projectId ]);
          return $pageVersion;
        },
        function($pageVersion) use ($project) {
          $this->restoreProjectWikiPage($project, $pageVersion);
          $this->generateWikiOverview();
        }))
      ->register(new GenericUndoable(
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
            } catch (\Throwable $t) {
              $this->logException($t, 'Unable to restore web-article with id ' . $webPage['articleId']);
            }
          }
        }));
    if ($softDelete && !empty($listId = $project->getMailingListId())) {
      $this->entityManager->registerPreFlushAction(new GenericUndoable(
        function() use ($listId) {
          /** @var MailingListsService $listsService */
          $listService = $this->di(MailingListsService::class);
          $listsService->setListConfig($listId, 'emergency', true);
        },
        function() use ($listId) {
          /** @var MailingListsService $listsService */
          $listService = $this->di(MailingListsService::class);
          $listsService->setListConfig($listId, 'emergency', false);
        }));
    }

    $this->entityManager->beginTransaction();
    try {

      $this->eventDispatcher->dispatchTyped(
        new Events\BeforeProjectDeletedEvent($project->getId(), $project->getName(), $softDelete));

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

        /** @var Entities\ProjectParticipantField $participantField */
        foreach ($project->getParticipantFields() as $participantField) {
          $this->participantFieldsService->deleteField($participantField);
        }

        // @todo: use cascading to remove
        $deleteTables = [
          Entities\ProjectInstrument::class,
          Entities\ProjectWebPage::class,
          Entities\ProjectEvent::class,
        ];

        $triggerResult = true;
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
    } catch (\Throwable $t) {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception(
        $this->l->t('Failed to remove project "%s", id "%d".',
                    [ $project['name'], $project['id'] ]),
        $t->getCode(),
        $t);
    }

    if (!$softDelete) {
      try {
        $this->deleteProjectMailingList($project);
      } catch (\Throwable $t) {
        $this->logException($t, 'Removing the mailing list of the project failed.');
      }
    }

    try {
      $this->eventDispatcher->dispatchTyped(
        new Events\AfterProjectDeletedEvent($project->getId(), $project->getName(), $softDelete));
    } catch (\Throwable $t) {
      $this->logException($t, 'After project-deleted handlers failed.');
    }

    return $softDelete ? $project : null;
  }

  /**
   * Copy the given project, including:
   *
   * - participant fields structure
   * - instrumentation numbers
   *
   * Everything else is not copied, in particular no project members
   * are copied over.
   *
   * @param int|Entities\Project $project
   *
   * @param null|string $newName The name of the copied project. If it
   * does not contain a year then the current year is used.
   *
   * @return null|Entities\Project Returns null if project was
   * actually deleted, else the updated "soft-deleted" project instance.
   *
   * @todo Check for proper cascading.
   */
  public function copyProject($projectOrId, ?string $newName = null):? Entities\Project
  {
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      throw new \RuntimeException($this->l->t('Unable to find the project to copy (id = %d)', $projectOrId));
    }

    // Road-map:
    // - sanitize name
    // - copy project and generate folders etc.
    // - copy instrumentation numbers
    // - copy participant fields

    if (empty($newName)) {
      $newName = $this->l->t('copy of %s', $project->getName());
      if ($project->getType() == ProjectType::TEMPORARY) {
        $newName = substr($newName, 0, -4) . date('Y');
      }
      $this->sanitizeName($projectName, $project->getType() == ProjectType::TEMPORARY);
    }
    list(, $newYear) = $this->yearFromName($newName);

    $this->entityManager->beginTransaction();
    try {

      /** @var Entities\Project $newProject */
      $newProject = clone $project;
      $newProject->setName($newName);
      $newProject->setYear($newYear);

      $this->persist($newProject);
      $this->flush();

      $this->createProjectInfraStructure($newProject);

      $this->entityManager->commit();
    } catch (\Throwable $t)  {
      $this->logException($t);
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }
      throw new \Exception(
        $this->l->t('Unable to copy project "%1$s" to "%2$s".', [ $project->getName(), $newName ]),
        $t->getCode(),
        $t);
    }

    return null;
  }

  /**
   * Rename the given project or id.
   *
   * @param int|Entities\Project $project
   *
   * @param string|array|Entities\Project $oldData
   *
   * @param string|array|Entities\Project $newData
   *
   * @return Entities\Project Returns the renamed and persisted
   * entity.
   */
  public function renameProject($projectOrId, $newData)
  {
    // This may be inside a transaction where the project-entity
    // already reflects the new state, so rather rely on the data.
    /** @var Entities\Project $project */
    $project = $this->repository->ensureProject($projectOrId);
    $projectId = $project['id'];

    $oldName = $projectOrId['name'] ?? $project->getName();
    $oldYear = $projectOrId['year'] ?? $project->getYear();

    if (is_string($newData)) {
      list('name' => $newName, 'year' => $newYear) = $this->yearFromName($newData);
      $newName = $newName.($newYear?:'');
    } else {
      $newName = $newData['name'];
      $newYear = $newData['year'];
    }
    $newYear = $newYear?:$project['year'];

    if ($oldName == $newName && $oldYear == $newYear) {
      return $project; // nothing to do
    }

    // project-entity is changed during the update.
    $oldProject = [ 'id' => $projectId, 'name' => $oldName, 'year' => $oldYear ];
    $newProject = [ 'id' => $projectId, 'name' => $newName, 'year' => $newYear ];

    /** @var CloudUserConnectorService $cloudService */
    $cloudService = $this->di(CloudUserConnectorService::class);

    $this->entityManager
      ->registerPreFlushAction(new GenericUndoable(
        function() use ($oldProject, $newProject) {
          $this->logInfo('OLD ' . print_r($oldProject, true) . ' NEW ' . print_r($newProject, true));
          $this->renameProjectFolder($newProject, $oldProject);
        },
        function() use ($oldProject, $newProject) {
          $this->renameProjectFolder($oldProject, $newProject);
        }
      ))
      ->register(new GenericUndoable(
        function() use ($oldProject, $newProject) {
          $this->renameProjectWikiPage($newProject, $oldProject);
        },
        function() use ($oldProject, $newProject) {
          $this->renameProjectWikiPage($oldProject, $newProject);
        }
      ))
      ->register(new GenericUndoable(
        function() use ($oldProject, $newProject) {
          $this->nameProjectWebPages($newProject['id'], $newProject['name']);
        },
        function() use ($oldProject, $newProject) {
          $this->nameProjectWebPages($oldProject['id'], $oldProject['name']);
        }
      ))
      ->register(new GenericUndoable(
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
      ->registerPreCommitAction(new GenericUndoable(
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

    if (!empty($listId = $project->getMailingListId())) {
      /** @var MailingListsService $listsService */
      $listsService = $this->di(MailingListsService::class);
      $this->entityManager->registerPreFlushAction(new GenericUndoable(
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

      $this->persistProject($project);
      $this->flush();
      $this->entityManager->commit();
    } catch (\Throwable $t) {
      $this->logException($t);
      $project->setName($oldName); // needed ?
      $project->setYear($oldYear); // needed ?
      $this->entityManager->rollback();
      if (!$this->entityManager->isTransactionActive()) {
        $this->entityManager->close();
        $this->entityManager->reopen();
      }

      throw new \Exception(
        $this->l->t('Failed to rename project "%s", id "%d" to new name "%s".',
                    [ $project['name'], $project['id'], $newName ]),
        $t->getCode(),
        $t);
    }

    return $project;
  }


  /**
   * Extract the year from the project name if present.
   *
   * @param string $name
   *
   * @return array
   *
   * ```
   * [ 'name' => NAME_WITHOUT_YEAR, 'year' => NULL_OR_YEAR ]
   * ```
   */
  public function yearFromName($projectName)
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
   */
  public function sanitizeName($projectName, $requireYear = false)
  {
    list('name' => $projectName, 'year' => $projectYear) = $this->yearFromName($projectName);
    if ($requireYear && !$projectYear) {
      return false;
    }

    if ($projectName ==  strtoupper($projectName)) {
      $projectName = strtolower($projectName);
    }
    $projectName = ucwords($projectName);
    $projectName = preg_replace("/[^[:alnum:]]?[[:space:]]?/u", '', $projectName);

    if ($projectYear) {
      $projectName .= $projectYear;
    }
    return $projectName;
  }

  /*
   * Sanitize the given project, i.e. make sure its infrastructure is
   * up-to-date.
   *
   * @param int|Entities\Project $projectOrId
   *
   * @param string|null $only Get the name of only this folder if not
   * null. $only can be one of the PROJECTS_..._FOLDER constants of
   * the ConfigService class, @see ConfigService.
   *
   * @throws \Exception is something goes wrong
   *
   * @todo This is not really implemented yet.
   */
  public function sanitizeProject($projectOrId)
  {
    $project = $this->repository->ensureProject($projectOrId);
    if (empty($project)) {
      throw new  \RuntimeException($this->l->t('Project not found.'));
    }
    $projectId = $project->getId();

    // do general stuff here

    if ($projectId == $this->getClubMembersProjectId()) {
      // do stuff here ?
    } else if ($projectId == $this->getExecutiveBoardProjectId()) {
      // ensure the signature field

      $participantFields = $project->getParticipantFields();

      $signatureNames = $this->translationVariants(ConfigService::SIGNATURE_FIELD_NAME);

      /** @var Entities\ProjectParticipantField $field */
      $signatureFound = $participantFields->exists(function($id, $field) use  ($signatureNames) {
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
          $this->l->t('Upload an image with the personal signature to simplify the generation of "official" mails. Preferably the image should have a transparent background and a resolution of 600 DPI or more.'));
        $this->persist($signatureField);
        $this->flush();
      }
    } else {
      throw new \RuntimeException($this->l->t('Validation of projects not yet implemented.'));
    }
  }

  /** Delete or disable a project participant. */
  public function deleteProjectParticipant(Entities\ProjectParticipant $participant)
  {
    /** @var Entities\ProjectParticipant $participant */
    $this->remove($participant, true); // this should be soft-delete
    if ($participant->unused()) {
      $this->logInfo('Project participant ' . $participant->getMusician()->getPublicName() . ' is unused, issuing hard-delete');

      // For now rather cascade manually. Could also use ORM, of course ...
      /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
      foreach ($participant->getParticipantFieldsData() as $fieldDatum) {
        $this->remove($fieldDatum, true);
        if ($fieldDatum->unused()) {
          $this->remove($fieldDatum, true);
        }
      }
      $this->remove($participant, true); // this should be hard-delete
    }

    $this->ensureMailingListUnsubscription($participant);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

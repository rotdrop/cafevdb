<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

// FIXME internal
use OC\Files\Mount\MountPoint;

use OCP\Files\Config\IMountProvider;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Exceptions\MissingProjectsFolderException;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Mount parts of the database-storage somewhere.
 *
 * @todo This is just a dummy for now in order to test the integration
 * with the surrounding cloud.
 */
class MountProvider implements IMountProvider
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use ProjectParticipantsStorageTrait;

  const MOUNT_TYPE = 'cafevdb-database';

  /** @var OrganizationalRolesService */
  private $organizationalRolesService;

  /** @var int */
  private static $recursionLevel = 0;

  public function __construct(
    ConfigService $configService
    , OrganizationalRolesService $organizationalRolesService
    , EntityManager $entityManager
  ) {
    $this->configService = $configService;
    $this->organizationalRolesService = $organizationalRolesService;
    $this->l = $this->l10n();
    $this->entityManager = $entityManager;
  }

  /**
   * Get all mountpoints applicable for the user
   *
   * @param \OCP\IUser $user
   * @param \OCP\Files\Storage\IStorageFactory $loader
   * @return \OCP\Files\Mount\IMountPoint[]
   */
  public function getMountsForUser(IUser $user, IStorageFactory $loader)
  {
    if (self::$recursionLevel > 0) {
      // the getNode() stuff below triggers recursion.
      $this->logDebug('RECURSION: ' . self::$recursionLevel);
      return [];
    }

    $userId = $user->getUID();

    if (!$this->inGroup($userId)) {
      return [];
    }

    if (!$this->entityManager->connected()) {
      // probably no credentials ...
      $this->logDebug('EntityManager is not connected for user ' . $userId);
      return [];
    }

    if ($userId == $this->shareOwnerId()) {
      // do not try to establish virtual mounts for the dummy user
      return [];
    }

    self::$recursionLevel++;

    // disable soft-deleteable here in order to cope with the case that the
    // musician underlying the project-participation is alreay soft-deleted.
    // Do this early as proxies seemingly (correctly) remember the filter state.
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $sharedFolder = $this->getSharedFolderPath();

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);
    if (empty($userStorage->user())) {
      $userStorage->setUser($user);
    }
    $node = $userStorage->get($sharedFolder);
    if (empty($node) || $node->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
      $this->logException(new \Exception('No shared folder for ' . $userId));
      --self::$recursionLevel;
      return [];
    }
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    try {
      $node = $node->get($projectsFolder);
      if (empty($node) || $node->getType() !== \OCP\Files\FileInfo::TYPE_FOLDER) {
        $this->logException(new MissingProjectsFolderException('No projects folder for ' . $userId));
        --self::$recursionLevel;
        return [];
      }
    } catch (\Throwable $t) {
      $this->logException(new MissingProjectsFolderException('No projects folder ' . $projectsFolder . ' for ' . $userId));
      --self::$recursionLevel;
      return [];
    }

    $this->logDebug($userId . ' ' . $sharedFolder);

    $mounts = [];
    $bulkLoadStorageIds = [];

    if ($this->organizationalRolesService->isTreasurer($userId, allowGroupAccess: true)) {
      // block for non-treasurers

      $storage = new BankTransactionsStorage([
        'configService' => $this->configService
      ]);
      $bulkLoadStorageIds[] = $storage->getId();

      $mounts[] = new class(
        $storage,
        '/' . $userId
        . '/files'
        . $this->getBankTransactionsPath(),
        null,
        $loader,
        [
          'filesystem_check_changes' => 1,
          'readonly' => true,
          'previews' => true,
          'enable_sharing' => false, // cannot work, mount needs DB access
          'authenticated' => true,
        ]
      ) extends MountPoint { public function getMountType() { return MountProvider::MOUNT_TYPE; } };
    }

    try {
      $projectsRepo = $this->getDatabaseRepository(Entities\Project::class);
      $projects = $projectsRepo->findBy([
        // '(|participantFields.dataType' => [ FieldType::DB_FILE, FieldType::SERVICE_FEE ],
        // '>financialBalanceSupportingDocuments.sequence' => 0,
        // [ ')' => true ],
        'type' => [ ProjectType::PERMANENT, ProjectType::TEMPORARY ],
        'deleted' => null,
      ]);
    } catch (\Throwable $t) {
      $this->logException($t, 'Unable to access projects table');
      return [];
    }

    if ($this->organizationalRolesService->isTreasurer($userId, allowGroupAccess: true)) {
      // block for non-treasurers

      /** @var Entities\Project $project */
      foreach ($projects as $project) {

        $storage = new ProjectBalanceSupportingDocumentsStorage([
          'configService' => $this->configService,
          'project' => $project,
        ]);
        $bulkLoadStorageIds[] = $storage->getId();

        $mountPathChain = [ $this->getProjectBalancesPath() ];
        if ($project->getType() == ProjectType::TEMPORARY) {
          $mountPathChain[] = $project->getYear();
        };
        $mountPathChain[] = $project->getName();
        $mountPathChain[] = $this->getSupportingDocumentsFolderName();

        $mountPath = '/' . $userId . '/' . 'files' . implode('/', $mountPathChain);

        $mounts[] = new class(
          $storage,
          $mountPath,
          null,
          $loader,
          [
            'filesystem_check_changes' => 1,
            'readonly' => false,
            'previews' => true,
            'enable_sharing' => false, // cannot work, mount needs DB access
            'authenticated' => true,
          ]
        ) extends MountPoint { public function getMountType() { return MountProvider::MOUNT_TYPE; } };
      }
    }

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);

    $fileCriteria = DBUtil::criteriaWhere([
      'dataType' => [ FieldType::DB_FILE, FieldType::SERVICE_FEE, ],
      'deleted' => null,
    ]);

    // this is going to execute too many queries, try to fetch at least the
    // storages in one run.

    $participantStorages = [];

    /** @var Entities\Project $project */
    foreach ($projects as $project) {
      $fileFields = $project->getParticipantFields()->matching($fileCriteria);
      if (count($fileFields) < 1) {
        continue;
      }

      /** @var Entities\ProjectParticipant $participant */
      foreach ($project->getParticipants() as $participant) {
        try {
          $folder = $projectService->getParticipantFolder($project, $participant->getMusician());
          $storage = new ProjectParticipantsStorage([
            'configService' => $this->configService,
            'participant' => $participant,
          ]);
          $bulkLoadStorageIds[] = $storage->getId();
          $participantStorages[$folder] = $storage;
        } catch (\Throwable $t) {
          $this->logException($t, 'Caught an exception trying to generate project-participant mounts.');
          continue;
        }
      }
    }

    foreach ($participantStorages as $folder => $storage) {

      $mounts[] = new class(
        $storage,
        '/' . $userId
        . '/files'
        . $folder
        . '/'. $this->getDocumentsFolderName(),
        null,
        $loader,
        [
          'filesystem_check_changes' => 1,
          'readonly' => true,
          'previews' => true,
          'enable_sharing' => false, // cannot work, mount needs DB access
          'authenticated' => true,
        ]
        ) extends MountPoint { public function getMountType() { return MountProvider::MOUNT_TYPE; } };
    }

    \OC\Files\Cache\Storage::getGlobalCache()->loadForStorageIds($bulkLoadStorageIds);

    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    --self::$recursionLevel;
    return $mounts;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

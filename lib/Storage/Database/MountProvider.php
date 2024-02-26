<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, 2023, 2024, Claus-Justus Heine
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

namespace OCA\CAFEVDB\Storage\Database;

use Exception;
use Throwable;

// F I X M E internal
use OC\Files\Mount\MountPoint;

use OCP\Files\Config\IMountProvider;
use OCP\Files\FileInfo;
use OCP\Files\Storage\IStorageFactory;
use OCP\IUser;
use OCP\IUserSession;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\AuthorizationService;
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
 */
class MountProvider implements IMountProvider
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use DatabaseStorageNodeNameTrait;

  const MOUNT_TYPE = 'cafevdb-database';

  /** @var int */
  private static $recursionLevel = 0;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    private AuthorizationService $authorizationService,
    private Factory $storageFactory,
    private IUserSession $userSession,
  ) {
    $this->l = $this->l10n();
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function getMountsForUser(IUser $user, IStorageFactory $loader)
  {
    if (self::$recursionLevel > 0) {
      // the getNode() stuff below triggers recursion.
      $this->logDebug('RECURSION: ' . self::$recursionLevel);
      return [];
    }
    self::$recursionLevel++;

    try {
      $mounts = $this->getMountsForUserInternal($user, $loader);
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to generate mounts');
      $mounts = [];
    }

    --self::$recursionLevel;
    return $mounts;
  }

  /** {@inheritdoc} */
  private function getMountsForUserInternal(IUser $user, IStorageFactory $loader)
  {
    $userId = $user->getUID();

    if ($this->userSession->isLoggedIn())  {
      $this->logDebug('No one is logged in.');
      return [];
    }

    if (!$this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_FILESYSTEM)) {
      $this->logDebug('User is not authorized: ' . $userId);
      return [];
    }

    if (!$this->entityManager->connected()) {
      // probably no credentials ...
      $this->logDebug('EntityManager is not connected for user ' . $userId);
      return [];
    }

    if ($userId == $this->shareOwnerId()) {
      $this->logDebug('Skipping virutal mounts for the shareholder: ' . $userId);
      // do not try to establish virtual mounts for the dummy user
      return [];
    }

    // disable soft-deleteable here in order to cope with the case that the
    // musician underlying the project-participation is alreay soft-deleted.
    // Do this early as proxies seemingly (correctly) remember the filter state.
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $sharedFolder = $this->getSharedFolderPath();

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    // we have to install the correct user into our UserStorage support class on each call.
    $userStorage->setUser($user);

    $node = $userStorage->get($sharedFolder);
    if (empty($node) || $node->getType() !== FileInfo::TYPE_FOLDER) {
      $this->logException(new Exception('No shared folder "' . $sharedFolder . '" for ' . $userId));
      return [];
    }
    $projectsFolder = $this->getConfigValue(ConfigService::PROJECTS_FOLDER);
    try {
      $node = $node->get($projectsFolder);
      if (empty($node) || $node->getType() !== FileInfo::TYPE_FOLDER) {
        $this->logException(new MissingProjectsFolderException('No projects folder for ' . $userId));
        return [];
      }
    } catch (\Throwable $t) {
      $this->logException(new MissingProjectsFolderException('No projects folder ' . $projectsFolder . ' for ' . $userId));
      return [];
    }

    $this->logDebug($userId . ' ' . $sharedFolder);

    $mounts = [];
    $bulkLoadStorageIds = [];

    // allow only treasurers or similar folk
    if ($this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_FINANCE)) {

      $storage = $this->storageFactory->getBankTransactionsStorage();
      $bulkLoadStorageIds[] = $storage->getId();

      $mounts[] = new class(
        $storage,
        UserStorage::PATH_SEP . implode(
          UserStorage::PATH_SEP,
          [ $userId, 'files', $this->getBankTransactionsPath(), ]
        ),
        null,
        $loader,
        [
          'filesystem_check_changes' => 1,
          'readonly' => true,
          'previews' => true,
          'enable_sharing' => false, // cannot work, mount needs DB access
          'authenticated' => true,
        ]
      ) extends MountPoint
      {
        /** {@inheritdoc} */
        public function getMountType()
        {
          return MountProvider::MOUNT_TYPE;
        }
      };

      $storage = $this->storageFactory->getTaxExemptionNoticesStorage();
      $bulkLoadStorageIds[] = $storage->getId();

      $mounts[] = new class(
        storage: $storage,
        mountpoint: UserStorage::PATH_SEP . implode(
          UserStorage::PATH_SEP,
          [ $userId, 'files', $this->getTaxExemptionNoticesPath(), ],
        ),
        mountId: null,
        loader: $loader,
        mountProvider: MountProvider::class,
        mountOptions: [
          'filesystem_check_changes' => 1,
          'readonly' => false,
          'previews' => true,
          'enable_sharing' => false, // cannot work, mount needs DB access
          'authenticated' => true,
        ]
      ) extends MountPoint
      {
        /** {@inheritdoc} */
        public function getMountType()
        {
          return MountProvider::MOUNT_TYPE;
        }
      };

      $storage = $this->storageFactory->getDonationReceiptsStorage();
      $bulkLoadStorageIds[] = $storage->getId();

      $mounts[] = new class(
        storage: $storage,
        mountpoint: UserStorage::PATH_SEP . implode(
          UserStorage::PATH_SEP,
          [ $userId, 'files', $this->getDonationReceiptsPath(), ],
        ),
        mountId: null,
        loader: $loader,
        mountProvider: MountProvider::class,
        mountOptions: [
          'filesystem_check_changes' => 1,
          'readonly' => false,
          'previews' => true,
          'enable_sharing' => false, // cannot work, mount needs DB access
          'authenticated' => true,
        ]
      ) extends MountPoint
      {
        /** {@inheritdoc} */
        public function getMountType()
        {
          return MountProvider::MOUNT_TYPE;
        }
      };
    }

    try {
      $projectsRepo = $this->getDatabaseRepository(Entities\Project::class);
      $projects = $projectsRepo->findBy([
        // '(|participantFields.dataType' => [ FieldType::DB_FILE, FieldType::RECEIVABLES, FieldType::LIALBILITIES ],
        // '>financialBalanceSupportingDocuments.sequence' => 0,
        // [ ')' => true ],
        'type' => [ ProjectType::PERMANENT, ProjectType::TEMPORARY ],
        'deleted' => null,
      ]);
    } catch (\Throwable $t) {
      $this->logException($t, 'Unable to access projects table');
      return [];
    }

    if ($this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_FINANCE)) {
      // allow only treasurers or similar folk

      /** @var Entities\Project $project */
      foreach ($projects as $project) {

        $storage = $this->storageFactory->getProjectBalanceSupportingDocumentsStorage($project);
        $bulkLoadStorageIds[] = $storage->getId();

        $mountPathChain = [ $this->getProjectBalancesPath() ];
        if ($project->getType() == ProjectType::TEMPORARY) {
          $mountPathChain[] = $project->getYear();
        };
        $mountPathChain[] = $project->getName();
        $mountPathChain[] = $this->getSupportingDocumentsFolderName();

        $mountPath = UserStorage::PATH_SEP . implode(
          UserStorage::PATH_SEP,
          array_merge(
            [ $userId, 'files', ],
            $mountPathChain,
          ),
        );

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
        ) extends MountPoint
        {
          /** {@inheritdoc} */
          public function getMountType()
          {
            return MountProvider::MOUNT_TYPE;
          }
        };
      }
    }

    /** @var ProjectService $projectService */
    $projectService = $this->di(ProjectService::class);

    $fileCriteria = DBUtil::criteriaWhere([
      'dataType' => [
        FieldType::DB_FILE,
        FieldType::RECEIVABLES,
        FieldType::LIABILITIES,
      ],
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
          $storage = $this->storageFactory->getProjectParticipantsStorage($participant);
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
        UserStorage::PATH_SEP . implode(
          UserStorage::PATH_SEP,
          [ $userId, 'files', $folder, $this->getDocumentsFolderName(), ]
        ),
        null,
        $loader,
        [
          'filesystem_check_changes' => 1,
          'readonly' => true,
          'previews' => true,
          'enable_sharing' => false, // cannot work, mount needs DB access
          'authenticated' => true,
        ]
      ) extends MountPoint
      {
        /** {@inheritdoc} */
        public function getMountType()
        {
          return MountProvider::MOUNT_TYPE;
        }
      };
    }

    \OC\Files\Cache\Storage::getGlobalCache()->loadForStorageIds($bulkLoadStorageIds);

    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $mounts;
  }
}

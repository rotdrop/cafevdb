<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2025, Claus-Justus Heine
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

use Throwable;
use UnexpectedValueException;

// F I X M E internal
use OC\Files\Mount\MountPoint;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Config\ICachedMountInfo;
use OCP\Files\Config\IMountProvider;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\FileInfo;
use OCP\Files\Storage\IStorageFactory;
use OCP\Files\StorageNotAvailableException;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserSession;

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldDataType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions\MissingProjectsFolderException;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Settings\ConfigConstants;
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
  const APP_ID = 'cafevdb';

  /** @var int */
  private static $recursionLevel = 0;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    private AuthorizationService $authorizationService,
    private Factory $storageFactory,
    private IUserMountCache $mountCache,
    private IDBConnection $connection,
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
    } catch (StorageNotAvailableException $t) {
      throw $t;
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to generate mounts');
      $mounts = [];
    } finally {
      --self::$recursionLevel;
    }

    return $mounts;
  }

  /** {@inheritdoc} */
  private function getMountsForUserInternal(IUser $user, IStorageFactory $loader)
  {
    $userId = $user->getUID();

    if (!$this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_FILESYSTEM)) {
      $this->logDebug('User is not authorized: ' . $userId);
      return [];
    }

    if ($userId == $this->shareOwnerId()) {
      $this->logDebug('Skipping virtual mounts for the shareholder: ' . $userId);
      return [];
    }

    if (!$this->userSession->isLoggedIn()
        || $this->userSession->getUser()?->getUID() !== $userId) {
      return $this->getUnavailableMounts($user);
    }

    $entityManager = EntityManager::getInstance();
    if (empty($entityManager) || $entityManager->getUserId() !== $userId) {
      return $this->getUnavailableMounts($user);
    }

    try {
      if (!$entityManager->connected()) {
        return $this->getUnavailableMounts($user);
      }
      $this->entityManager = $entityManager;
      return $this->getAuthenticatedMountsForUser($user, $loader);
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to refresh database mounts for ' . $userId);
      return $this->getUnavailableMounts($user);
    }
  }

  /**
   * @param IUser $user
   * @param IStorageFactory $loader
   *
   * @return array
   */
  private function getAuthenticatedMountsForUser(IUser $user, IStorageFactory $loader):array
  {
    // disable soft-deleteable here in order to cope with the case that the
    // musician underlying the project-participation is alreay soft-deleted.
    // Do this early as proxies seemingly (correctly) remember the filter state.
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    try {
      return $this->buildAuthenticatedMountsForUser($user, $loader);
    } finally {
      $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    }
  }

  /**
   * @param IUser $user
   * @param IStorageFactory $loader
   *
   * @return array
   */
  private function buildAuthenticatedMountsForUser(IUser $user, IStorageFactory $loader):array
  {
    $userId = $user->getUID();
    $sharedFolder = $this->getSharedFolderPath();

    /** @var UserStorage $userStorage */
    $userStorage = $this->di(UserStorage::class);

    // we have to install the correct user into our UserStorage support class on each call.
    $userStorage->setUser($user);

    $node = $userStorage->get($sharedFolder);
    if (empty($node) || $node->getType() !== FileInfo::TYPE_FOLDER) {
      throw new UnexpectedValueException('No shared folder "' . $sharedFolder . '" for ' . $userId);
    }
    $projectsFolder = $this->getConfigValue(ConfigConstants::PROJECTS_FOLDER);
    try {
      $node = $node->get($projectsFolder);
      if (empty($node) || $node->getType() !== FileInfo::TYPE_FOLDER) {
        throw new MissingProjectsFolderException('No projects folder for ' . $userId);
      }
    } catch (\Throwable $t) {
      throw new MissingProjectsFolderException('No projects folder ' . $projectsFolder . ' for ' . $userId, 0, $t);
    }

    $this->logDebug($userId . ' ' . $sharedFolder);

    $mounts = [];
    $bulkLoadStorageIds = [];

    // allow only treasurers or similar folk
    if ($this->authorizationService->authorized($userId, AuthorizationService::PERMISSION_FINANCE)) {

      $storage = $this->storageFactory->getBankTransactionsStorage();
      $bulkLoadStorageIds[] = $storage->getId();

      $mounts[] = new class(
        storage: $storage,
        mountpoint: UserStorage::PATH_SEP . implode(
          UserStorage::PATH_SEP,
          [ $userId, 'files', $this->getBankTransactionsPath(), ]
        ),
        arguments: null,
        loader: $loader,
        mountOptions: [
          'filesystem_check_changes' => 1,
          'readonly' => true,
          'previews' => true,
          'enable_sharing' => false, // cannot work, mount needs DB access
          'authenticated' => true,
        ],
        mountProvider: MountProvider::class,
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

      $storage = $this->storageFactory->getInvoicesStorage();
      $bulkLoadStorageIds[] = $storage->getId();

      $mounts[] = new class(
        storage: $storage,
        mountpoint: UserStorage::PATH_SEP . implode(
          UserStorage::PATH_SEP,
          [ $userId, 'files', $this->getInvoicesPath(), ],
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
        // '(|participantFields.dataType' => [ FieldDataType::DB_FILE, FieldDataType::RECEIVABLES, FieldDataType::LIALBILITIES ],
        // '>financialBalanceSupportingDocuments.sequence' => 0,
        // [ ')' => true ],
        'type' => [ ProjectType::PERMANENT, ProjectType::TEMPORARY ],
        'deleted' => null,
      ]);
    } catch (\Throwable $t) {
      $this->logException($t, 'Unable to access projects table');
      throw $t;
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
        FieldDataType::DB_FILE,
        FieldDataType::RECEIVABLES,
        FieldDataType::LIABILITIES,
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
          throw $t;
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

    return $mounts;
  }

  /**
   * Retain only this user's previously registered CafeVDB mounts while the
   * application database is unavailable.
   *
   * @param IUser $user
   *
   * @return array
   */
  private function getUnavailableMounts(IUser $user):array
  {
    $mounts = [];
    $userId = $user->getUID();
    $hasFinancePermission = $this->authorizationService->authorized(
      $userId,
      AuthorizationService::PERMISSION_FINANCE,
    );

    try {
      foreach ($this->mountCache->getMountsForUser($user) as $cachedMount) {
        if ($cachedMount->getMountProvider() !== self::class) {
          continue;
        }

        $mountPath = $cachedMount->getMountPoint();
        $prefix = '/' . $userId . '/files/';
        $relativePath = str_starts_with($mountPath, $prefix)
          ? substr($mountPath, strlen($prefix))
          : '';
        $pathParts = explode('/', trim($relativePath, '/'));

        if ($cachedMount->getUser()->getUID() !== $userId
            || !str_starts_with($mountPath, $prefix)
            || !str_ends_with($mountPath, '/')
            || str_contains($mountPath, '//')
            || in_array('', $pathParts, true)
            || in_array('.', $pathParts, true)
            || in_array('..', $pathParts, true)
            || $cachedMount->getRootInternalPath() !== ''
            || $cachedMount->getStorageId() <= 0
            || $cachedMount->getRootId() <= 0) {
          throw new StorageNotAvailableException('Invalid cached CafeVDB mount for ' . $userId);
        }

        $storageId = $this->getCachedStorageId($cachedMount);
        $storagePrefix = self::APP_ID . Storage::STORAGE_ID_TAG . Storage::PATH_SEPARATOR;
        if (!str_starts_with($storageId, $storagePrefix)
            && preg_match('/^[a-f0-9]{32}$/D', $storageId) !== 1) {
          throw new StorageNotAvailableException('Cached storage is not a recognized CafeVDB storage.');
        }

        if (!$hasFinancePermission && $this->isFinanceMountPath($userId, $mountPath)) {
          continue;
        }

        $mounts[] = new UnavailableMount($storageId, $cachedMount);
      }
    } catch (Throwable $t) {
      if ($t instanceof StorageNotAvailableException) {
        throw $t;
      }
      throw new StorageNotAvailableException(
        'Unable to restore cached CafeVDB mounts.',
        StorageNotAvailableException::STATUS_ERROR,
        $t instanceof \Exception ? $t : null,
      );
    }

    return $mounts;
  }

  /**
   * @param string $userId
   * @param string $mountPath
   *
   * @return bool
   */
  private function isFinanceMountPath(string $userId, string $mountPath):bool
  {
    $financePaths = [
      $this->getBankTransactionsPath(),
      $this->getTaxExemptionNoticesPath(),
      $this->getDonationReceiptsPath(),
      $this->getInvoicesPath(),
      $this->getProjectBalancesPath(),
    ];

    foreach ($financePaths as $financePath) {
      if (!empty($financePath)
          && str_starts_with($mountPath, '/' . $userId . '/files/' . trim($financePath, '/') . '/')) {
        return true;
      }
    }

    return false;
  }

  /**
   * Verify the cached root and return the storage's registered string ID.
   *
   * @param ICachedMountInfo $cachedMount
   *
   * @return string
   */
  private function getCachedStorageId(ICachedMountInfo $cachedMount):string
  {
    $query = $this->connection->getQueryBuilder();
    $query->select('s.id')
      ->from('storages', 's')
      ->innerJoin('s', 'filecache', 'f', $query->expr()->eq('f.storage', 's.numeric_id'))
      ->where($query->expr()->eq('s.numeric_id', $query->createNamedParameter($cachedMount->getStorageId(), IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->eq('f.fileid', $query->createNamedParameter($cachedMount->getRootId(), IQueryBuilder::PARAM_INT)))
      ->andWhere($query->expr()->eq('f.path', $query->createNamedParameter('')))
      ->andWhere($query->expr()->eq('f.parent', $query->createNamedParameter(-1, IQueryBuilder::PARAM_INT)));

    $result = $query->executeQuery();
    try {
      $storageId = $result->fetchOne();
    } finally {
      $result->closeCursor();
    }
    if (!is_string($storageId) || $storageId === '') {
      throw new StorageNotAvailableException('Cached CafeVDB storage root is missing.');
    }
    return $storageId;
  }
}

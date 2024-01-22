<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Maintenance\Migrations;

use Throwable;

use Psr\Log\LoggerInterface as ILogger;

use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\Folder;
use OCP\IL10N;
use OCP\Lock\ILockingProvider;
use OCP\IUserSession;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Maintenance\IMigration;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\GroupFoldersService;
use OCA\CAFEVDB\Service\SimpleSharingService;

/**
 * Decrypt the shareowner config value.
 */
class GroupSharedOrchestraFolder implements IMigration
{
  use \OCA\CAFEVDB\Toolkit\Traits\UserRootFolderTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  private const MIGRATION_SUFFIX = '-migration';
  private const KEEP_OLD_SUFFIX = '-pre-group-folder-migration';
  private const SHARED_KEEP_OLD_SUFFIX = '-old-do-not-use';

  private const SHARE_TYPES = [
    IShare::TYPE_CIRCLE,
    IShare::TYPE_DECK,
    IShare::TYPE_DECK_USER,
    IShare::TYPE_EMAIL,
    IShare::TYPE_GROUP,
    IShare::TYPE_GUEST,
    IShare::TYPE_LINK,
    IShare::TYPE_REMOTE,
    IShare::TYPE_REMOTE_GROUP,
    IShare::TYPE_ROOM,
    IShare::TYPE_SCIENCEMESH,
    IShare::TYPE_USER,
    IShare::TYPE_USERGROUP,
  ];

  protected string $userId; // needed by the root-folder trait

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    IUserSession $userSession,
    protected ILogger $logger,
    protected IL10N $l,
    protected ConfigService $configService,
    protected ConfigCheckService $configCheckService,
    protected EncryptionService $encryptionService,
    protected GroupFoldersService $groupFoldersService,
    protected SimpleSharingService $sharingService,
    protected IRootFolder $rootFolder,
    protected ILockingProvider $lockingProvider,
    protected IShareManager $shareManager,
    protected string $appName,
  ) {
    // note that this cannot be done by simply using dependency injection on
    // the userId as the user-id is already initialized to null when running
    // the CLI tools before the login-process happens. The DI-container value
    // cannot be corrected later.
    $user = $userSession->getUser();
    if (empty($user)) {
      throw new Exceptions\Exception('Not logged in');
    }
    $this->userId = $user->getUID();
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function description():string
  {
    return $this->l->t(
      'Abandon the orchestra folder shared by the share-owner "%s" and instead use a group-shared folder with the same name.'
      . ' This has several advantages.'
      . ' The first is that this is one step towards the removal of the shareowner dummy user altogether,'
      . ' the second is that the mount-point name of the group-shared folder is more stable; it cannot be renamed.'
      . ' Further this change opens the possibility to a finer grained access control of sub-folders, modelling them as additional group-shared folders.'
      . ' Please note that this may be a long running operation, depending on the size of the shared orchestra folder.'
      . ' Please note also that the old folder is not removed by this automatic migration step.'
      . ' This also implies that there must be enough storage space left on the server in order to hold the new copy.',
      $this->encryptionService->getConfigValue(ConfigService::SHAREOWNER_KEY)
    );
  }

  /** {@inheritdoc} */
  public function execute():bool
  {
    try {
      // roadmap:
      //
      // - create a new group-shared folder FOLDERNAME-new
      //
      // - copy all the data from the old shared folder to the new one, recursively
      //
      // - unshare the old folder, removing all shares. This will also
      //   invalidate member download shares, but so what. Or could this be
      //   avoided by tweaking the database?
      //
      // - rename the new group share to FOLDERNAME

      $shareOwner = $this->encryptionService->getConfigValue(ConfigService::SHAREOWNER_KEY);
      $sharedFolder = $this->encryptionService->getConfigValue(ConfigService::SHARED_FOLDER, false);
      if (empty($sharedFolder)) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('The shared orchester file-system folder is not configured, hence this migration cannot be performed.')
        );
      }

      // Step 0: check if we are already done
      $folderInfo = $this->groupFoldersService->getFolder($sharedFolder);

      if (empty($folderInfo)) {
        // Step 1, create the folder
        if (!$this->configCheckService->checkGroupFolder($sharedFolder, self::MIGRATION_SUFFIX)) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Unable to create the intermediate shared folder "%s". Please contact the system administrator.', $sharedFolder . self::MIGRATION_SUFFIX)
          );
        }
        $groupFolder = $sharedFolder . self::MIGRATION_SUFFIX;
      } else {
        $groupFolder = $sharedFolder;
        $sharedFolder = $groupFolder . self::KEEP_OLD_SUFFIX;
      }

      // now copy everything ...
      $this->userId = $shareOwner;
      /** @var Folder $userFolder */
      $userFolder = $this->getUserFolder();
      $this->logInfo('USER FOLDER ' . $userFolder->getPath());

      $sourceFolder = $userFolder->get($sharedFolder);
      $this->logInfo('SOURCE: ' . $sourceFolder->getPath() . ' ' . $sourceFolder->getParent()->getPath());

      $targetFolder = $userFolder->get($groupFolder);
      $this->logInfo('TARGET: ' . $targetFolder->getPath() . ' ' . $targetFolder->getParent()->getPath());

      $sourceStorage = $sourceFolder->getStorage();
      $targetStorage = $targetFolder->getStorage();

      $targetInternalPath = $targetFolder->getInternalPath();
      $sourceInternalPath = $sourceFolder->getInternalPath();

      $this->logInfo('INTERNAL PATHS ' . $targetInternalPath . ' vs ' . $sourceInternalPath);

      $locked = false;
      try {
        $targetStorage->acquireLock($targetInternalPath, ILockingProvider::LOCK_EXCLUSIVE, $this->lockingProvider);
        $locked = true;
        $targetStorage->copyFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
        $targetStorage->releaseLock($targetInternalPath, ILockingProvider::LOCK_EXCLUSIVE, $this->lockingProvider);
        $locked = false;
        $targetStorage->getScanner()->scan($targetInternalPath);
      } catch (Throwable $t) {
        $this->logException($t);
        if ($locked) {
          try {
            $targetStorage->releaseLock($targetInternalPath, ILockingProvider::LOCK_EXCLUSIVE, $this->lockingProvider);
          } catch (Throwable $t) {
            $this->logException($t, 'Unable to unlock ' . $targetInternalPath);
          }
        }
        try {
          // try to cleanup if possible
          $targetStorage->getScanner()->scan($targetInternalPath);
          // $targetFolder->delete();
        } catch (FileNotFoundException $e) {
          // really ignore this one: nothing to be cleaned up
        } catch (Throwable $t) {
          $this->logException($t, 'Unable to cleanup target path.');
          // otherwise ignore
        }
        throw Exceptions\EnduserNotificationException($this->l->t('Copying failed.'), 0, $t);
      }

      $shares = [];
      foreach (self::SHARE_TYPES as $shareType) {
        $shares = array_merge($shares, $this->shareManager->getSharesBy($shareOwner, $shareType, reshares: true, limit: -1));
      }

      $sourcePath = $sourceFolder->getPath();
      $targetPath = $targetFolder->getPath();

      /** @var IShare $share */
      foreach ($shares as $share) {
        try {
          $sharedNode = $share->getNode();
          $shareType = $share->getShareType();
          $shareSource = $sharedNode->getPath();
          $sharedWith = $share->getSharedWith();
          $this->logInfo(
            'Share:'
              . ' SRC: ' . $shareSource
              . ' TGT: ' . $share->getTarget()
              . ' TYPE ' . $shareType
              . ' TOKEN ' . $share->getToken()
              . ' WITH ' . $sharedWith
          );
          if ($shareType == IShare::TYPE_LINK || $shareType == IShare::TYPE_USER) {
            // Update individual and shared-by links shares s.t. they point to
            // the new location.
            //
            // typical source: /cameratashareholder/files/camerata
            // typical target: /cameratashareholder/files/camerata-migration
            // typical link share path:
            // /cameratashareholder/files/camerata/projects/2019/Auvergne2019/downloads
            $newShareSource = str_replace($sourcePath, '', $shareSource);
            $newSharedNode = $targetFolder->get($newShareSource);
            $share->setNode($newSharedNode);
            $this->shareManager->updateShare($share);
          } else {
            // Delete all other shares.
            $this->logInfo('Deleting share ' . $shareSource . ' -> ' . $sharedWith);
            $this->shareManager->deleteShare($share);
          }
        } catch (NotFoundException $e) {
          $this->logInfo('Cleaning share of non-existing node');
          $this->shareManager->deleteShare($share);
        }
      }

      if (!str_ends_with($sourcePath, self::KEEP_OLD_SUFFIX)) {
        // then move the old folder out of the way
        $sourceFolder->move($sourcePath . self::KEEP_OLD_SUFFIX);
      }

      if (str_ends_with($targetPath, self::MIGRATION_SUFFIX)) {
        $this->groupFoldersService->changeMountPoint($groupFolder, $sharedFolder, moveChildren: true);
      }

      $orchestraGroup = $this->encryptionService->getConfigValue(ConfigService::USER_GROUP_KEY);
      // group-share the old folder in order to give the orchestra group
      // access for a while
      $this->sharingService->groupShareNode(
        $sourceFolder,
        $orchestraGroup,
        $sharedFolder . self::SHARED_KEEP_OLD_SUFFIX,
        \OCP\Constants::PERMISSION_READ,
      );

      // Remember that we do no longer need the folder from the share-owner
      $this->encryptionService->setConfigValue(ConfigService::SHAREOWNER_FOLDER_SERVICE_KEY, false);

    } catch (Throwable $t) {
      throw new Exceptions\MigrationException('Migration has failed', 0, $t);
      $this->logException($t);
      return false;
    }

    return true;
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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
use Net_IMAP;

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Share\IManager as IShareManager;
use OCP\Calendar\IManager as ICalendarManager;
use OCP\IUserSession;
use OCP\IUser;
use OCP\IConfig;
use OCP\Share\IShare;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\AddressBook\AddressBookProvider;
use OCA\CAFEVDB\Common\Util; // some static helpers, only for explode
use OCA\CAFEVDB\Common\PHPMailer;

/** Check for a usable configuration. */
class ConfigCheckService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  const SHARE_PERMISSIONS = (
      \OCP\Constants::PERMISSION_CREATE
      | \OCP\Constants::PERMISSION_READ
      | \OCP\Constants::PERMISSION_UPDATE
      | \OCP\Constants::PERMISSION_DELETE
      | \OCP\Constants::PERMISSION_SHARE
  );

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    private IRootFolder $rootFolder,
    private IShareManager $shareManager,
    private ICalendarManager $calendarManager,
    private IContactsManager $contactsManager,
    private CalDavService $calDavService,
    private CardDavService $cardDavService,
    private EventsService $eventsService,
    private AddressBookProvider $addressBookProvider,
    private MigrationsService $migrationsService,
    private SimpleSharingService $sharingService,
  ) {
    $this->l = $this->l10n();
    // {
      // $mm3 = new MailingListsService($this->configService);
      // $mm3->serverConfiguration();
    // }
  }
  // phpcs:enable

  /**
   * Return an array with necessary configuration items, being either
   * true or false, depending the checks performed. The summary
   * component is just the logic and of all other items.
   *
   * @return array
   * ```
   * [ 'summary',
   *   ConfigService::USER_GROUP_KEY,
   *   'encryptionkey'
   *   'orchestra',
   *   ConfigService::SHAREOWNER_KEY,
   *   'sharedfolder',
   *   'database',
   * ]
   * ```
   *
   * where summary is a bool and everything else is
   * array('status','message') where 'message' should be empty if
   * status is true.
   */
  public function configured()
  {
    $result = [];

    foreach ([
      'orchestra',
      ConfigService::USER_GROUP_KEY,
      ConfigService::SHAREOWNER_KEY,
      'sharedfolder',
      'database',
      'encryptionkey',
      'migrations',
    ] as $key) {
      $result[$key] = ['status' => false, 'message' => ''];
    }

    $key ='orchestra';
    try {
      $result[$key]['status'] = $this->getConfigValue('orchestra');
    } catch (Throwable $e) {
      $result[$key]['message'] = $e->getMessage();
    }
    $this->logDebug($key.': '.$result[$key]['status']);

    $key = 'encryptionkey';
    try {
      $result[$key]['status'] = $result['orchestra']['status'] && $this->encryptionKeyValid();
    } catch (Throwable $e) {
      $result[$key]['message'] = $e->getMessage();
    }
    $this->logDebug($key.': '.$result[$key]['status']);

    $key = 'database';
    try {
      $result[$key]['status'] = $result['orchestra']['status'] && $this->databaseAccessible();
    } catch (Throwable $e) {
      $result[$key]['message'] = $e->getMessage();
    }
    $this->logDebug($key.': '.$result[$key]['status']);

    $key = 'migrations';
    try {
      $result[$key]['status'] = $result['database']['status'] && $this->noUnappliedMigrations();
    } catch (Throwable $t) {
      $result[$key]['message'] = $t->getMessage();
    }
    $this->logDebug($key.': '.$result[$key]['status']);

    $key = ConfigService::USER_GROUP_KEY;
    try {
      $result[$key]['status'] = $this->shareGroupExists();
    } catch (Throwable $e) {
      $result[$key]['message'] = $e->getMessage();
    }
    $this->logDebug($key.': '.$result[$key]['status']);

    $key = ConfigService::SHAREOWNER_KEY;
    try {
      $result[$key]['status'] = $result[ConfigService::USER_GROUP_KEY]['status'] && $this->shareOwnerExists();
    } catch (Throwable $e) {
      $result[$key]['message'] = $e->getMessage();
    }
    $this->logDebug($key.': '.$result[$key]['status']);

    $key = 'sharedfolder';
    try {
      $result[$key]['status'] = $result[ConfigService::SHAREOWNER_KEY]['status'] && $this->sharedFolderExists();
    } catch (Throwable $e) {
      $result[$key]['message'] = $e->getMessage();
    }
    $this->logDebug($key.': '.$result[$key]['status']);

    $key = 'sharedaddressbooks';
    try {
      $result[$key]['status'] = $result[ConfigService::SHAREOWNER_KEY]['status'] && $this->sharedAddressBooksExist();
    } catch (Throwable $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $summary = true;
    foreach ($result as $key => $value) {
      $summary = $summary && $value['status'];
    }
    $result['summary'] = $summary;
    $this->logDebug(print_r($result, true));

    return $result;
  }

  /**
   * @param string $host
   *
   * @param int $port
   *
   * @param null|string $secure
   *
   * @param string $user
   *
   * @param string $password
   *
   * @return bool
   */
  public function checkImapServer(
    string $host,
    int $port,
    ?string $secure,
    string $user,
    string $password,
  ):bool {
    $imap = new Net_IMAP($host, $port, $secure == 'starttls' ? true : false, 'UTF-8');
    $result = $imap->login($user, $password) === true;
    $imap->disconnect();

    return $result;
  }

  /**
   * @param string $host
   *
   * @param int $port
   *
   * @param null|string $secure Enum, 'insecure', 'ssl', 'starttls'.
   *
   * @param string $user
   *
   * @param string $password
   *
   * @return bool
   */
  public function checkSmtpServer(
    string $host,
    int $port,
    ?string $secure,
    string $user,
    string $password,
  ):bool {
    $result = true;

    $mail = new PHPMailer(true);
    $mail->CharSet = 'utf-8';
    $mail->SingleTo = false;
    $mail->IsSMTP();

    $mail->Host = $host;
    $mail->Port = $port;
    switch ($secure) {
      case 'insecure':
        $mail->SMTPSecure = '';
        break;
      case 'starttls':
        $mail->SMTPSecure = 'tls';
        break;
      case 'ssl':
        $mail->SMTPSecure = 'ssl';
        break;
      default:
        $mail->SMTPSecure = '';
        break;
    }
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $password;

    try {
      $mail->SmtpConnect();
      $mail->SmtpClose();
    } catch (Throwable $exception) {
      $this->logException($exception, 'Testing SMTP server '.$user.'@'.$host.':'.$port.' failed.');
      $result = false;
    }

    return $result;
  }

  /**
   * Check whether the shared object exists. Note: this function has
   * to be executed under the uid of the user the object belongs
   * to. See ConfigService::sudo().
   *
   * @param int $id The @b numeric id of the object (not the name).
   *
   * @param string $group The group to share the item with.
   *
   * @param string $type The type of the item, for exmaple calendar,
   * event, folder, file etc.
   *
   * @param null|string $shareOwner
   *
   * @return bool @c true for success, @c false on error.
   */
  public function groupSharedExists(int $id, string $group, string $type, ?string $shareOwner = null)
  {
    // First check whether the object is already shared.
    $shareType  = IShare::TYPE_GROUP;
    $groupPerms = self::SHARE_PERMISSIONS;

    if ($type != 'folder' && $type != 'file') {
      $this->logError('only folder and file for now');
      return false;
    }

    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    // retrieve all shared items for $shareOwner
    foreach ($this->shareManager->getSharesBy($shareOwner, $shareType) as $share) {
      if ($share->getNodeId() === $id && $share->getSharedWith() == $group) {
        return $share->getPermissions() === $groupPerms;
      }
    }
    return false;
  }

  /**
   * Share an object between the members of the specified group. Note:
   * this function has to be executed under the uid of the user the
   * object belongs to. See ConfigService::sudo().
   *
   * @param int $id The @b numeric id of the object (not the name).
   *
   * @param string $groupId The group to share the item with.
   *
   * @param string $type The type of the item, for exmaple calendar,
   * event, folder, file etc.
   *
   * @param null|string  $shareOwner The user sharing the object.
   *
   * @return bool @c true for success, @c false on error.
   */
  public function groupShareObject(int $id, string $groupId, string $type = 'calendar', ?string $shareOwner = null):bool
  {
    $shareType = IShare::TYPE_GROUP;
    $groupPerms = self::SHARE_PERMISSIONS;

    if ($type != 'folder' && $type != 'file') {
      $this->logError('only folder and file for now');
      return false;
    }

    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    // retrieve all shared items for $shareOwner
    foreach ($this->shareManager->getSharesBy($shareOwner, $shareType) as $share) {
      if ($share->getNodeId() === $id && $share->getSharedWith() == $groupId) {
        // check permissions
        if ($share->getPermissions() !== $groupPerms) {
          $share->setPermissions($groupPerms);
          $this->shareManager->updateShare($share);
        }
        return $share->getPermissions() !== $groupPerms;
      }
    }

    // Otherwise it should be legal to attempt a new share ...
    $share = $this->shareManager->newShare();
    $share->setNodeId($id);
    $share->setSharedWith($groupId);
    $share->setPermissions($groupPerms);
    $share->setShareType($shareType);
    $share->setShareOwner($shareOwner);
    $share->setSharedBy($shareOwner);

    try {
      $this->shareManager->createShare($share);
      return true;
    } catch (Throwable $t) {
      $this->logException($t);
      return false;
    }
  }

  /**
   * Return @c true if the share-group is set as application
   * configuration option and exists.
   *
   * @return bool
   */
  public function shareGroupExists():bool
  {
    return $this->groupExists();
  }

  /**
   * Return @c true if the share-owner exists and belongs to the
   * orchestra user group (and only to this group).
   *
   * @param null|string $shareOwnerId Optional. If unset, then the uid is
   * fetched from the application configuration options.
   *
   * @return bool @c true on success.
   */
  public function shareOwnerExists(?string $shareOwnerId = null)
  {
    $shareGroupId = $this->getAppValue(ConfigService::USER_GROUP_KEY);
    empty($shareOwnerId) && $shareOwnerId = $this->getConfigValue(ConfigService::SHAREOWNER_KEY);

    if (empty($shareOwnerId)) {
      return false;
    }

    $shareOwner = $this->user($shareOwnerId); // get the user object

    if (!$shareOwner->isEnabled()) {
      return false;
    }

    /* Ok, the user exists and is configured as "share-owner" in our
     * poor orchestra app, now perform additional consistency checks.
     *
     * How paranoid should we be?
     */
    $groups = $this->groupManager()->getUserGroups($shareOwner);

    // The one and only group should be our's.
    if (!isset($groups[$shareGroupId])) {
      return false;
    }

    // Add more checks as needed ... ;)
    return true;
  }

  /**
   * Make sure the "sharing" user exists, create it when necessary.
   * May throw an exception.
   *
   * @param string $shareOwnerId The account id holding the shared resources.
   *
   * @param null|string $shareOwnerPassword
   *
   * @return bool @c true on success.
   */
  public function checkShareOwner(string $shareOwnerId, ?string $shareOwnerPassword = null):bool
  {
    $shareGroupId = $this->getAppValue(ConfigService::USER_GROUP_KEY, false);
    if (empty($shareGroupId)) {
      return false; // need at least this group!
    }

    $created = false;
    $shareOwner = null;

    // Create the user if necessary
    if (!$this->userManager()->userExists($shareOwnerId)) {
      $this->logError("User does not exist");
      if (empty($shareOwnerPassword)) {
        $shareOwnerPassword = $this->generateRandomBytes(30);
      }

      if (!$this->isSubAdminOfGroup()) {
        $this->logError("Permission denied: ".$this->userId()." is not a group admin of ".$shareGroupId.".");
        return false;
      }
      $userBackend = $this->user()->getBackend();

      $shareOwner = $this->userManager()->createUserFromBackend($shareOwnerId, $shareOwnerPassword, $userBackend);
      if (!empty($shareOwner)) {
        $this->logInfo("User created");
        // trigger address book creation and things
        $this->eventDispatcher->dispatch(\OCP\IUser::class . '::firstLogin', new GenericEvent($shareOwner));
        $created = true;
      } else {
        $this->logError("User could not be created");
        return false;
      }
    } else {
      $shareOwner = $this->userManager()->get($shareOwnerId);
    }

    if (empty($shareOwner)) {
      $this->logError("Share-owner " . $shareOwnerId . " could not be found or created.");
      return false;
    }

    // Sutff the user in its appropriate group
    if (!$this->inGroup($shareOwnerId, $shareGroupId)) {
      $this->logError("not in group");
      $shareGroup = $this->group($shareGroupId);
      if (empty($shareGroup)) {
        $this->logError("Could not get group " . $shareGroupId . ".");
      } else {
        $shareGroup->addUser($shareOwner);
      }
      // check again, addUser() has no return value
      if (!$this->inGroup($shareOwnerId, $shareGroupId)) {
        $this->logError("Could not add " . $shareOwnerId . " to group " . $shareGroupId . ".");
        if ($created) {
          $this->logError("Deleting just created user " . $shareOwnerId  . ".");
          $shareOwner->delete();
        }
        return false;
      }
      $this->logDebug("added to group");
    }

    return $this->shareOwnerExists($shareOwnerId);
  }

  /**
   * We require that the share-owner owns a directory shared with the
   * orchestra group. Check whether this folder exists.
   *
   * @param null|string $sharedFolder Optional. If unset, the name is fetched
   * from the application configuration options.
   *
   * @return bool @c true on success.
   */
  public function sharedFolderExists(?string $sharedFolder = '')
  {
    if (!$this->shareOwnerExists()) {
      return false;
    }

    $shareGroup   = $this->getAppValue(ConfigService::USER_GROUP_KEY);
    $shareGroupIds = [];
    foreach (AuthorizationService::GROUP_SUFFIX_LIST as $permissions => $groupSuffix) {
      $permissions |= AuthorizationService::IMPLIED_PERMISSIONS[$permissions];
      if ($permissions & AuthorizationService::PERMISSION_FILESYSTEM) {
        $shareGroupIds[] = $shareGroup . $groupSuffix;
      }
    }
    $shareOwner   = $this->getConfigValue(ConfigService::SHAREOWNER_KEY);
    // $groupadmin   = $this->userId();

    $sharedFolder == '' && $sharedFolder = $this->getConfigValue('sharedfolder', '');

    if ($sharedFolder == '') {
      $this->logError('no folder');
      // not configured
      return false;
    }

    //$id = \OC\Files\Cache\Cache::getId($sharedFolder, $vfsroot);
    $result = $this->sudo($shareOwner, function(string $shareOwner) use ($sharedFolder, $shareGroupIds) {

      if ($sharedFolder[0] != '/') {
        $sharedFolder = '/'.$sharedFolder;
      }

      try {
        $id = $this->rootFolder->getUserFolder($shareOwner)->get($sharedFolder)->getId();
        $this->logDebug('Shared folder id: ' . $id);
        foreach ($shareGroupIds as $shareGroupId) {
          if (!$this->groupSharedExists($id, $shareGroupId, 'folder', $shareOwner)) {
            return false;
          }
        }
        return true;
      } catch (Throwable $e) {
        $this->logError('No file id for  ' . $sharedFolder . ' ' . $e->getMessage());
        return false;
      }
    });

    return $result;
  }

  /**
   * Check for the existence of the shared folder and create it when
   * not found.
   *
   * @param string $sharedFolder The name of the folder.
   *
   * @param null|string $postfix Postfix to append to the folder name. This is
   * need in order to implement the migration from the personally shared
   * share-holder folder to the anonymously shared group-folder.
   *
   * @return bool \true on success.
   */
  public function checkGroupSharedFolder(string $sharedFolder, ?string $postfix = null):bool
  {
    if ($postfix === null) {
      $sharedFolder .= '-testing'; // @todo remove after it has proven useful
    }

    $this->logInfo('TRY CREATE ' . $sharedFolder);

    $adminGroupId = $this->subAdminGroupId();
    if (empty($adminGroupId)) {
      $this->logInfo('NO ADMIN GROUP');
      return false; // need at least this group!
    }

    $shareGroupId = $this->groupId();
    if (empty($shareGroupId)) {
      $this->logInfo('NO SHARE GROUP');
      return false; // need at least this group!
    }
    $shareGroupIds = [];
    foreach (AuthorizationService::GROUP_SUFFIX_LIST as $permissions => $groupSuffix) {
      $permissions |= AuthorizationService::IMPLIED_PERMISSIONS[$permissions];
      if ($permissions & AuthorizationService::PERMISSION_FILESYSTEM) {
        $shareGroupIds[] = $shareGroupId . $groupSuffix;
      }
    }

    $groupAdmin = $this->userId();
    foreach ($shareGroupIds as $shareGroupId) {
      if (!$this->isSubAdminOfGroup($groupAdmin, $shareGroupId)) {
        $this->logError('Permission denied: ' . $groupAdmin . ' is not a group admin of ' . $shareGroupId . '.');
        return false;
      }
    }

    $this->logInfo('HELLO');

    /** @var GroupFoldersService $groupFoldersService */
    $groupFoldersService = $this->appContainer()->get(GroupFoldersService::class);

    $folderInfo = $groupFoldersService->getFolder($sharedFolder);

    try {
      if (empty($folderInfo)) {
        $folderInfo = $groupFoldersService->createFolder(
          $sharedFolder,
          array_combine($shareGroupIds, array_fill(0, count($shareGroupIds), GroupFoldersService::PERMISSION_ALL)),
          [ $adminGroupId => GroupFoldersService::MANAGER_TYPE_GROUP, ],
        );
        $this->logInfo('TRIED TO CREATE ' . $sharedFolder);
      } else {
        foreach ($shareGroupIds as $shareGroupId) {
          $groupFoldersService->addGroupToFolder(
            $sharedFolder,
            $shareGroupId,
            GroupFoldersService::PERMISSION_ALL,
          );
        }
        $groupFoldersService->addManagerToFolder(
          $sharedFolder,
          $adminGroupId,
          GroupFoldersService::MANAGER_TYPE_GROUP,
        );
        $this->logInfo('TRIED TO MODIFY ' . $sharedFolder);
      }
    } catch (Throwable $t) {
      $this->logException($t, 'Unable to create or modify group shared folder ' . $sharedFolder);
      return false;
    }

    $this->logInfo('HELLO');

    return true;
  }

  /**
   * Check for the existence of the shared folder and create it when
   * not found.
   *
   * @param string $sharedFolder The name of the folder.
   *
   * @return bool @c true on success.
   */
  public function checkSharedFolder(string $sharedFolder):bool
  {
    try {
      $this->checkGroupSharedFolder($sharedFolder);
    } catch (Throwable $t) {
      $this->logException($t, 'CANNOT CREATE GROUP SHARED FOLDER');
    }

    if ($sharedFolder == '') {
      return false;
    }

    if ($sharedFolder[0] != '/') {
      $sharedFolder = '/'.$sharedFolder;
    }

    if ($this->sharedFolderExists($sharedFolder)) {
      // no need to create
      return true;
    }

    $shareGroup = $this->getAppValue(ConfigService::USER_GROUP_KEY);
    $shareGroupIds = [];
    foreach (AuthorizationService::GROUP_SUFFIX_LIST as $permissions => $groupSuffix) {
      $permissions |= AuthorizationService::IMPLIED_PERMISSIONS[$permissions];
      if ($permissions & AuthorizationService::PERMISSION_FILESYSTEM) {
        $shareGroupIds[] = $shareGroup . $groupSuffix;
      }
    }
    $groupAdmin = $this->userId();
    $shareOwner = $this->getConfigValue(ConfigService::SHAREOWNER_KEY);

    foreach ($shareGroupIds as $shareGroupId) {
      if (!$this->isSubAdminOfGroup($groupAdmin, $shareGroupId)) {
        $this->logError('Permission denied: ' . $groupAdmin . ' is not a group admin of ' . $shareGroupId . '.');
        return false;
      }
    }

    // try to create the folder and share it with the group
    /* $result = */ $this->sudo($shareOwner, function(string $shareOwner) use ($sharedFolder, $shareGroupIds, $groupAdmin) {
      $userId    = $this->userId();
      // $user      = $this->user();

      $rootView = $this->rootFolder->getUserFolder($shareOwner);

      if ($rootView->nodeExists($sharedFolder)) {
        $node = $rootView->get($sharedFolder);
        if ($node->getType() != FileInfo::TYPE_FOLDER || !$node->isShareable()) {
          try {
            $node->delete();
          } catch (Throwable $t) {
            $this->logException($t);
            return false;
          }
        }
      }

      //->get($sharedFolder)->getId();
      if (!$rootView->nodeExists($sharedFolder) && !$rootView->newFolder($sharedFolder)) {
        return false;
      }

      $node = $rootView->nodeExists($sharedFolder) ? $rootView->get($sharedFolder) : null; //
      if (empty($node) || $node->getType() != FileInfo::TYPE_FOLDER) {
        throw new Exception($this->l->t('Folder \`%s\' could not be created', [$sharedFolder]));
      }

      // Now it should exist as directory and $node should contain its file-info

      $result = true;
      if ($node) {
        $id = $node->getId();
        $this->logDebug('shared folder id ' . $id);
        foreach ($shareGroupIds as $shareGroupId) {
          if (!$this->groupShareObject($id, $shareGroupId, 'folder', $userId)
              || !$this->groupSharedExists($id, $shareGroupId, 'folder', $userId)) {
            $result = false;
          }
        }
      } else {
        $this->logError('No file info for ' . $sharedFolder);
        $result = false;
      }

      return $result;
    });

    return $this->sharedFolderExists($sharedFolder);
  }

  /**
   * Check for the existence of the shared folder and create it when
   * not found.
   *
   * @param string $sharedFolder The name of the folder.
   *
   * @return bool @c true on success.
   *
   * @todo The sudo() stuff should no longer be neccessary.
   */
  public function checkLinkSharedFolder(string $sharedFolder):bool
  {
    if ($sharedFolder == '') {
      return null;
    }

    if ($sharedFolder[0] != '/') {
      $sharedFolder = '/'.$sharedFolder;
    }

    $shareGroup = $this->getAppValue(ConfigService::USER_GROUP_KEY);
    $groupAdmin = $this->userId();
    $shareOwner = $this->getConfigValue(ConfigService::SHAREOWNER_KEY);

    if (!$this->isSubAdminOfGroup()) {
      $this->logError("Permission denied: ".$groupAdmin." is not a group admin of ".$shareGroup.".");
      return null;
    }

    // try to create the folder and share it with the group
    $result = $this->sudo($shareOwner, function(string $shareOwner) use ($sharedFolder) {
      $userId    = $this->userId();
      // $user      = $this->user();

      $rootView = $this->rootFolder->getUserFolder($shareOwner);

      $node = $rootView->nodeExists($sharedFolder) ? $rootView->get($sharedFolder) : null;
      if (!empty($node) && ($node->getType() != FileInfo::TYPE_FOLDER || !$node->isShareable())) {
        try {
          $node->delete();
        } catch (Throwable $t) {
          $this->logException($t);
          return null;
        }
      }

      if (empty($node) && !$rootView->newFolder($sharedFolder)) {
        return null;
      }

      $node = $rootView->nodeExists($sharedFolder) ? $rootView->get($sharedFolder) : null;
      if (empty($node) || $node->getType() != FileInfo::TYPE_FOLDER) {
        throw new Exception($this->l->t('Folder \`%s\' could not be created', [$sharedFolder]));
      }

      // Now it should exist as directory and $node should contain its file-info

      if ($node) {
        $url = $this->sharingService->linkShare($node, $userId);
        if (empty($url)) {
          return null;
        }
      } else {
        $this->logError('No file info for ' . $sharedFolder);
        return null;
      }

      return $url;
    });

    return $result;
  }

  /**
   * Check for existence of the project folder and create it when not
   * found.
   *
   * @param string $projectFolder The name of the folder. The name may
   * be composed of several path components.
   *
   * @return bool @c true on success.
   */
  public function checkProjectFolder(string $projectFolder):bool
  {
    $sharedFolder = $this->getConfigValue(ConfigService::SHARED_FOLDER);

    if (!$this->sharedFolderExists($sharedFolder)) {
      return false;
    }

    $shareGroup = $this->getAppValue(ConfigService::USER_GROUP_KEY);
    // $shareOwner = $this->getConfigValue(ConfigService::SHAREOWNER_KEY);
    $groupAdmin = $this->userId();

    if (!$this->isSubAdminOfGroup()) {
      $this->logError("Permission denied: ".$groupAdmin." is not a group admin of ".$shareGroup.".");
      return false;
    }

    /* Ok, then there should be a folder /$sharedFolder */

    $rootView = $this->rootFolder->getUserFolder($groupAdmin);

    $projectFolder = trim(preg_replace('|[/]+|', '/', $projectFolder), "/");
    $projectFolder = Util::explode('/', $projectFolder);
    if (count($projectFolder) > 0 && $projectFolder[0] == $sharedFolder) {
      array_shift($projectFolder);
    }

    $path = '/'.$sharedFolder;

    foreach ($projectFolder as $pathComponent) {
      $path .= '/'.$pathComponent;
      //trigger_error("Path: ".$path, E_USER_NOTICE);
      try {
        $node = $rootView->get($path);
        if ($node->getType() != FileInfo::TYPE_FOLDER
            || $node->getPermissions() != self::SHARE_PERMISSIONS) {
          try {
            $node->delete();
          } catch (Throwable $t) {
            $this->logException($t);
            $this->logError('Could not delete node ' . $path
                            . ' type ' . $node->getType()
                            . ' permissions ' . $node->getPermissions());
            return false;
          }
        }
      } catch (Throwable $t) {
        $node = null;
      }
      if (empty($node)) {
        try {
          $node = $rootView->newFolder($path);
        } catch (Throwable $e) {
          $this->logError('Could not create ' . $path . ' ' . $e->getMessage() . ' ' . $e->getTraceAsString());
          return false;
        }
      }
      if ($node->getType() != FileInfo::TYPE_FOLDER) {
        $this->logError($path . ' is not a folder!');
        return false;
      }
    }

    return true;
  }

  /**
   * Check for existence of the given calendar. Create one if it could
   * not be found. Make sure it is shared between the orchestra group.
   *
   * @param string $uri The local URI of the calendar.
   *
   * @param null|string $displayName The display-name of the calendar.
   *
   * @param null|int $id The id of the calendar.
   *
   * @return int -1 on error, calendar id on success.
   */
  public function checkSharedCalendar(string $uri, ?string $displayName = null, ?int $id = null):int
  {
    if (empty($uri)) {
      return -1;
    }

    empty($displayName) && ($displayName = ucfirst($uri));

    $shareOwnerId = $this->shareOwnerId();
    $userGroupId = $this->groupId();

    if (empty($shareOwnerId) || empty($userGroupId)) {
      return -1;
    }

    $shareGroupIds = [];
    foreach (AuthorizationService::GROUP_SUFFIX_LIST as $permissions => $groupSuffix) {
      $permissions |= AuthorizationService::IMPLIED_PERMISSIONS[$permissions];
      if ($permissions & AuthorizationService::PERMISSION_CALENDAR) {
        $shareGroupIds[] = $userGroupId . $groupSuffix;
      }
    }

    $result = $this->sudo($shareOwnerId, function(string $shareOwnerId) use ($uri, $id, $displayName, $shareGroupIds) {
      $this->logDebug("Sudo to " . $this->userId());

      // get or create the calendar
      if (!empty($id) && $id > 0) {
        $calendar = $this->calDavService->calendarById($id);
      } else {
        $calendar = $this->calDavService->calendarByName($displayName);
      }

      if (empty($calendar)) {
        $this->logDebug("Calendar " . $displayName . " does not seem to exist.");
        $id = $this->calDavService->createCalendar($uri, $displayName, $shareOwnerId);
        if ($id < 0) {
          $this->logError("Unabled to create calendar " . $displayName);
          return -1;
        }
        $this->logDebug("Created calendar " . $displayName . " with id " . $id);
        $calendar = $this->calDavService->calendarById($id);
        if (empty($calendar)) {
          $this->logError("Failed to create calendar " . $displayName);
          $this->calDavService->deleteCalendar($id);
          return -1;
        }
        $created = true;
      } else {
        $id = $calendar->getKey();
        $created = false;
      }

      foreach ($shareGroupIds as $shareGroupId) {
        // make sure it is shared with the group
        if (!$this->calDavService->groupShareCalendar($id, $shareGroupId)) {
          $this->logError("Unable to share " . $uri . " with " . $shareGroupId);
          if ($created) {
            $this->calDavService->deleteCalendar($id);
          }
          return -1;
        }
      }

      // check the display name
      if ($calendar->getDisplayName() != $displayName) {
        $this->logDebug("Changing name of " . $id . " from " . $calendar->getDisplayName() . " to " . $displayName);
        $this->calDavService->displayName($id, $displayName);
      }

      return $id;
    });

    $this->logDebug("returning " . $result);

    return $result;
  }

  /** @return bool */
  private function sharedAddressBooksExist():bool
  {
    $configAddressBooks = [
      'general' => [
        'key' => $this->getConfigValue('generaladdressbook'.'id', -1),
        'found' => false,
      ],
      'musicians' => [
        'key' => $this->addressBookProvider->getContactsAddressBook()->getKey(),
        'found' => false,
      ],
    ];

    $addressBooks = $this->contactsManager->getUserAddressBooks();

    foreach ($addressBooks as $addressBook) {
      $displayName = $addressBook->getDisplayName();
      $key = $addressBook->getKey();
      $uri = $addressBook->getUri();
      $shared = $addressBook->isShared();
      $this->logDebug('AddressBook: '.print_r([
        'DPY' => $displayName,
        'KEY' => $key,
        'URI' => $uri,
        'SHR' => $shared,
      ], true));
      foreach ($configAddressBooks as &$configBook) {
        if ($configBook['key'] == $key) {
          $configBook['found'] = true;
        }
      }
    }

    $result = true;
    foreach ($configAddressBooks as &$configBook) {
      $result = $result &&  $configBook['found'];
    }

    return $result;
  }

  /**
   * Check for existence of the given addressBook. Create one if it could
   * not be found. Make sure it is shared between the orchestra group.
   *
   * @param string $uri The local URI of the addressBook.
   *
   * @param null|string $displayName The display-name of the addressBook.
   *
   * @param null|int $id The id of the addressBook.
   *
   * @return int -1 on error, addressBook id on success.
   */
  public function checkSharedAddressBook(string $uri, ?string $displayName = null, ?int $id = null):int
  {
    if (empty($uri)) {
      return -1;
    }

    empty($displayName) && ($displayName = ucfirst($uri));

    $shareOwnerId = $this->shareOwnerId();
    $userGroupId = $this->groupId();

    if (empty($shareOwnerId) || empty($userGroupId)) {
      return -1;
    }

    $shareGroupIds = [];
    foreach (AuthorizationService::GROUP_SUFFIX_LIST as $permissions => $groupSuffix) {
      $permissions |= AuthorizationService::IMPLIED_PERMISSIONS[$permissions];
      if ($permissions & AuthorizationService::PERMISSION_ADDRESSBOOK) {
        $shareGroupIds[] = $userGroupId . $groupSuffix;
      }
    }

    return $this->sudo($shareOwnerId, function(string $shareOwnerId) use ($uri, $displayName, $id, $shareGroupIds) {
      $this->logDebug("Sudo to " . $this->userId());

      // get or create the addressBook
      if (!empty($id) && $id > 0) {
        $addressBook = $this->cardDavService->addressBookById($id);
      } else {
        $addressBook = $this->cardDavService->addressBookByName($displayName);
      }

      if (empty($addressBook)) {
        $this->logError("AddressBook " . $uri . " / " . $displayName . " does not seem to exist.");
        $id = $this->cardDavService->createAddressBook($uri, $displayName, $shareOwnerId);
        if ($id < 0) {
          $this->logError("Unabled to create addressBook " . $displayName);
          return -1;
        }
        $addressBook = $this->cardDavService->addressBookById($id);
        if (empty($addressBook)) {
          $this->logError("Failed to create addressBook " . $displayName);
          $this->cardDavService->deleteAddressBook($id);
          return -1;
        }
        $created = true;
      } else {
        $id = $addressBook->getKey();
        $created = false;
      }

      foreach ($shareGroupIds as $shareGroupId) {
        // make sure it is shared with the group
        if (!$this->cardDavService->groupShareAddressBook($id, $shareGroupId)) {
          $this->logError("Unable to share " . $uri . " with " . $shareGroupId);
          if ($created) {
            $this->cardDavService->deleteAddressBook($id);
          }
          return -1;
        }
      }

      // check the display name
      if ($addressBook->getDisplayName() != $displayName) {
        $this->logDebug("Changing name of " . $id . " from " . $addressBook->getDisplayName() . " to " . $displayName);
        $this->cardDavService->displayName($id, $displayName);
      }

      return $id;
    });

    return -1;
  }

  /**
   * Check whether we have data-base access by connecting to the
   * data-base server and selecting the configured data-base.
   *
   * @return bool @c true on success.
   *   */
  public function databaseAccessible()
  {
    $connection = null;

    // Why was this here? It breaks things ...
    // $this->entityManager->reopen();

    $connection = $this->entityManager->getConnection();

    if (empty($connection)) {
      $this->logError('db connection empty');
      return false;
    }

    $selfOpened = false;
    if (!$connection->ping()) {
      if (!$connection->connect()) {
        $this->logError('db cannot connect');
        return false;
      }
      $selfOpened = true;
    }

    if (!$connection->ping()) {
      $this->logError('db cannot ping');
      return false;
    }

    if ($selfOpened) {
      $connection->close();
    }

    return true;
  }

  /** @return bool */
  public function noUnappliedMigrations():bool
  {
    if ($this->migrationsService->needsMigration()) {
      $migrations = $this->migrationsService->getUnapplied();
      throw new RuntimeException($this->l->t('Unapplied migrations: %s.', implode(', ', $migrations)));
    }
    return true;
  }
}

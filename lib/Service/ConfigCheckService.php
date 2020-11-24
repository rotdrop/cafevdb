<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IUser;
use OCP\IConfig;
use OCP\Share\IShare;
use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;

use OCA\CAFEVDB\Database\EntityManager;

use OCA\CAFEVDB\Common\Util; // some static helpers, only for explode

/**Check for a usable configuration.
 */
class ConfigCheckService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var EntityManager */
  private $entityManager;

  /** @var IRootFolder  */
  private $rootFolder;

  /** @var \OCP\Share\IManager */
  private $shareManager;

  /** @var CalDavService */
  private $calDavService;

  /** @var CardDavService */
  private $cardDavService;

  /** @var \OCP\Calendar\IManager */
  private $calendarManager;

  /** @var \OCP\Contacts\IManager */
  private $contactsManager;

  public function __construct(
    ConfigService $configService,
    EntityManager $entityManager,
    IRootFolder $rootFolder,
    \OCP\Share\IManager $shareManager,
    \OCP\Calendar\IManager $calendarManager,
    \OCP\Contacts\IManager $contactsManager,
    CalDavService $calDavService,
    CardDavService $cardDavService,
    EventsService $eventsService
    //, \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit $phpMyEdit
    //, \OCA\CAFEVDB\Database\Connection $dbConnection
    //, \OCA\CAFEVDB\Service\RequestParameterService $requestParameters
    //, \OCA\CAFEVDB\PageRenderer\Musicians $musiciansView
  ) {
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->rootFolder = $rootFolder;
    $this->shareManager = $shareManager;
    $this->calendarManager = $calendarManager;
    $this->contactsManager = $contactsManager;
    $this->calDavService = $calDavService;
    $this->cardDavService = $cardDavService;

    //$contactsService = new ContactsService($configService, $entityManager, $geoCodingService);
    //$contactsService->playground();

    //new PMEConfig($configService, $toolTipsService, $urlGenerator);
    //new PMEOptions();
    //new PHPMyEdit($this->entityManager->getConnection(), $pmeConfig);

    // $result = $mySQLiShim->query('SELECT * FROM Musiker');
    // while ($row = $mySQLiShim->fetch_array($result)) {
    //   $this->logInfo(__METHOD__." ".print_r($row, true));
    // }
    //new \OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit($connection, $pmeConfig);
    //new \OCA\CAFEVDB\PageRenderer\Musicians($configService, $requestParameters, $entityManager, $phpMyEdit);
    // try {
    //   $musiciansView->disableProjectMode();
    //   ob_start();
    //   $musiciansView->render();
    //   $table = ob_get_contents();
    //   ob_end_clean();
    //   $this->logInfo($table);
    // } catch (\Throwable $t) {
    //   $this->logException($t);
    //   $table = ob_get_contents();
    //   ob_end_clean();
    //   $this->logInfo($table);
    // }
    // try {
    //   $query = "SELECT * FROM Musicians";
    //   $this->logInfo("Query: ".$query);
    //   $result = $phpMyEdit->myquery($query);
    //   if (empty($result)) {
    //     if ($phpMyEdit->exception) {
    //       $this->logException($phpMyEdit->exception);
    //     }
    //   }
    //   while (($row = $phpMyEdit->sql_fetch($result))) {
    //     $this->logInfo(print_r($row, true));
    //   }
    // } catch (\Throwable $t) {
    //   $this->logException($t);
    // }
    // try {
    //   $query = "SELECT * FROM Musicians";
    //   $this->logInfo("Query: ".$query);
    //   $result = $dbConnection->executeQuery($query);
    //   while (($row = $result->fetch())) {
    //     $this->logInfo(print_r($row, true));
    //   }
    // } catch (\Throwable $t) {
    //   $this->logException($t);
    // }
  }

  /**Return an array with necessary configuration items, being either
   * true or false, depending the checks performed. The summary
   * component is just the logic and of all other items.
   *
   * @return array
   * ```
   * [ 'summary',
   *   'orchestra',
   *   'usergroup',
   *   'shareowner',
   *   'sharedfolder',
   *   'database',
   *   'encryptionkey'
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

    foreach (['orchestra','usergroup','shareowner','sharedfolder','database','encryptionkey'] as $key) {
      $result[$key] = ['status' => false, 'message' => ''];
    }

    $key ='orchestra';
    try {
      $result[$key]['status'] = $this->encryptionKeyValid() && $this->getConfigValue('orchestra');
    } catch (\Exception $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $key = 'encryptionkey';
    try {
      $result[$key]['status'] = $result['orchestra']['status'] &&$this->encryptionKeyValid();
    } catch (\Exception $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $key = 'database';
    try {
      $result[$key]['status'] = $result['orchestra']['status'] && $this->databaseAccessible();
    } catch (\Exception $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $key = 'usergroup';
    try {
      $result[$key]['status'] = $this->shareGroupExists();
    } catch (\Exception $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $key = 'shareowner';
    try {
      $result[$key]['status'] = $result['usergroup']['status'] && $this->shareOwnerExists();
    } catch (\Exception $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $key = 'sharedfolder';
    try {
      $result[$key]['status'] = $result['shareowner']['status'] && $this->sharedFolderExists();
    } catch (\Exception $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $summary = true;
    foreach ($result as $key => $value) {
      $summary = $summary && $value['status'];
    }
    $result['summary'] = $summary;

    return $result;
  }

  public function checkImapServer($host, $port, $secure, $user, $password)
  {
    $oldReporting = ini_get('error_reporting');
    ini_set('error_reporting', $oldReporting & ~E_STRICT);

    $imap = new \Net_IMAP($host, $port, $secure == 'starttls' ? true : false, 'UTF-8');
    $result = $imap->login($user, $password) === true;
    $imap->disconnect();

    ini_set('error_reporting', $oldReporting);

    return $result;
  }

  public function checkSmtpServer($host, $port, $secure, $user, $password)
  {
    $result = true;

    $mail = new \PHPMailer(true);
    $mail->CharSet = 'utf-8';
    $mail->SingleTo = false;
    $mail->IsSMTP();

    $mail->Host = $host;
    $mail->Port = $port;
    switch ($secure) {
    case 'insecure': $mail->SMTPSecure = ''; break;
    case 'starttls': $mail->SMTPSecure = 'tls'; break;
    case 'ssl':      $mail->SMTPSecure = 'ssl'; break;
    default:         $mail->SMTPSecure = ''; break;
    }
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $password;

    try {
      $mail->SmtpConnect();
      $mail->SmtpClose();
    } catch (\Exception $exception) {
      $result = false;
    }

    return $result;
  }

  /**Check whether the shared object exists. Note: this function has
   * to be executed under the uid of the user the object belongs
   * to. See ConfigService::sudo().
   *
   * @param $id The @b numeric id of the object (not the name).
   *
   * @param $group The group to share the item with.
   *
   * @param $type The type of the item, for exmaple calendar,
   * event, folder, file etc.
   *
   * @return @c true for success, @c false on error.
   */
  public function groupSharedExists($id, $group, $type, $shareOwner = null)
  {
    // First check whether the object is already shared.
    $shareType  = IShare::TYPE_GROUP;
    $groupPerms = (\OCP\Constants::PERMISSION_CREATE|
                   \OCP\Constants::PERMISSION_READ|
                   \OCP\Constants::PERMISSION_UPDATE|
                   \OCP\Constants::PERMISSION_DELETE);

    if ($type != 'folder' && $type != 'file') {
      $this->logError('only folder and file for now');
      return false;
    }

    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    // retrieve all shared items for $shareOwner
    foreach($this->shareManager->getSharesBy($shareOwner, $shareType) as $share) {
      if ($share->getNodeId() === $id) {
        return $share->getPermissions() === $groupPerms;
      }
    }
    return false;
  }

  /**Share an object between the members of the specified group. Note:
   * this function has to be executed under the uid of the user the
   * object belongs to. See ConfigService::sudo().
   *
   * @param $id The @b numeric id of the object (not the name).
   *
   * @param $groupId The group to share the item with.
   *
   * @param $type The type of the item, for exmaple calendar,
   * event, folder, file etc.
   *
   * @param $shareOwner The user sharing the object.
   *
   * @return @c true for success, @c false on error.
   */
  public function groupShareObject($id, $groupId, $type = 'calendar', $shareOwner = null)
  {
    $shareType = IShare::TYPE_GROUP;
    $groupPerms = (\OCP\Constants::PERMISSION_CREATE|
                   \OCP\Constants::PERMISSION_READ|
                   \OCP\Constants::PERMISSION_UPDATE|
                   \OCP\Constants::PERMISSION_DELETE);

    if ($type != 'folder' && $type != 'file') {
      $this->logError('only folder and file for now');
      return false;
    }

    if (empty($shareOwner)) {
      $shareOwner = $this->userId();
    }

    // retrieve all shared items for $shareOwner
    foreach($this->shareManager->getSharesBy($shareOwner, $shareType) as $share) {
      if ($share->getNodeId() === $id) {
        // check permissions
        if ($share->getPermissions() !== $groupPerms) {
          $share->setPermissions($groupPerms);
          $this->shareManager->updateShare($share);
        }
      }
    }

    // Otherwise it should be legal to attempt a new share ...
    $share = $this->shareManager->newShare();
    $share->setNodeId($id);
    $share->setSharedWith($groupId);
    $share->setPermissions($groupPerms);
    $share->setShareType($shareType);
    $share->setShareOwner($shareOwner);
    $share->setSharedBy(empty($shareOwner) ? $this->getUserId() : $shareOwner);

    return $this->shareManager->createShare($share);
  }

  /**Return @c true if the share-group is set as application
   * configuration option and exists.
   */
  public function shareGroupExists()
  {
    return $this->groupExists();
  }

  /**Return @c true if the share-owner exists and belongs to the
   * orchestra user group (and only to this group).
   *
   * @param $shareOwnerId Optional. If unset, then the uid is
   * fetched from the application configuration options.
   *
   * @return bool, @c true on success.
   */
  public function shareOwnerExists($shareOwnerId = null)
  {
    $shareGroupId = $this->getAppValue('usergroup');
    empty($shareOwnerId) && $shareOwnerId = $this->getConfigValue('shareowner');

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

  /**Make sure the "sharing" user exists, create it when necessary.
   * May throw an exception.
   *
   * @param $shareOwnerId The account id holding the shared resources.
   *
   * @return bool, @c true on success.
   */
  public function checkShareOwner($shareOwnerId, $shareOwnerPassword = null)
  {
    if (!($shareGroupId = $this->getAppValue('usergroup', false))) {
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
      $shareOwner = $this->userManager()->createUser($shareOwnerId, $shareOwnerPassword);
      if (!empty($shareOwner)) {
        $this->logError("User created");
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
      $this->logError("added to group");
    }

    return $this->shareOwnerExists($shareOwnerId);
  }

  /**We require that the share-owner owns a directory shared with the
   * orchestra group. Check whether this folder exists.
   *
   * @param $sharedFolder Optional. If unset, the name is fetched
   * from the application configuration options.
   *
   * @return bool, @c true on success.
   */
  public function sharedFolderExists($sharedFolder = '')
  {
    if (!$this->shareOwnerExists()) {
      return false;
    }

    $shareGroup   = $this->getAppValue('usergroup');
    $shareOwner   = $this->getConfigValue('shareowner');
    $groupadmin   = $this->userId();

    $sharedFolder == '' && $sharedFolder = $this->getConfigValue('sharedfolder', '');

    if ($sharedFolder == '') {
      $this->logError('no folder');
      // not configured
      return false;
    }

    //$id = \OC\Files\Cache\Cache::getId($sharedFolder, $vfsroot);
    $result = $this->sudo($shareOwner, function() use ($sharedFolder, $shareGroup, $shareOwner) {

      if ($sharedFolder[0] != '/') {
        $sharedFolder = '/'.$sharedFolder;
      }

      try {
        $id = $this->rootFolder->getUserFolder($shareOwner)->get($sharedFolder)->getId();
        $this->logDebug('Shared folder id: ' . $id);
        return $this->groupSharedExists($id, $shareGroup, 'folder', $shareOwner);
      } catch(\Exception $e) {
        $this->logError('No file id for  ' . $sharedFolder . ' ' . $e->getMessage());
        return false;
      }

    });

    return $result;
  }

  /**Check for existence of the shared folder and create it when not
   * found.
   *
   * @param $sharedFolder The name of the folder.
   *
   * @return bool, @c true on success.
   */
  public function checkSharedFolder($sharedFolder)
  {
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

    $shareGroup = $this->getAppValue('usergroup');
    $groupAdmin = $this->userId();
    $shareOwner = $this->getConfigValue('shareowner');

    if (!$this->isSubAdminOfGroup()) {
      $this->logError("Permission denied: ".$groupAdmin." is not a group admin of ".$shareGroup.".");
      return false;
    }

    // try to create the folder and share it with the group
    $result = $this->sudo($shareOwner, function() use ($sharedFolder, $shareGroup, $groupAdmin, $shareOwner) {
      $userId    = $this->userId();
      $user      = $this->user();

      $rootView = $this->rootFolder->getUserFolder($shareOwner);

      if ($rootView->nodeExists($sharedFolder)
          && (($node = $rootView->get($sharedFolder))->getType() != FileInfo::TYPE_FOLDER
              || !$node->isShareable())
          && !$node->delete()) {
        return false;
      }


      //->get($sharedFolder)->getId();
      if (!$rootView->nodeExists($sharedFolder) && !$rootView->newFolder($sharedFolder)) {
        return false;
      }

      if (!$rootView->nodeExists($sharedFolder)
          || ($node = $rootView->get($sharedFolder))->getType() != FileInfo::TYPE_FOLDER) {
        throw new \Exception($this->l->t('Folder \`%s\' could not be created', [$sharedFolder]));
        return false;
      }

      // Now it should exist as directory and $node should contain its file-info

      if ($node) {
        $id = $node->getId();
        $this->logDebug('shared folder id ' . $id);
        if (!$this->groupShareObject($id, $shareGroup, 'folder', $userId)
            || !$this->groupSharedExists($id, $shareGroup, 'folder', $userId)) {
          return false;
        }
      } else {
        $this->logError('No file info for ' . $sharedFolder);
        return false;
      }

      return true; // seems to be ok ...
    });

    return $this->sharedFolderExists($sharedFolder);
  }

  /**Check for existence of the project folder and create it when not
   * found.
   *
   * @param $projectsFolder The name of the folder. The name may
   * be composed of several path components.
   *
   * @return bool, @c true on success.
   */
  public function checkProjectsFolder($projectsFolder)
  {
    $sharedFolder = $this->getConfigValue('sharedfolder');

    if (!$this->sharedFolderExists($sharedFolder)) {
      return false;
    }

    $shareGroup = $this->getAppValue('usergroup');
    $shareOwner = $this->getConfigValue('shareowner');
    $groupAdmin = $this->userId();

    if (!$this->isSubAdminOfGroup()) {
      $this->logError("Permission denied: ".$groupAdmin." is not a group admin of ".$shareGroup.".");
      return false;
    }

    /* Ok, then there should be a folder /$sharedFolder */

    $rootView = $this->rootFolder->getUserFolder($groupAdmin);

    $projectsFolder = trim(preg_replace('|[/]+|', '/', $projectsFolder), "/");
    $projectsFolder = Util::explode('/', $projectsFolder);

    $path = '/'.$sharedFolder;

    //trigger_error("Path: ".print_r($projectsFolder, true), E_USER_NOTICE);

    foreach ($projectsFolder as $pathComponent) {
      $path .= '/'.$pathComponent;
      //trigger_error("Path: ".$path, E_USER_NOTICE);
      try {
        $node = $rootView->get($path);
      } catch(\Exception $e) {
        $node = null;
      }
      if (empty($node)
          || $node->getType() != FileInfo::TYPE_FOLDER
          || !$node->isShareable()) {
        if ($node && !$node->delete()) {
          $this->logError('Could not delete non-folder node ' . $path . ' type ' . $node->getType());
          return false;
        }
        try {
          $node = $rootView->newFolder($path);
        } catch(\Exception $e) {
          $this->logError('Could not create ' . $path . ' ' . $e->getMessage() . ' ' . $e->getTraceAsString());
          return false;
        }
        if ($node->getType() != FileInfo::TYPE_FOLDER) {
          $this->logError($path . ' is not a folder!');
          return false;
        }
      }
    }

    return true;
  }

  /**Check for existence of the given calendar. Create one if it could
   * not be found. Make sure it is shared between the orchestra group.
   *
   * @param $uri The local URI of the calendar.
   *
   * @param $displayName The display-name of the calendar.
   *
   * @param $id The id of the calendar.
   *
   * @return int -1 on error, calendar id on success.
   */
  public function checkSharedCalendar($uri, $displayName = null, $id = null)
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

    $result = $this->sudo($shareOwnerId, function()
      use ($uri, $id, $displayName, $shareOwnerId, $userGroupId)
      {
        $this->logError("Sudo to " . $this->userId());

        // get or create the calendar
        if (!empty($id) && $id > 0) {
          $calendar = $this->calDavService->calendarById($id);
        } else {
          $calendar = $this->calDavService->calendarByName($displayName);
        }

        if (empty($calendar)) {
          $this->logError("Calendar " . $displayName . " does not seem to exist.");
          $id = $this->calDavService->createCalendar($uri, $displayName, $shareOwnerId);
          if ($id < 0) {
            $this->logError("Unabled to create calendar " . $displayName);
            return -1;
          }
          $this->logError("Created calendar " . $displayName . " with id " . $id);
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

        // make sure it is shared with the group
        if (!$this->calDavService->groupShareCalendar($id, $userGroupId)) {
          $this->logError("Unable to shared " . $uri . " with " . $userGroupId);
          if ($created) {
            $this->calDavService->deleteCalendar($id);
          }
          return -1;
        }

        // check the display name
        if ($calendar->getDisplayName() != $displayName) {
          $this->logError("Changing name of " . $id . " from " . $calendar->getDisplayName() . " to " . $displayName);
          $this->calDavService->displayName($id, $displayName);
        }

        return $id;
      });

    $this->logError("returning " . $result);

    return $result;
  }

  /**Check for existence of the given addressBook. Create one if it could
   * not be found. Make sure it is shared between the orchestra group.
   *
   * @param $uri The local URI of the addressBook.
   *
   * @param $displayName The display-name of the addressBook.
   *
   * @param $idd The id of the addressBook.
   *
   * @return int -1 on error, addressBook id on success.
   */
  public function checkSharedAddressBook($uri, $displayName = null, $id = null)
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

    return $this->sudo($shareOwnerId, function()
      use ($uri, $displayName, $id, $shareOwnerId, $userGroupId)
      {
        $this->logError("Sudo to " . $this->userId());

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

        // make sure it is shared with the group
        if (!$this->cardDavService->groupShareAddressBook($id, $userGroupId)) {
          $this->logError("Unable to shared " . $uri . " with " . $userGroupId);
          if ($created) {
            $this->cardDavService->deleteAddressBook($id);
          }
          return -1;
        }

        // check the display name
        if ($addressBook->getDisplayName() != $displayName) {
          $this->logError("Changing name of " . $id . " from " . $addressBook->getDisplayName() . " to " . $displayName);
          $this->cardDavService->displayName($id, $displayName);
        }

        return $id;
      });

    return -1;
  }

  /**Check whether we have data-base access by connecting to the
   * data-base server and selecting the configured data-base.
   *
   * @para, $connectionParams Array with keys 'dbname', 'user',
   * 'password', 'host' or null for default options.
   *
   * @return bool, @c true on success.
   *
   * @todo Initial database structure and migrations
   */
  public function databaseAccessible()
  {
    $connection = null;

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

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

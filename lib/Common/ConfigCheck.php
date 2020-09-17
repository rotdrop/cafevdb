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

/**CamerataDB namespace to prevent name-collisions.
 */
namespace OCA\CAFEVDB\Common;

use OCP\IUserManager;
use OCP\IGroupManager;

/**Check for a usable configuration.
 */
class ConfigCheck
{
  /** @var IGroupManager */
  private $groupManager;

  /** @var IUserManager */
  private $userManager;

  public function __construct(IUserManager $userManager, IGroupManager $groupManager) {
    $this->userManager = $userManager;
    $this->groupManager = $groupManager;
  }

  /**Return an array with necessary configuration items, being either
   * true or false, depending the checks performed. The summary
   * component is just the logic and of all other items.
   *
   * @return bool
   * array('summary','orchestra','usergroup','shareowner','sharedfolder','database','encryptionkey')
   *
   * where summary is a bool and everything else is
   * array('status','message') where 'message' should be empty if
   * status is true.
   */
  public function configured()
  {
    $result = array();

    foreach (array('orchestra','usergroup','shareowner','sharedfolder','database','encryptionkey') as $key) {
      $result[$key] = array('status' => false, 'message' => '');
    }

    $key ='orchestra';
    try {
      $result[$key]['status'] = Config::encryptionKeyValid() && Config::getValue('orchestra');
    } catch (\Exception $e) {
      $result[$key]['message'] = $e->getMessage();
    }

    $key = 'encryptionkey';
    try {
      $result[$key]['status'] = $result['orchestra']['status'] && Config::encryptionKeyValid();
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
   *to be executed under the uid of the user the object belongs
   *to. See ConfigCheck::sudo().
   *
   * @param[in] $id The @b numeric id of the object (not the name).
   *
   * @param[in] $group The group to share the item with.
   *
   * @param[in] $type The type of the item, for exmaple calendar,
   * event, folder, file etc.
   *
   * @return @c true for success, @c false on error.
   */
  public function groupSharedExists($id, $group, $type)
  {
    // First check whether the object is already shared.
    $shareType  = \OCP\Share::SHARE_TYPE_GROUP;
    $groupPerms = (\OCP\PERMISSION_CREATE|
                   \OCP\PERMISSION_READ|
                   \OCP\PERMISSION_UPDATE|
                   \OCP\PERMISSION_DELETE);

    $token =\OCP\Share::getItemShared($type, $id, \OCP\Share::FORMAT_NONE);

    // Note: getItemShared() returns an array with one element, strip
    // the outer array!
    if (is_array($token) && count($token) == 1) {
      $token = array_shift($token);
      return isset($token['permissions']) &&
        ($token['permissions'] & $groupPerms) == $groupPerms;
    } else {
      return false;
    }
  }

  /**Share an object between the members of the specified group. Note:
   * this function has to be executed under the uid of the user the
   * object belongs to. See ConfigCheck::sudo().
   *
   * @param[in] $id The @b numeric id of the object (not the name).
   *
   * @param[in] $group The group to share the item with.
   *
   * @param[in] $type The type of the item, for exmaple calendar,
   * event, folder, file etc.
   *
   * @return @c true for success, @c false on error.
   */
  public function groupShareObject($id, $group, $type = 'calendar')
  {
    $groupPerms = (\OCP\PERMISSION_CREATE|
                   \OCP\PERMISSION_READ|
                   \OCP\PERMISSION_UPDATE|
                   \OCP\PERMISSION_DELETE);

    // First check whether the object is already shared.
    $shareType   = \OCP\Share::SHARE_TYPE_GROUP;
    $token = \OCP\Share::getItemShared($type, $id);
    if ($token !== false && (!is_array($token) || count($token) > 0)) {
      return \OCP\Share::setPermissions($type, $id, $shareType, $group, $groupPerms);
    }
    // Otherwise it should be legal to attempt a new share ...

    // try it ...
    return \OCP\Share::shareItem($type, $id, $shareType, $group, $groupPerms);
  }

  /**Fake execution with other user-id. Note that this function will
   * catch any exception thrown while executing the callback-function
   * and in case an exeption has been called will re-throw the
   * exception.
   *
   * @param[in] $uid The "fake" uid.
   *
   * @param[in] $callback function.
   *
   * @return Whatever the callback-functoni returns.
   *
   */
  public function sudo($uid, $callback)
  {
    \OC_Util::setupFS(); // This must come before trying to sudo

    $olduser = \OC_User::getUserId();
    \OC_User::setUserId($uid);
    try {
      $result = call_user_func($callback);
    } catch (\Exception $exception) {
      \OC_User::setUserId($olduser);

      throw $exception;
    }
    \OC_User::setUserId($olduser);

    return $result;
  }

  /**Return @c true if the share-group is set as application
   * configuration option and exists.
   */
  public function shareGroupExists()
  {
    $groupId = Config::getAppValue('usergroup');

    if (!$this->groupManager->groupExists($groupId)) {
      return false;
    }

    return true;
  }

  /**Return @c true if the share-owner exists and belongs to the
   * orchestra user group (and only to this group).
   *
   * @param[in] $shareowner Optional. If unset, then the uid is
   * fetched from the application configuration options.
   *
   * @return bool, @c true on success.
   */
  public function shareOwnerExists($shareowner = '')
  {
    $sharegroup = Config::getAppValue('usergroup');
    $shareowner === '' && $shareowner = Config::getValue('shareowner');

    if ($shareowner === false) {
      return false;
    }

    if (!\OC_user::isEnabled($shareowner)) {
      return false;
    }

    /* Ok, the user exists and is configured as "share-owner" in our
     * poor orchestra app, now perform additional consistency checks.
     *
     * How paranoid should we be?
     */
    $groups = $this->groupManager->getUserGroups($shareowner);

    // well, the share-owner should in turn only be owned by the
    // group.
    if (count($groups) != 1) {
      return false;
    }

    // The one and only group should be our's.
    if ($groups[0] != $sharegroup) {
      return false;
    }

    // Add more checks as needed ... ;)
    return true;
  }

  /**Make sure the "sharing" user exists, create it when necessary.
   * May throw an exception.
   *
   * @param[in] $shareowner The account holding the shared resources.
   *
   * @return bool, @c true on success.
   */
  public function checkShareOwner($shareowner)
  {
    if (!$sharegroup = Config::getAppValue('usergroup', false)) {
      return false; // need at least this group!
    }

    // Create the user if necessary
    if (!\OC_User::userExists($shareowner) &&
        !\OC_User::createUser($shareowner,
                              \OC_User::generatePassword())) {
      return false;
    }

    // Sutff the user in its appropriate group
    if (!\OC_Group::inGroup($shareowner, $sharegroup) &&
        !\OC_Group::addToGroup($shareowner, $sharegroup)) {
      return false;
    }

    return $this->shareOwnerExists($shareowner);
  }

  /**We require that the share-owner owns a directory shared with the
   * orchestra group. Check whether this folder exists.
   *
   * @param[in] $sharedfolder Optional. If unset, the name is fetched
   * from the application configuration options.
   *
   * @return bool, @c true on success.
   */
  public function sharedFolderExists($sharedfolder = '')
  {
    if (!$this->shareOwnerExists()) {
      return false;
    }

    $sharegroup   = Config::getAppValue('usergroup');
    $shareowner   = Config::getValue('shareowner');
    $groupadmin   = $this->getUserId();

    $sharedfolder == '' && $sharedfolder = Config::getSetting('sharedfolder', '');

    if ($sharedfolder == '') {
      // not configured
      return false;
    }

    //$id = \OC\Files\Cache\Cache::getId($sharedfolder, $vfsroot);
    $result = $this->sudo($shareowner, function() use ($sharedfolder, $sharegroup) {
      $user         = $this->getUserId();
      $vfsroot = '/'.$user.'/files';

      if ($sharedfolder[0] != '/') {
        $sharedfolder = '/'.$sharedfolder;
      }

      \OC\Files\Filesystem::initMountPoints($user);

      $rootView = new \OC\Files\View($vfsroot);
      $info = $rootView->getFileInfo($sharedfolder);

      if ($info) {
        $id = $info['fileid'];
        return ConfigCheck::groupSharedExists($id, $sharegroup, 'folder');
      } else {
        \OCP\Util::write('CAFEVDB', 'No file info for  ' . $sharedfolder, \OCP\Util::ERROR);
        return false;
      }
    });

    return $result;
  }

  /**Check for existence of the shared folder and create it when not
   * found.
   *
   * @param[in] $sharedfolder The name of the folder.
   *
   * @return bool, @c true on success.
   */
  public function checkSharedFolder($sharedfolder)
  {
    if ($sharedfolder == '') {
      return false;
    }

    if ($sharedfolder[0] != '/') {
      $sharedfolder = '/'.$sharedfolder;
    }

    if ($this->sharedFolderExists($sharedfolder)) {
      // no need to create
      return true;
    }

    $sharegroup = Config::getAppValue('usergroup');
    $groupadmin = $this->getUserId();

    if (!\OC_SubAdmin::isSubAdminofGroup($groupadmin, $sharegroup)) {
      \OCP\Util::write(Config::APP_NAME,
                       "Permission denied: ".$groupadmin." is not a group admin of ".$sharegroup.".",
                       \OCP\Util::ERROR);
      return false;
    }

    // try to create the folder and share it with the group
    $result = $this->sudo($shareowner, function() use ($sharedfolder, $sharegroup, $user) {
      $user    = $this->getUserId();
      $vfsroot = '/'.$user.'/files';

      // Create the user data-directory, if necessary
      $user_root = \OC_User::getHome($user);
      $userdirectory = $user_root . '/files';
      if( !is_dir( $userdirectory )) {
        mkdir( $userdirectory, 0770, true );
      }
      if( !is_dir( $userdirectory )) {
        return false;
      }

      \OC\Files\Filesystem::initMountPoints($user);

      $rootView = new \OC\Files\View($vfsroot);

      if ($rootView->file_exists($sharedfolder) &&
          (!$rootView->is_dir($sharedfolder) ||
           !$rootView->isSharable($sharedfolder)) &&
          !$rootView->unlink($sharedfolder)) {
        return false;
      }

      if (!$rootView->file_exists($sharedfolder) &&
          !$rootView->mkdir($sharedfolder)) {
        return false;
      }

      if (!$rootView->file_exists($sharedfolder) ||
          !$rootView->is_dir($sharedfolder)) {
        throw new \Exception('Still does not exist.');
      }

      // Now it should exist as directory. Share it
      // Nice ass-hole stuff. We need the id.

      //\OC\Files\Cache\Cache::scanFile($sharedfolder, $vfsroot);
      //$id = \OC\Files\Cache\Cache::getId($sharedfolder, $vfsroot);
      $info = $rootView->getFileInfo($sharedfolder);
      if ($info) {
        $id = $info['fileid'];
        if (!ConfigCheck::groupShareObject($id, $sharegroup, 'folder') ||
            !ConfigCheck::groupSharedExists($id, $sharegroup, 'folder')) {
          return false;
        }
      } else {
        \OCP\Util::write('CAFEVDB', 'No file info for ' . $sharedfolder, \OCP\Util::ERROR);
        return false;
      }

      return true; // seems to be ok ...
    });

    return $this->sharedFolderExists($sharedfolder);
  }

  /**Check for existence of the project folder and create it when not
   * found.
   *
   * @param[in] $projectsFolder The name of the folder. The name may
   * be composed of several path components.
   *
   * @return bool, @c true on success.
   */
  public function checkProjectsFolder($projectsFolder)
  {
    $sharedFolder = Config::getValue('sharedfolder');

    if (!$this->sharedFolderExists($sharedFolder)) {
      return false;
    }

    $sharegroup = Config::getAppValue('usergroup');
    $shareowner = Config::getValue('shareowner');
    $user       = $this->getUserId();

    if (!\OC_SubAdmin::isSubAdminofGroup($user, $sharegroup)) {
      \OCP\Util::write(Config::APP_NAME,
                       "Permission denied: ".$user." is not a group admin of ".$sharegroup.".",
                       \OCP\Util::ERROR);
      return false;
    }

    /* Ok, then there should be a folder /$sharedFolder */

    $fileView = \OC\Files\Filesystem::getView();

    $projectsFolder = trim(preg_replace('|[/]+|', '/', $projectsFolder), "/");
    $projectsFolder = Util::explode('/', $projectsFolder);

    $path = '/'.$sharedFolder;

    //trigger_error("Path: ".print_r($projectsFolder, true), E_USER_NOTICE);

    foreach ($projectsFolder as $pathComponent) {
      $path .= '/'.$pathComponent;
      //trigger_error("Path: ".$path, E_USER_NOTICE);
      if (!$fileView->is_dir($path)) {
        if ($fileView->file_exists($path)) {
          $fileView->unlink($path);
        }
        $fileView->mkdir($path);
        if (!$fileView->is_dir($path)) {
          return false;
        }
      }
    }

    return true;
  }

  /**Check whether we have data-base access by connecting to the
   * data-base server and selecting the configured data-base.
   *
   * @return bool, @c true on success.
   */
  public function databaseAccessible($opts = array())
  {
    try {
      Config::init();
      if (empty($opts)) {
        $opts = Config::$dbopts;
      }

      $handle = mySQL::connect($opts, false /* don't die */, true);
      if ($handle === false) {
        return false;
      }

      if (Events::configureDatabase($handle) === false) {
        mySQL::close($handle);
        return false;
      }

      mySQL::close($handle);
      return true;

    } catch(\Exception $e) {
      mySQL::close($handle);
      throw $e;
    }

    return false;
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

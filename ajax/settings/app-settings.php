<?php
/**
 * Copyright (c) 2011, Frank Karlitschek <karlitschek@kde.org>
 * Copyright (c) 2012, Florian Hülsmann <fh@cbix.de>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();

use \CAFEVDB\L;
use \CAFEVDB\Config;

// Check if we are a group-admin, otherwise bail out.
$user  = OCP\USER::getUser();
$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
if (!OC_SubAdmin::isGroupAccessible($user, $group)) {
    OC_JSON::error(array("data" => array("message" => "Unsufficient privileges.")));
    return;
}

if (isset($_POST['systemkey']) && isset($_POST['oldkey'])) {

  $oldkey   = $_POST['oldkey'];
  $newkey   = $_POST['systemkey'];

  // Remember the old session-key, needs to be restored in case of error
  $actkey = Config::getEncryptionKey();

  // Make sure the old key ist installed properly
  Config::setEncryptionKey($oldkey);

  // Now fetch the key itself
  $storedkey = Config::getValue('encryptionKey');
  if ($storedkey !== $oldkey) {
      Config::setEncryptionKey($actkey);      
      OC_JSON::error(array("data" => array("message" => "Wrong old key.")));
      return;
  }

  // (re-)load the old config values and decrypt with the old key
  if (!Config::decryptConfigValues() && $oldkey == '') {
      // retry with new key
      Config::setEncryptionKey($newkey);
      if (!Config::decryptConfigValues()) {
          Config::setEncryptionKey($actkey);      
          OC_JSON::error(
            array(
              "data" => array(
                    "message" => "Unable to decrypt old config-values.")));
          return;
      }
  }

  // Store the new key in the session data
  Config::setEncryptionKey($newkey);

  // Re-encode the data-base account information with the new key.
  Config::encryptConfigValues();

  // Compute md5 if key is non-empty
  $md5encdbkey = $newkey != '' ? md5($newkey) : '';

  // Encode the new key with itself ;)
  $encdbkey = Config::encrypt($newkey, $newkey);

  OC_AppConfig::setValue('cafevdb', 'encryptionkey', $encdbkey);
  OC_AppConfig::setValue('cafevdb', 'encryptionkey::MD5', $md5encdbkey);

  OC_JSON::success(array("data" => array( "encryptionkey" => $encdbkey)));
  return;
}

if (isset($_POST['keydistribute'])) {
  $group = OC_AppConfig::getValue('cafevdb', 'usergroup', '');
  $users = OC_Group::usersInGroup($group);
  $error = '';
  foreach ($users as $user) {
    if (!Config::setUserKey($user)) {
      $error .= $user.' ';
    }
  }
  if ($error != '') {
    $error = "Failed for: " . $error;
    OC_JSON::error(array("data" => array( "message" => "$error" )));
  } else {
    OC_JSON::success(array("data" => array( "message" => "Key installed successfully!" )));
  }
  return;
}

if (isset($_POST['dbserver'])) {
  $value = $_POST['dbserver'];
  Config::setValue('dbserver', $value);
  echo "dbserver: $value";
  return;
}

if (isset($_POST['dbname'])) {
  $value = $_POST['dbname'];
  Config::setValue('dbname', $value);
  echo "dbname: $value";
  return;
}

if (isset($_POST['dbuser'])) {
  $value = $_POST['dbuser'];
  Config::setValue('dbuser', $value);
  echo "dbuser: $value";
  return;
}

if (isset($_POST['dbpassword'])) {
    $value = $_POST['dbpassword'];
    Config::setValue('dbpassword', $value);
    // Should we now check whether we really can log in to the db-server?
    OC_JSON::success(array("data" => array( "dbpassword" => $value )));
    return;
}

$calendarkeys = array('sharinguser',
                      'concertscalendar',
                      'rehearsalscalendar',
                      'othercalendar',
                      'managementcalendar');

foreach ($calendarkeys as $key) {
    if (isset($_POST[$key])) {
        $value = $_POST[$key];
        Config::setValue($key, $value);
        OC_JSON::success(array("data" => array( "message" => "$key: $value")));
        return;
    }
}

if (isset($_POST['eventduration'])) {
    $value = $_POST['eventduration'];
    Config::setValue('eventduration', $value);
    // Should we now check whether we really can log in to the db-server?
    OC_JSON::success(array("data" => array( "message" => '('.$value.' '.L::t('minutes').')' )));
    return;
}

if (isset($_POST['passwordgenerate'])) {
  OC_JSON::success(array("data" => array( "message" => \OC_User::generatePassword() )));
  return;
}    

if (isset($_POST['sharingpassword'])) {
  $value = $_POST['sharingpassword'];

  // Change the password of the "share"-holder.
  //
  $sharinguser = Config::getValue('sharinguser');

  if (\OC_User::setPassword($sharinguser, $value)) {
    OC_JSON::success(
      array(
        "data" => array(
          "message" => L::t('Changed password for').' '.$sharinguser )));
    return true;
  } else {
    OC_JSON::error(
      array(
        "data" => array(
          "message" => L::t('Failed changing password for').' '.$sharinguser )));
    return false;
  }
}  

if (isset($_POST['error'])) {
  $value = $_POST['error'];

  OC_JSON::error(
    array(
      "data" => array(
        "message" => L::t($value) )));
  return false;
}  

OC_JSON::error(array("data" => array("message" => L::t("Unhandled request:")." ".print_r($_POST,true))));

return false;

?>


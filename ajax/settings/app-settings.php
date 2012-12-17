<?php
/**
 * Copyright (c) 2011, Frank Karlitschek <karlitschek@kde.org>
 * Copyright (c) 2012, Florian Hülsmann <fh@cbix.de>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

$user  = OCP\USER::getUser();
$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
if (!OC_SubAdmin::isGroupAccessible($user, $group)) {
    OC_JSON::error(array("data" => array("message" => "Unsufficient privileges.")));
    return;
}

if (isset($_POST['CAFEVDBkey']) && isset($_POST['dbkey1'])) {

  $oldkey   = $_POST['dbkey1'];
  $newkey   = $_POST['CAFEVDBkey'];

  // Remember the old session-key, needs to be restored in case of error
  $actkey = CAFEVDB\Config::getEncryptionKey();

  // Make sure the old key ist installed properly
  CAFEVDB\Config::setEncryptionKey($oldkey);

  // Now fetch the key itself
  $storedkey = CAFEVDB\Config::getValue('encryptionKey');
  if ($storedkey !== $oldkey) {
      CAFEVDB\Config::setEncryptionKey($actkey);      
      OC_JSON::error(array("data" => array("message" => "Wrong old key.")));
      return;
  }

  // (re-)load the old config values and decrypt with the old key
  if (!CAFEVDB\Config::decryptConfigValues() && $oldkey == '') {
      // retry with new key
      CAFEVDB\Config::setEncryptionKey($newkey);
      if (!CAFEVDB\Config::decryptConfigValues()) {
          CAFEVDB\Config::setEncryptionKey($actkey);      
          OC_JSON::error(array("data" => array("message" => "Unable to decrypt old config-values.")));
          return;
      }
  }

  // Store the new key in the session data
  CAFEVDB\Config::setEncryptionKey($newkey);

  // Re-encode the data-base account information with the new key.
  CAFEVDB\Config::encryptConfigValues();

  // Compute md5 if key is non-empty
  $md5encdbkey = $newkey != '' ? md5($newkey) : '';

  // Encode the new key with itself ;)
  $encdbkey = CAFEVDB\Config::encrypt($newkey, $newkey);

  OC_AppConfig::setValue('cafevdb', 'encryptionkey', $encdbkey);
  OC_AppConfig::setValue('cafevdb', 'encryptionkey::MD5', $md5encdbkey);

  OC_JSON::success(array("data" => array( "encryptionkey" => $encdbkey)));
  return;
}

if (isset($_POST['CAFEVDBkeydistribute'])) {
  $group = OC_AppConfig::getValue('cafevdb', 'usergroup', '');
  $users = OC_Group::usersInGroup($group);
  $error = '';
  foreach ($users as $user) {
    if (!CAFEVDB\Config::setUserKey($user)) {
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

if (isset($_POST['CAFEVdbserver'])) {
  $value = $_POST['CAFEVdbserver'];
  CAFEVDB\Config::setValue('dbserver', $value);
  echo "dbserver: $value";
  return;
}

if (isset($_POST['CAFEVdbname'])) {
  $value = $_POST['CAFEVdbname'];
  CAFEVDB\Config::setValue('dbname', $value);
  echo "dbname: $value";
  return;
}

if (isset($_POST['CAFEVdbuser'])) {
  $value = $_POST['CAFEVdbuser'];
  CAFEVDB\Config::setValue('dbuser', $value);
  echo "dbuser: $value";
  return;
}

/* if (isset($_POST['CAFEVDBpass']) && isset($_POST['dbpass1'])) { */
/*     $oldpass = $_POST['dbpass1']; */
/*     $storedpass = CAFEVDB\Config::getValue('dbpassword'); */
/*     if ($oldpass != $storedpass) { */
/*         OC_JSON::error(array("data" => array("message" => "Old password is wrong $oldpass $storedpass."))); */
/*         return; */
/*     } */
/*     $value = $_POST['CAFEVDBpass']; */
/*     CAFEVDB\Config::setValue('dbpassword', $value); */
/*     // Should we now check whether we really can log in to the db-server? */
/*     OC_JSON::success(array("data" => array( "dbpassword" => $value ))); */
/*     return; */
/* } */

if (isset($_POST['CAFEVDBpass'])) {
    $value = $_POST['CAFEVDBpass'];
    CAFEVDB\Config::setValue('dbpassword', $value);
    // Should we now check whether we really can log in to the db-server?
    OC_JSON::success(array("data" => array( "dbpassword" => $value )));
    return;
}

echo 'false';

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
use \CAFEVDB\Events;

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

if (isset($_POST['shareowner-saved']))
{
  $user    = @$_POST['shareowner'];
  $force   = @$_POST['shareowner-force'] == 'on';
  $olduser = @$_POST['shareowner-saved'];

  // If there is no old dummy, then just create one.
  $actuser = Config::getSetting('shareowner', '');
  if ($olduser != $actuser) {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Submitted').': "'.$olduser.'" != "'.$actuser.'" ('.L::t('stored').').' )));
    return false;
  }
  
  if ($olduser == '' || $force) {
    if (Events::checkShareOwner($user)) {
      Config::setValue('shareowner', $user);
      OC_JSON::success(
        array("data" => array( "message" => L::t('New share-owner').' '.$user.'.' )));
      return true;
    } else {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure creating account').' '.$user.'.' )));
      return false;
    }
  } else if ($user != $olduser) {
    OC_JSON::error(
      array("data" => array( "message" => $olduser.' != '.$user )));
    return false;
  }

  if (Events::checkShareOwner($user)) {
    Config::setValue('shareowner', $user);
    OC_JSON::success(
      array("data" => array( "message" => L::t('Keeping old share-owner').' '.$user )));
    return true;
  } else {
    OC_JSON::error(
      array("data" => array( "message" => L::t('Failure checking account').' '.$user )));
    return false;
  }
}

$calendarkeys = array('concertscalendar',
                      'rehearsalscalendar',
                      'othercalendar',
                      'managementcalendar');

foreach ($calendarkeys as $key) {
    if (isset($_POST[$key])) {
        $value = $_POST[$key];
        $id = Config::getSetting($key.'id', false);

        try {
          $newId = Events::checkSharedCalendar($value, $id);
        } catch (Exception $exception) {
          OC_JSON::error(
            array(
              "data" => array(
                "message" => L::t("Exception:").$exception->getMessage())));
          return false;
        }

        if ($newId !== false) {
          Config::setValue($key, $value);
          if ($id != $newId) {
            Config::setValue($key.'id', $newId);
          }
          OC_JSON::success(array("data" => array( "message" => "$key: $value")));
        } else {
          OC_JSON::error(
            array(
              "data" => array(
                "message" => L::t("Unable to set:").' '.$key.' -> '.$value)));
          return false;
        }
        return true;
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
  $shareowner = Config::getValue('shareowner');

  if (\OC_User::setPassword($shareowner, $value)) {
    OC_JSON::success(
      array(
        "data" => array(
          "message" => L::t('Changed password for').' '.$shareowner )));
    return true;
  } else {
    OC_JSON::error(
      array(
        "data" => array(
          "message" => L::t('Failed changing password for').' '.$shareowner )));
    return false;
  }
}

/********************************************************************************
 *
 * Mail-server settings.
 *
 *******************************************************************************/

foreach (array('smtp', 'imap') as $proto) {
  if (isset($_POST[$proto.'server'])) {
    $value = $_POST[$proto.'server'];
    Config::setValue($proto.'server', $value);
    OC_JSON::success(
      array("data" => array(
              'message' => L::t('Using "%s" as %s-server.',
                                array($value, strtoupper($proto))))));
    return true;
  }

  if (isset($_POST[$proto.'noauth'])) {
    $value = $_POST[$proto.'noauth'];
    Config::setValue($proto.'noauth', $value);
    if ($value) {
      $msg = L::t('Trying access without login/password.');
    } else {
      $msg = L::t('Using login/password authentication.');
    }
    OC_JSON::success(array("data" => array('message' => $msg)));
    return true;
  }

  if (isset($_POST[$proto.'port'])) {
    $value = $_POST[$proto.'port'];
    Config::setValue($proto.'port', $value);
    OC_JSON::success(
      array(
        "data" => array(
          'message' => L::t('Using '.strtoupper($proto).' on port %d',
                            array($value)))));

    return true;
  }

  if (isset($_POST[$proto.'secure'])) {
    $value = $_POST[$proto.'secure'];
    $stdports = array('smtp' => array('insecure' => 587,
                                      'starttls' => 587,
                                      'ssl' => 465),
                      'imap' => array('insecure' => 143,
                                      'starttls' => 143,
                                      'ssl' => 993));

    switch ($value) {
    case 'insecure':
    case 'starttls':
    case 'ssl':
      break;
    default:
      OC_JSON::error(
        array(
          "data" => array(
            "message" => L::t('Unknown transport security method:').' '.$value)));
      return false;
    }

    $port = $stdports[$proto][$value];
    Config::setValue($proto.'secure', $value);
    Config::setValue($proto.'port', $port);

    OC_JSON::success(
      array(
        "data" => array(
          "message" => L::t('Using "%s" for message transport.', array($value)),
          "proto" => $proto,
          "port" => $port)));

    return true;
  }
}

if (isset($_POST['emailuser'])) {
  $value = $_POST['emailuser'];
  Config::setValue('emailuser', $value);
  Config::setValue('smtpnoauth', false);
  Config::setValue('imapnoauth', false);
  // Should we now check whether we really can log in to the db-server?
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Using "%s" as login.', array($value)))));
  return true;
}

if (isset($_POST['emailpassword'])) {
  $value = $_POST['emailpassword'];
  Config::setValue('emailpassword', $value);
  Config::setValue('smtpnoauth', false);
  Config::setValue('imapnoauth', false);
  // Should we now check whether we really can log in to the db-server?
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Password has been changed.'))));
  return true;
}

if (isset($_POST['emailtest'])) {
  $value = $_POST['emailtest'];
  OC_JSON::error(
    array("data" => array(
            'message' => L::t('Not yet implemented.'))));
  return true;
}

if (isset($_POST['error'])) {
  $value = $_POST['error'];

  OC_JSON::error(
    array(
      "data" => array(
        "message" => L::t($value) )));
  return false;
}

OC_JSON::error(
  array("data" => array(
          "message" => L::t("Unhandled request:")." ".print_r($_POST, true))));

return false;

?>


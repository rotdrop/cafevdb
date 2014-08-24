<?php
/**
 * Copyright (c) 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 *
 * BUG: this file is just too long.
 */

\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();

use \CAFEVDB\L;
use \CAFEVDB\Config;
use \CAFEVDB\ConfigCheck;
use \CAFEVDB\Events;
use \CAFEVDB\Finance;

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
  $storedkey = Config::getValue('encryptionkey');
  if ($storedkey !== $oldkey) {
      Config::setEncryptionKey($actkey);
      OC_JSON::error(array("data" => array("message" => L::t("Wrong old key."))));
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
  $md5encdbkey = Config::encrypt($md5encdbkey, $newkey);

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
    $error = L::t("Failed for: %s", array($error));;
    OC_JSON::error(array("data" => array( "message" => "$error" )));
  } else {
    OC_JSON::success(
      array("data" => array( "message" => L::t("Key installed successfully!"))));
  }
  return;
}

if (isset($_POST['orchestra'])) {
  $value = $_POST['orchestra'];
  Config::setValue('orchestra', $value);
  OC_JSON::success(
    array("data" => array(
            "value" => $value,
            "message" => L::t('Name of orchestra set to `%s\'', $value))));  
  return true;
}

if (isset($_POST['dbserver'])) {
  $value = $_POST['dbserver'];
  Config::setValue('dbserver', $value);
  OC_JSON::success(
    array("data" => array(
            "value" => $value,
            "message" => L::t('DB-server set to `%s\'', $value))));  
  return true;
}

if (isset($_POST['dbname'])) {
  $value = $_POST['dbname'];
  Config::setValue('dbname', $value);
  OC_JSON::success(
    array("data" => array(
            "value" => $value,
            "message" => L::t('DB-name set to `%s\'', $value))));  
  return true;
}

if (isset($_POST['dbuser'])) {
  $value = $_POST['dbuser'];
  Config::setValue('dbuser', $value);
  OC_JSON::success(
    array("data" => array(
            "value" => $value,
            "message" => L::t('DB-login set to `%s\'', $value))));  
  return true;
}

if (isset($_POST['cafevdb_dbpassword'])) {
  $value = $_POST['cafevdb_dbpassword'];

  Config::init();
    
  $opts = Config::$dbopts;
    
  if ($value != '') {
    $opts['pw'] = $value;
    if (ConfigCheck::databaseAccessible($opts)) {
      Config::setValue('dbpassword', $value);
      OC_JSON::success(
        array("data" => array(
                "message" => L::t('DB-test passed and DB-password set.'))));
      return true;
    } else {
      OC_JSON::error(
        array("data" => array(
                "message" => L::t('DB-test failed. Check the account settings. Check was performed with the new password.'))));
      return false;
    }
  } else {
    // Check with the stored password
    if (ConfigCheck::databaseAccessible($opts)) {
      OC_JSON::success(
        array("data" => array(
                "message" => L::t('DB-test passed with stored password (empty input ignored).'))));
      return true;
    } else {
      OC_JSON::error(
        array("data" => array(
                "message" => L::t('DB-test failed with stored password (empty input ignored).'))));
      return false;
    }
    
  } 
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
              "message" => L::t('Submitted `%s\' != `%s\' (stored)',
                                array($olduser, $actuser)))));
    return false;
  }
  
  if ($olduser == '' || $force) {
    if (ConfigCheck::checkShareOwner($user)) {
      Config::setValue('shareowner', $user);
      OC_JSON::success(
        array("data" => array( "message" => L::t('New share-owner `%s\'',
                                                 array($user)))));      
      return true;
    } else {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure creating account `%s\'',
                                                 array($user)))));
      return false;
    }
  } else if ($user != $olduser) {
    OC_JSON::error(
      array("data" => array( "message" => $olduser.' != '.$user )));
    return false;
  }

  if (ConfigCheck::checkShareOwner($user)) {
    Config::setValue('shareowner', $user);
    OC_JSON::success(
      array("data" => array( "message" => L::t('Keeping old share-owner `%s\'',
                                               array($user)))));
    return true;
  } else {
    OC_JSON::error(
      array("data" => array( "message" => L::t('Failure checking account `%s\'',
                                               array($user)))));
    return false;
  }
}

if (isset($_POST['sharedfolder-saved']))
{
  $folder    = @$_POST['sharedfolder'];
  $force     = @$_POST['sharedfolder-force'] == 'on';
  $oldfolder = @$_POST['sharedfolder-saved'];

  $shareowner = Config::getSetting('shareowner', '');
  if ($shareowner == '' || $group == '') {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Unable to share without share-holder `dummy-user\''))));
    return false;
  }
  
  // If there is no old dummy, then just create one.
  $actfolder = Config::getSetting('sharedfolder', '');
  if ($oldfolder != $actfolder) {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Inconsistency, submitted `%s\' != `%s\' (stored)',
                                array($oldfolder, $actfolder)))));
    return false;
  }
  
  // some of the functions below may throw an exception, catch it

  try {
    if ($oldfolder == '' || $force) {
      if (ConfigCheck::checkSharedFolder($folder)) {

        Config::setValue('sharedfolder', $folder);
        OC_JSON::success(
          array("data" => array( "message" => L::t('New shared folder `%s\'',
                                                   array($folder)))));
        return true;
      } else {
        OC_JSON::error(
          array("data" => array( "message" => L::t('Failure creating folder `%s\'',
                                                   array($folder)))));
        return false;
      }
    } else if ($folder != $oldfolder) {
      OC_JSON::error(
        array("data" => array( "message" => $oldfolder.' != '.$folder )));
      return false;
    }
    
    if (ConfigCheck::checkSharedFolder($folder)) {
      Config::setValue('sharedfolder', $folder);
      OC_JSON::success(
        array("data" => array( "message" => L::t('Keeping old shared folder `%s\'',
                                                 array($folder)))));
      return true;
    } else {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure checking folder `%s\'',
                                                 array($folder)))));
      return false;
    }
  } catch (Exception $e) {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure checking folder `%s\', caught an exception `%s\'',
                                                 array($folder, $e->getMessage())))));
      return false;
  }  

  return false;
}

if (isset($_POST['projectsfoldersaved']))
{
  $folder    = @$_POST['projectsfolder'];
  $force     = @$_POST['projectsfolder-force'] == 'on';
  $oldfolder = @$_POST['projectsfoldersaved'];

  $sharedfolder = Config::getSetting('sharedfolder', '');
  if ($sharedfolder == '') {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Unable to define project-folder without shared parent folder.'),
              "data" => $folder)));
    return false;
  }
  
  // If there is no old dummy, then just create one.
  $actfolder = Config::getSetting('projectsfolder', '');
  if ($oldfolder != $actfolder) {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Inconsistency, submitted `%s\' != `%s\' (stored)',
                                array($oldfolder, $actfolder)).print_r($_POST, true),
              "data" => $folder)));
    return false;
  }

  $absFolder = "/".$sharedfolder."/".$folder;

  // some of the functions below may throw an exception, catch it
  try {
    if ($oldfolder == '' || $force) {
      if (ConfigCheck::checkProjectsFolder($folder)) {

        Config::setValue('projectsfolder', $folder);
        OC_JSON::success(
          array("data" => array( "message" => L::t('New shared folder `%s\'',
                                                   array($absFolder)),
                                 "data" => $folder)));
        return true;
      } else {
        OC_JSON::error(
          array("data" => array( "message" => L::t('Failure creating folder `%s\'',
                                                   array($absFolder)),
                                 "data" => $folder)));
        return false;
      }
    } else if ($folder != $oldfolder) {
      OC_JSON::error(
        array("data" => array( "message" => $oldfolder.' != '.$folder,
                               "data" => $folder)));
      return false;
    }
    
    if (ConfigCheck::checkProjectsFolder($folder)) {
      Config::setValue('projectsfolder', $folder);
      OC_JSON::success(
        array("data" => array( "message" => L::t('Keeping old shared folder `%s\'',
                                                 array($absFolder)),
                               "data" => $folder)));
      return true;
    } else {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure checking folder `%s\'',
                                                 array($absFolder)),
                               "data" => $folder)));
      return false;
    }
  } catch (Exception $e) {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure checking folder `%s\', caught an exception `%s\'',
                                                 array($absFolder, $e->getMessage())),
                               "data" => $folder)));
      return false;
  }  

  return false;
}

if (isset($_POST['projectsbalancefoldersaved']))
{
  $folder    = @$_POST['projectsbalancefolder'];
  $force     = @$_POST['projectsbalancefolder-force'] == 'on';
  $oldfolder = @$_POST['projectsbalancefoldersaved'];

  $sharedfolder = Config::getSetting('sharedfolder', '');
  if ($sharedfolder == '') {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Unable to define project-folder without shared parent folder.'),
              "data" => $folder)));
    return false;
  }

  $projectsFolder = Config::getSetting('projectsfolder', '');
  if ($sharedfolder == '') {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Unable to define the financial balance folder without project folder name.'),
              "data" => $folder)));
    return false;
  }

  $realFolder = $folder.'/'.$projectsFolder;
  $absFolder = "/".$sharedfolder."/".$realFolder;
  
  // If there is no old dummy, then just create one.
  $actfolder = Config::getSetting('projectsbalancefolder', '');
  if ($oldfolder != $actfolder) {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t('Inconsistency, submitted `%s\' != `%s\' (stored)',
                                array($oldfolder, $actfolder)).print_r($_POST, true),
              "data" => $folder)));
    return false;
  }

  // some of the functions below may throw an exception, catch it

  try {
    if ($oldfolder == '' || $force) {
      if (ConfigCheck::checkProjectsFolder($realFolder)) {

        Config::setValue('projectsbalancefolder', $folder);
        OC_JSON::success(
          array("data" => array( "message" => L::t('New shared folder `%s\'',
                                                   array($absFolder)),
                                 "data" => $folder)));
        return true;
      } else {
        OC_JSON::error(
          array("data" => array( "message" => L::t('Failure creating folder `%s\'',
                                                   array($absFolder)),
                                 "data" => $folder)));
        return false;
      }
    } else if ($folder != $oldfolder) {
      OC_JSON::error(
        array("data" => array( "message" => $oldfolder.' != '.$folder,
                               "data" => $folder)));
      return false;
    }
    
    if (ConfigCheck::checkProjectsFolder($folder.'/'.$projectsFolder)) {
      Config::setValue('projectsBalanceFolder', $folder);
      OC_JSON::success(
        array("data" => array( "message" => L::t('Keeping old shared folder `%s\'',
                                                 array($absFolder)),
                               "data" => $folder)));
      return true;
    } else {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure checking folder `%s\'',
                                                 array($absFolder)),
                               "data" => $folder)));
      return false;
    }
  } catch (Exception $e) {
      OC_JSON::error(
        array("data" => array( "message" => L::t('Failure checking folder `%s\', caught an exception `%s\'',
                                                 array($absFolder, $e->getMessage())),
                               "data" => $folder)));
      return false;
  }  

  return false;
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

    // Check whether the host at least exists
    $ip = gethostbyname($value);
    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
      Config::setValue($proto.'server', $value);
      OC_JSON::success(
        array("data" => array(
                'message' => L::t('Using `%s\' at %s as %s-server.',
                                  array($value, $ip, strtoupper($proto))))));
    } else {
      OC_JSON::error(
        array(
          "data" => array(
            "message" => L::t('Unable to determine the IP-address for %s.',
                              array($value)))));
    }
    
    return true;
  }

  if (isset($_POST[$proto.'port'])) {
    $value = $_POST[$proto.'port'];

    // Check that the value is numeric and not too large
    if (filter_var($value, FILTER_VALIDATE_INT) !== false &&
        $value > 0 && $value < (1 << 16)) {
      Config::setValue($proto.'port', $value);
      OC_JSON::success(
        array(
          "data" => array(
            'message' => L::t('Using '.strtoupper($proto).' on port %d',
                              array($value)))));
      return true;
    } else {
      OC_JSON::error(
        array(
          "data" => array(
            'message' => L::t('`%s\' doesn\'t seem to be a candidate for an IP-port.',
                              array($value)))));
      return false;
    }
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
          "message" => L::t('Using `%s\' for message transport.', array($value)),
          "proto" => $proto,
          "port" => $port)));

    return true;
  }
}

if (isset($_POST['emailuser'])) {
  $value = $_POST['emailuser'];
  Config::setValue('emailuser', $value);
  // Should we now check whether we really can log in to the db-server?
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Using `%s\' as login.', array($value)))));
  return true;
}

if (isset($_POST['emailpassword'])) {
  $value = $_POST['emailpassword'];
  Config::setValue('emailpassword', $value);
  // Should we now check whether we really can log in to the db-server?
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Password has been changed.'))));
  return true;
}

/* Try to distribute the email credentials to all registered users.
 */
if (isset($_POST['emaildistribute'])) {
  $group         = OC_AppConfig::getValue('cafevdb', 'usergroup', '');
  $users         = OC_Group::usersInGroup($group);
  $emailUser     = Config::getValue('emailuser'); // CAFEVDB encKey
  $emailPassword = Config::getValue('emailpassword'); // CAFEVDB encKey

  $error = '';
  foreach ($users as $ocUser) {
    if (!\OC_RoundCube_App::cryptEmailIdentity($ocUser, $emailUser, $emailPassword)) {
      $error .= $ocUser.' ';
    }
  }

  if ($error != '') {
    $error = L::t("Failed for: %s", array($error));;
    OC_JSON::error(
      array("data" => array( "message" => "$error" )));
  } else {
    OC_JSON::success(
      array("data" => array( "message" => L::t("Email credentials installed successfully!"))));
  }
  return;
}

if (isset($_POST['emailtest'])) {
  $value = $_POST['emailtest'];

  $user     = Config::getValue('emailuser');
  $password = Config::getValue('emailpassword');

  $host     = Config::getValue('imapserver');
  $port     = Config::getValue('imapport');
  $secure   = Config::getValue('imapsecure');

  $imapok = false;
  if (CAFEVDB\Email::checkImapServer($host, $port, $secure, $user, $password)) {
    $imapmsg = L::t('IMAP connection seems functional.');
    $imapok = true;
  } else {
    $imapmsg = L::t('Unable to establish IMAP connection.');
  }

  $host     = Config::getValue('smtpserver');
  $port     = Config::getValue('smtpport');
  $secure   = Config::getValue('smtpsecure');
  
  $smtpok = false;
  if (CAFEVDB\Email::checkSmtpServer($host, $port, $secure, $user, $password)) {
    $smtpmsg = L::t('SMTP connection seems functional.');
    $smtpok = true;
  } else {
    $smtpmsg = L::t('Unable to establish SMTP connection.');
  }
  
  $result = array("data" => array('message' => $imapmsg.' '.$smtpmsg));

  if ($smtpok && $imapok) {
    OC_JSON::success($result);
    return true;
  } else {
    OC_JSON::error($result);
    return false;
  }
}

if (isset($_POST['emailtestmode'])) {
  $value = $_POST['emailtestmode'];
  Config::setValue('emailtestmode', $value);
  $addr = Config::getSetting('emailtestaddress', L::t('UNSPECIFIED'));
  if ($value != 'off') {
    OC_JSON::success(
      array("data" => array(
              'message' => L::t('Email test-mode enabled, sending only to %s', array($addr)))));
  } else {
    OC_JSON::success(
      array("data" => array(
              'message' => L::t('Email test-mode disable, will send to all!!!'))));
  }
  return true;
}

if (isset($_POST['emailtestaddress'])) {
  $value = $_POST['emailtestaddress'];

  if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
    OC_JSON::error(
      array("data" => array(
              'message' => L::t('`%s\' doesn\'t seem to be a valid email-address.',
                                array($value)))));
    return false;
  }

  Config::setValue('emailtestaddress', $value);
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Using `%s\' as email-address for test-mode.',
                              array($value)))));
  return true;
}

if (isset($_POST['emailfromname'])) {
  $value = $_POST['emailfromname'];
  Config::setValue('emailfromname', $value);
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Using `%s\' as name of the sender identity.',
                              array($value)))));
  return true;
}

if (isset($_POST['emailfromaddress'])) {
  $value = $_POST['emailfromaddress'];

  if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
    OC_JSON::error(
      array("data" => array(
              'message' => L::t('`%s\' doesn\'t seem to be a valid email-address.',
                                array($value)))));
    return false;
  }

  Config::setValue('emailfromaddress', $value);
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('Using `%s\' as sender email-address.',
                              array($value)))));
  return true;
}

// Something about the orchestra ...
$streetAddressSettings = array('streetAddressName01',
                               'streetAddressName02',
                               'streetAddressStreet',
                               'streetAddressHouseNumber',
                               'streetAddressCity',
                               'streetAddressZIP',
                               'streetAddressCountry',
                               'memberTable',
                               'executiveBoardTable');

foreach ($streetAddressSettings as $item) {
  if (isset($_POST[$item])) {
    $value = $_POST[$item];
    
    Config::setValue($item, $value);
    OC_JSON::success(
      array("data" => array(
              'message' => L::t('Value for `%s\' set to `%s\'.',
                                array($item, $value)))));
    return true;
  }
}

$bankAccountSettings = array('bankAccountOwner',
                             'bankAccountIBAN',
                             'bankAccountBLZ',
                             'bankAccountBIC',
                             'bankAccountCreditorIdentifier');

foreach ($bankAccountSettings as $item) {
  if (isset($_POST[$item])) {
    $value = $_POST[$item];
    
    // Allow erasing
    if ($value == '') {
      Config::setValue($item, $value);
      OC_JSON::success(
        array("data" => array(
                'message' => L::t('Value for `%s\' set to `%s\'.', array($item, $value)),
                'value' => $value,
                'iban' => Config::getValue('bankAccountIBAN'),
                'blz' => Config::getValue('bankAccountBLZ'),
                'bic' => Config::getValue('bankAccountBIC'))));
      return true;
    }

    switch ($item) {
    case 'bankAccountCreditorIdentifier':
      $value = preg_replace('/\s+/', '', $value); // eliminate space
      if (Finance::testCI($value)) {
        Config::setValue($item, $value);
        OC_JSON::success(
          array("data" => array(
                  'message' => L::t('Value for `%s\' set to `%s\'.', array($item, $value)),
                  'value' => $value,
                  'iban' => Config::getValue('bankAccountIBAN'),
                  'blz' => Config::getValue('bankAccountBLZ'),
                  'bic' => Config::getValue('bankAccountBIC'))));
        return true;
      }
      break;
    case 'bankAccountIBAN':
      $value = preg_replace('/\s+/', '', $value); // eliminate space
      $iban = new \IBAN($value);
      if (!$iban->Verify() && is_numeric($value)) {
        // maybe simlpy the bank account number, if we have a BLZ,
        // then compute the IBAN
        $blz = Config::getValue('bankAccountBLZ');
        $bav = new \malkusch\bav\BAV;
        if ($bav->isValidBank($blz)) {
          $value = Finance::makeIBAN($blz, $value);
        }
      }
      $iban = new \IBAN($value);
      if ($iban->Verify()) {
        $value = $iban->MachineFormat();
        Config::setValue($item, $value);

        // Compute as well the BLZ and the BIC
        $blz = $iban->Bank();
        $bav = new \malkusch\bav\BAV;
        if ($bav->isValidBank($blz)) {
          Config::setValue('bankAccountBLZ', $blz);
          Config::setValue('bankAccountBIC', $bav->getMainAgency($blz)->getBIC());
        }
        
        OC_JSON::success(
          array("data" => array(
                  'message' => L::t('Value for `%s\' set to `%s\'.', array($item, $value)),
                  'value' => $value,
                  'iban' => Config::getValue('bankAccountIBAN'),
                  'blz' => Config::getValue('bankAccountBLZ'),
                  'bic' => Config::getValue('bankAccountBIC'))));

        return true;
      } else {
        $message = L::t("Invalid IBAN: `%s'.", array($value));
        $suggestion = '';
        $suggestions = $iban->MistranscriptionSuggestions();
        $intro = L::t("Perhaps you meant");
        while (count($suggestions) > 0) {
          $alternative = array_shift($suggestions);
          if ($iban->Verify($alternative)) {
            $alternative = $iban->MachineFormat($alternative);
            $alternative = $iban->HumanFormat($alternative);
            $suggestion .= $intro . " `".$alternative."'";
            $into = L::t("or");
          }
        }
        OC_JSON::error(
          array("data" => array('message' => $message,
                                'suggestion' => $suggestion)));
        return false;
      }
    case 'bankAccountBLZ':
      $value = preg_replace('/\s+/', '', $value);
      $bav = new \malkusch\bav\BAV;
      if ($bav->isValidBank($value)) {
        // set also the BIC
        Config::setValue('bankAccountBLZ', $value);
        $agency = $bav->getMainAgency($value);
        $bic = $agency->getBIC();
        if (Finance::validateSWIFT($bic)) {
          Config::setValue('bankAccountBIC', $bic);
        }
        OC_JSON::success(
          array("data" => array(
                  'message' => L::t('Value for `%s\' set to `%s\'.', array($item, $value)),
                  'value' => $value,
                  'iban' => Config::getValue('bankAccountIBAN'),
                  'blz' => Config::getValue('bankAccountBLZ'),
                  'bic' => Config::getValue('bankAccountBIC'))));
        return true;
      }
      break;
    case 'bankAccountBIC':
      $value = preg_replace('/\s+/', '', $value);
      if (!Finance::validateSWIFT($value)) {
        // maybe a BLZ
        $bav = new \malkusch\bav\BAV;
        if ($bav->isValidBank($value)) {
          Config::setValue('bankAccountBLZ', $value);
          $agency = $bav->getMainAgency($value);
          $value = $agency->getBIC();
          // Set also the BLZ
        }
      }
      if (Finance::validateSWIFT($value)) {
        Config::setValue($item, $value);
        OC_JSON::success(
          array("data" => array(
                  'message' => L::t('Value for `%s\' set to `%s\'.', array($item, $value)),
                  'value' => $value,
                  'iban' => Config::getValue('bankAccountIBAN'),
                  'blz' => Config::getValue('bankAccountBLZ'),
                  'bic' => Config::getValue('bankAccountBIC'))));
        return true;
      }
      break; // error
    default:
      Config::setValue($item, $value);
      OC_JSON::success(
        array("data" => array(
                'message' => L::t('Value for `%s\' set to `%s\'.', array($item, $value)),
                'value' => $value,
                'iban' => Config::getValue('bankAccountIBAN'),
                'blz' => Config::getValue('bankAccountBLZ'),
                'bic' => Config::getValue('bankAccountBIC'))));
      return true;
    }

    // Default is error
    OC_JSON::error(
      array("data" => array(
              'message' => L::t('Value for `%s\' invalid: `%s\'.',
                                array($item, $value)),
              'suggestion' => '')));
    return false;
  }
}

$devlinks = array('phpmyadmin', 'phpmyadminoc', 'sourcecode', 'sourcedocs', 'ownclouddev');

foreach ($devlinks as $link) {

  if (isset($_POST[$link])) {
    $value = $_POST[$link];

    $value = $value;
    Config::setValue($link, $value);    
    OC_JSON::success(
      array("data" => array(
              'message' => L::t('Link for `%s\' set to `%s\'.',
                                array($link, $value)))));
    return true;
  }

  if (isset($_POST['test'.$link])) {
    $value = $_POST['test'.$link];

    $target = Config::getSetting($link, false);
    if ($target === false) {
      OC_JSON::error(
        array(
          "data" => array(
            "message" => L::t('Unable to test link for `%s\' without a link target.',
                              array($link)))));
      return false;
    } else {
      OC_JSON::success(
        array("data" => array(
                'message' => L::t('New window or tab with `%s\'?',
                                  array($target)),
                'link' => $link.'@'.Config::APP_NAME,
                'target' => $target)));
      return true;
    }
    return true;
  }
  
}

if (isset($_POST['error'])) {
  $value = $_POST['error'];

  OC_JSON::error(
    array(
      "data" => array(
        "message" => $value )));
  return false;
}

OC_JSON::error(
  array("data" => array(
          "message" => L::t("Unhandled request:")." ".print_r($_POST, true))));

return false;

?>


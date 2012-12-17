<?php

// Init owncloud

$l=OC_L10N::get('cafevdb');

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if (isset($_POST['encryptionkey']) && isset($_POST['password'])) {
  $user          = OCP\USER::getUser();
  $password      = $_POST['password'];
  $encryptionkey = $_POST['encryptionkey'];

  // Re-validate the user
  if (\OC_User::checkPassword($user, $password) !== $user) {
    OCP\JSON::error(array('data' => array('message' => $l->t('Invalid password for ')."$user")));
    return false;
  }

  // Then check whether the key is correct
  if (!CAFEVDB\Config::encryptionKeyValid($encryptionkey)) {
    OCP\JSON::error(array('data' => array('message' => $l->t('Invalid encryption key.'))));
    return false;
  }

  // So generate a new key-pair and store the key.
  CAFEVDB\Config::recryptEncryptionKey($user, $password, $encryptionkey);

  // Then store the key in the session as it is the valid key
  CAFEVDB\Config::setEncryptionKey($encryptionkey);

  OCP\JSON::success(array('data' => array('message' => $l->t('Encryption key stored.'))));

  return true;
}

?>

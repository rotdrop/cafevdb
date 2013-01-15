<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if (isset($_POST['encryptionkey']) && isset($_POST['password'])) {
  $user          = OCP\USER::getUser();
  $password      = $_POST['password'];
  $encryptionkey = $_POST['encryptionkey'];

  try {
    // Re-validate the user
    if (\OC_User::checkPassword($user, $password) !== $user) {
      OCP\JSON::error(array('data' => array('message' => L::t('Invalid password for `%s\'',
                                                              array($user)))));
      return false;
    }

    // Then check whether the key is correct
    if (!CAFEVDB\Config::encryptionKeyValid($encryptionkey)) {
      OCP\JSON::error(array('data' => array('message' => L::t('Invalid encryption key.'))));
      return false;
    }

    // So generate a new key-pair and store the key.
    CAFEVDB\Config::recryptEncryptionKey($user, $password, $encryptionkey);

    // Then store the key in the session as it is the valid key
    CAFEVDB\Config::setEncryptionKey($encryptionkey);

  } catch (\Exception $e) {
    OC_JSON::error(
      array(
        "data" => array(
          "message" => L::t("Exception:").$exception->getMessage())));
    return false;
  }

  OCP\JSON::success(array('data' => array('message' => L::t('Encryption key stored.'))));

  return true;
}

OCP\JSON::error(array('data' => array('message' => L::t('Unknown request: `%s\'',
                                                        array(print_r($_POST,true))))));

return false;

?>

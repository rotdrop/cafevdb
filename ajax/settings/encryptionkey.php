<?php

// Init owncloud

$l=OC_L10N::get('cafevdb');

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['encryptionkey'] ) ) {
  $encryptionkey=$_POST['encryptionkey'];
  OCP\Config::setUserValue( OCP\USER::getUser(), 'cafevdb', 'encryptionkey', $encryptionkey );
  OCP\JSON::success(array('data' => array( 'message' => $l->t('Encryption key changed') )));
  return true;
} else {
  OCP\JSON::error(array('data' => array( 'message' => $l->t('Invalid request') )));
  return false;
}

?>

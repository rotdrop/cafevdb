<?php

// Init owncloud

$l=OC_L10N::get('cafevdb');

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['expertmode2'] ) ) {
  $expertmode2=$_POST['expertmode2'];
} else {
  $expertmode2='off';
}
OCP\Config::setUserValue( OCP\USER::getUser(), 'cafevdb', 'expertmode2', $expertmode2 );
OCP\JSON::success(array('data' => array( 'message' => $l->t('Expertmode2 changed') )));
return true;

?>

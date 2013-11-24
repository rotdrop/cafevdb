<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['headervisibility'] ) ) {
  $headervisibility = $_POST['visibility'] == 'on' ? 'expanded' : 'collapsed';
} else {
  $headervisibility = 'collapsed';
}
OCP\Config::setUserValue( OCP\USER::getUser(), 'cafevdb', 'headervisibility', $headervisibility );
OCP\JSON::success(array('data' => array( 'message' => L::t('header-visibility changed') )));
return true;

?>

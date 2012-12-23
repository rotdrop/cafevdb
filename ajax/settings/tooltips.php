<?php

// Init owncloud

use CAFEVDB\L;

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

// Get data
if( isset( $_POST['tooltips'] ) ) {
  $tooltips=$_POST['tooltips'];
} else {
  $tooltips='off';
}
OCP\Config::setUserValue( OCP\USER::getUser(), 'cafevdb', 'tooltips', $tooltips );
OCP\JSON::success(array('data' => array( 'message' => L::t('Tooltips changed') )));
return true;

?>

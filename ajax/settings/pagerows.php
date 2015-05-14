<?php

namespace CAFEVDB 
{
  
  // Check if we are a user
  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  $key = 'pagerows';
  $value = Util::cgiValue($key, 20);
  
  Config::setUserValue($key, $value);
  \OCP\JSON::success(
      array('data' => array('message' => L::t("Setting `%s' to `%s'", array($key, $value)),
                            'value' => $value)));
  return true;
}
  
?>

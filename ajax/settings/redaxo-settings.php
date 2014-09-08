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

// Check if we are a group-admin, otherwise bail out.
$user  = OCP\USER::getUser();
$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
if (!OC_SubAdmin::isGroupAccessible($user, $group)) {
    OC_JSON::error(array("data" => array("message" => "Unsufficient privileges.")));
    return;
}

$redaxoKeys = array('Preview', 'Archive', 'Trashbin');
if (false) {
  L::t('Preview');
  L::t('Archive');
  L::t('Trashbin');  
}

foreach ($redaxoKeys as $key) {
  $cfgKey = 'redaxo'.$key;
  if (isset($_POST[$cfgKey])) {
    $value = $_POST[$cfgKey];

    $value = trim($value);
    $intValue = intval($value);
    if ($intValue > 0 && strval($intValue) == $value) {
      Config::setValue($cfgKey, $value);
      OC_JSON::success(
        array("data" => array(
                "value" => $value,
                "message" => L::t("Redaxo categorie Id for `%s' set to %s",
                                  array($key, $intValue)))));
      return true;
    } else {
      OC_JSON::error(
        array("data" => array(
                "message" => L::t("Category id must be integer and positive, got `%s'.",
                                  array($value)))));
      return false;
    }     
  }
}

OC_JSON::error(
  array("data" => array(
          "message" => L::t("Unhandled request:")." ".print_r($_POST, true))));

return false;

?>
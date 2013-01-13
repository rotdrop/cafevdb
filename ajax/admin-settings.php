<?php
/**
 * Copyright (c) 2011, Frank Karlitschek <karlitschek@kde.org>
 * Copyright (c) 2012, Florian Hülsmann <fh@cbix.de>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

use CAFEVDB\L;

OCP\User::checkAdminUser();
OCP\JSON::callCheck();

if (isset($_POST['CAFEVgroup'])) {
  $value = $_POST['CAFEVgroup'];
  \OC_AppConfig::setValue('cafevdb', 'usergroup', $value);
  
  OC_JSON::success(
    array("data" => array( "message" => L::t('Setting orchestra group to `%s\'. Please login as group administrator and configure the Camerata DB application.',
                                             array($value)))));
  return true;
}

OC_JSON::error(
  array("data" => array( "message" => L::t('Unknown request.'))));

return false;


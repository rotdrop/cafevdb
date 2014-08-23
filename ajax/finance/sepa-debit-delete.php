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
use \CAFEVDB\Util;
use \CAFEVDB\Projects;
use \CAFEVDB\Finance;

$_GET = array(); // disable GET

$requiredKeys = array('ProjectId', 'MusicianId', 'mandateReference');
foreach ($requiredKeys as $required) {
  if (!Util::cgiValue($required, null, false)) {
    OC_JSON::error(
      array("data" => array(
              "message" => L::t("Required information `%s' not provided.", array($required)).print_r($_POST, true))));
    return false;
  }
}

$musicianId = Util::cgiValue('MusicianId');
$projectId = Util::cgiValue('ProjectId');

if (Finance::deleteSepaMandate($projectId, $musicianId)) {
  OC_JSON::success(
    array("data" => array(
            'message' => L::t('SEPA debit mandate deleted from data-base.'))));
  return true;
} else {
  OC_JSON::error(
    array("data" => array(
            'message' => L::t('Unable to delete SEPA debit mandate from data-base.'))));
  return false;
}

?>
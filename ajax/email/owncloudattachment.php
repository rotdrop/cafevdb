<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**@file
 * @brief Add an attachment from the Owncloud filespace.
 */

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;
use CAFEVDB\Ajax;
use CAFEVDB\EmailComposer;

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled(Config::APP_NAME);
OCP\JSON::callCheck();

if (!isset($_GET['path'])) {
  Ajax::bailOut(L::t('No attachment path was submitted.'));
}

$localpath = $_GET['path'];
$name      = \OC\Files\Filesystem::getLocalFile($localpath);

if (!file_exists($name)) {
  Ajax::bailOut(L::t('File doesn\'t exist: ').$localpath);
}

if (false) {
  Ajax::bailOut(L::t('File doesn\'t exist:').'<br/>'
                .$name.'<br/>'
                .$localpath.'<br/>'
                .\OC\Files\Filesystem::getLocalPath($name).'<br/>'
                .\OC\Files\Filesystem::getLocalPath($localpath).'<br/>'
                .print_r($fileInfo, true));
}

// Should coincide with $localpath
if ($localpath != \OC\Files\Filesystem::getLocalPath($localpath)) {
  Ajax::bailOut(L::t('Inconsistency:').' '.
                $localpath.
                ' <=> '.
                \OC\Files\Filesystem::getLocalPath($localpath));
}

$fileInfo = \OC\Files\Filesystem::getFileInfo($localpath);

// We emulate an uploaded file here:
$fileRecord = array(
  'name' => $localpath,
  'error' => 0,
  'tmp_name' => $name,
  'type' => $fileInfo['mimetype'],
  'size' => $fileInfo['size']);

$composer = new EmailComposer();

$fileRecord = $composer->saveAttachment($fileRecord, false);

if ($fileRecord === false) {
  Ajax::bailOut('Couldn\'t save temporary file for: '.$localpath);
  return false;
} else {
  OCP\JSON::success(
    array(
      'data' => array(
        'type'     => $fileRecord['type'],
        'size'     => $fileRecord['size'],
        'name'     => $fileRecord['name'],
        'error'    => 0,
        'tmp_name' => $fileRecord['tmp_name'])));
  return true;
}

?>

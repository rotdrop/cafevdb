<?php
/**
 * Upload portraits (photos).
 *
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Originally borrowed from:
 *
 * ownCloud - Addressbook
 *
 * @author Thomas Tanghus
 * @copyright 2012 Thomas Tanghus <thomas@tanghus.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use CAFEVDB\L;
use CAFEVDB\Ajax;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;


// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');

$recordId  = Util::cgiValue('RecordId', '');
$imageSize = Util::cgiValue('ImageSize', 400);

if ($recordId == '') {
  Ajax::bailOut(L::t('No record ID was submitted.'));
}

$path = Util::cgiValue('path', '');
if ($path == '') {
  Ajax::bailOut(L::t('No image path was submitted.'));
}

$user = \OC_User::getUser();
$view = new \OC\Files\View('/'.$user.'/files');
$fileInfo = $view->getFileInfo($path);
if($fileInfo['encrypted'] === true) {
  $fileName = $view->toTmpFile($path);
} else {
  $fileName = $view->getLocalFile($path);
}

$tmpkey = 'cafevdb-inline-image-'.md5($fileName);

if (!file_exists($fileName)) {
  Ajax::bailOut(L::t('File doesn\'t exist:').$fileName);
}

$image = new OC_Image();
if (!$image) {
  Ajax::bailOut(L::t('Error loading image.'));
}
if (!$image->loadFromFile($fileName)) {
  Ajax::bailOut(L::t('Error loading image.'));
}
if ($image->width() > $imageSize || $image->height() > $imageSize) {
  $image->resize($imageSize); // Prettier resizing than with browser and saves bandwidth.
}
if (!$image->fixOrientation()) { // No fatal error so we don't bail out.
  OCP\Util::writeLog('cafevdb',
                     'ajax/inlinepicture/oc_photo.php: Couldn\'t save correct image orientation: '.$fileName,
                     OCP\Util::DEBUG);
}
if (\OC\Cache::set($tmpkey, $image->data(), 600)) {
  OCP\JSON::success(array('data' => array('recordId'=>$recordId, 'tmp'=>$tmpkey)));
  exit();
} else {
  Ajax::bailOut(L::t('Couldn\'t save temporary image: %s', $tmpkey));
}

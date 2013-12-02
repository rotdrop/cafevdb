<?php
/**
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

// Firefox and Konqueror tries to download application/json for me.  --Arthur
OCP\JSON::setContentTypeHeader('text/plain');
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');

Config::init();

$recordId = Util::cgiValue('RecordId', '');
$imageClass = Util::cgiValue('ImagePHPClass', 'CAFEVDB\Musicians');

if ($recordId == '') {
  Ajax::bailOut(L::t('No record ID was submitted.'));
}

$photo = call_user_func(array($imageClass, 'fetchImage'), $recordId);
if (!$photo || $photo ==  '') {
  Ajax::bailOut(L::t('Error reading inline image for ID = %s.', array($recordId)));
} else {
  $image = new OC_Image();
  $image->loadFromBase64($photo);
  if ($image->valid()) {
    $tmpkey = 'cafevdb-inline-image-'.$recordId;
    if (OC_Cache::set($tmpkey, $image->data(), 600)) {
      OCP\JSON::success(array('data' => array('recordId'=>$recordId, 'tmp'=>$tmpkey)));
      exit();
    } else {
      Ajax::bailOut(L::t('Error saving temporary file.'));
    }
  } else {
    Ajax::bailOut(L::t('The loading image is not valid.'));
  }
}

?>

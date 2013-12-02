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

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::callCheck();

Config::init();

// Firefox and Konqueror tries to download application/json for me.  --Arthur
OCP\JSON::setContentTypeHeader('text/plain; charset=utf-8');

$image = null;

$x1 = Util::cgiValue('x1', 0, false);
//$x2 = Util::CgiValue('x2', -1, false);
$y1 = Util::cgiValue('y1', 0, false);
//$y2 = Util::cgiValue('y2', -1, false);
$w = Util::cgiValue('w', -1, false);
$h = Util::cgiValue('h', -1, false);
$tmpkey = Util::cgiValue('tmpkey', '');
$recordId = Util::cgiValue('RecordId', '');
$imageClass = Util::cgiValue('ImagePHPClass', 'CAFEVDB\Musicians');

if ($tmpkey == '') {
  Ajax::bailOut('Missing key to temporary file.');
}

if ($recordId == '') {
  Ajax::bailOut(L::t('While trying to save cropped photo: missing record id.').print_r($_POST, true));
}

OCP\Util::writeLog('cafevdb', 'savecrop.php: key: '.$tmpkey, OCP\Util::DEBUG);

$data = OC_Cache::get($tmpkey);
if ($data) {
  $image = new OC_Image();
  if ($image->loadFromdata($data)) {
    $w = ($w != -1 ? $w : $image->width());
    $h = ($h != -1 ? $h : $image->height());
    OCP\Util::writeLog('cafevdb',
                       'savecrop.php, x: '.$x1.' y: '.$y1.' w: '.$w.' h: '.$h,
                       OCP\Util::DEBUG);
    if ($image->crop($x1, $y1, $w, $h)) {
      if (($image->width() <= 200 && $image->height() <= 200)
         || $image->resize(200)) {
        if (!call_user_func(array($imageClass, 'storeImage'), $recordId, $image->__toString())) {
          Ajax::bailOut(L::t('Error saving image in DB'));
        }
        OCP\JSON::success(array(
                            'data' => array(
                              'recordId' => $recordId,
                              'width' => $image->width(),
                              'height' => $image->height()
                              )
                            ));
      } else {
        Ajax::bailOut(L::t('Error resizing image'));
      }
    } else {
      Ajax::bailOut(L::t('Error cropping image'));
    }
  } else {
    Ajax::bailOut(L::t('Error creating temporary image'));
  }
} else {
  Ajax::bailOut(L::t('Error finding image: %s', $tmpkey));
}

OC_Cache::remove($tmpkey);

?>


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
use CAFEVDB\Musicians;

// Firefox and Konqueror tries to download application/json for me.  --Arthur
OCP\JSON::setContentTypeHeader('text/plain');
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');

Config::init();

$memberId = Util::cgiValue('MemberId', '');
if ($memberId == '') {
  Ajax::bailOut(L::t('No member ID was submitted.'));
}

$photo = Musicians::fetchPortrait($memberId);
if (!$photo || $photo ==  '') {
  Ajax::bailOut(OC_Contacts_App::$l10n->t('Error reading member photo for ID = %s.', $memberId));
} else {
  $image = new OC_Image();
  $image->loadFromBase64($photo);
  if ($image->valid()) {
    $tmpkey = 'cafevdb-member-photo-'.$memberId;
    if (OC_Cache::set($tmpkey, $image->data(), 600)) {
      OCP\JSON::success(array('data' => array('memberId'=>$memberId, 'tmp'=>$tmpkey)));
      exit();
    } else {
      Ajax::bailOut(L::t('Error saving temporary file.'));
    }
  } else {
    Ajax::bailOut(L::t('The loading photo is not valid.'));
  }
}

?>

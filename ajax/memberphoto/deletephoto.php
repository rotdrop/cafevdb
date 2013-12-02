<?php
/**
 * Upload portraits (photos).
 *
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

$recordId = Util::cgiValue('RecordId', '');
$pictureClass = Util::cgiValue('PicturePHPClass', 'CAFEVDB\Musicians');

if ($recordId == '') {
  Ajax::bailOut(L::t('No member ID was submitted.'));
}

if (!call_user_func(array($pictureClass, 'deletePicture'), $recordId)) {
  Ajax::bailOut(L::t('Deleting the photo may have failed.'));
}

$tmpkey = 'cafevdb-member-photo-'.$recordId;
OC_Cache::remove($tmpkey);

OCP\JSON::success(array('data' => array('recordId'=>$recordId)));

?>

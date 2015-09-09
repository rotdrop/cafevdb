<?php
/**
 * Upload portraits (photos).
 *
 * @copyright 2013-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB {

  // Check if we are a user
  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');

  $itemId = Util::cgiValue('ItemId', '');
  $itemTable = Util::cgiValue('ImageItemTable', '');

  if ($itemId == '') {
    Ajax::bailOut(L::t('No record ID was submitted.'));
  }

  $inlineImage = new InlineImage($itemTable);

  $imageMetaData = $inlineImage->fetch($itemId, InlineImage::IMAGE_META_DATA);
  
  if (!$imageMetaData || !$inlineImage->delete($itemId)) {
    Ajax::bailOut(L::t('Deleting the photo may have failed.'));
  }

  // in case it exists
  $tmpkey = 'cafevdb-inline-image-'.$itemTable.'-'.$itemId.'-'.$imageMetaData['MD5'];
  FileCache::remove($tmpkey);

  \OCP\JSON::success(array('data' => array('itemId' => $itemId)));

} // namespace

?>

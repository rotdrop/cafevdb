<?php
/**
 * Camerata Musician DB
 *
 * @author Claus-Justus Heine
 * @copyright 2012-2015 Claus-Justus HEine <himself@claus-justus-heine.de>
 *
 * Originally from
 *
 * ownCloud - Addressbook, copyright 2012 Thomas Tanghus <thomas@tanghus.net>
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

namespace CAFEVDB 
{

  // Firefox and Konqueror tries to download application/json for me.  --Arthur
  \OCP\JSON::setContentTypeHeader('text/plain');
  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');

  Config::init();

  $itemId = Util::cgiValue('ItemId', '');
  $itemTable = Util::cgiValue('ImageItemTable', '');
  $imageSize = Util::cgiValue('ImageSize', 400);

  if ($itemId == '') {
    Ajax::bailOut(L::t('No item ID was submitted.'));
  }

  $inlineImage = new InlineImage($itemTable);
  
  $imageData = $inlineImage->fetch($itemId);
  if (!isset($imageData['Data']) || !$imageData['Data']) {
    Ajax::bailOut(L::t('Error reading inline image for ID = %s.', array($itemId)));
  } else {
    $image = new \OC_Image();
    $image->loadFromBase64($imageData['Data']);
    if ($image->valid()) {
      // generate a unique URL based on MD5-hash of image data in order to disable browser caching.
      $tmpkey = 'cafevdb-inline-image-'.$itemTable.'-'.$itemId.'-'.$imageData['MD5'];
      if (FileCache::set($tmpkey, $image->data(), 600)) {
        \OCP\JSON::success(array('data' => array('itemId' => $itemId,
                                                 'tmp' => $tmpkey)));
        exit();
      } else {
        Ajax::bailOut(L::t('Error saving temporary file.'));
      }
    } else {
      Ajax::bailOut(L::t('The loaded image is not valid.'));
    }
  }

} // namespace

?>

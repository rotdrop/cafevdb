<?php
/**
 * Upload portraits (photos).
 *
 * @copyright 2013-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Originally borrowed from:
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

// Check if we are a user
  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  Config::init();

// Firefox and Konqueror tries to download application/json for me.  --Arthur
  \OCP\JSON::setContentTypeHeader('text/plain; charset=utf-8');

  $recordId = Util::cgiValue('RecordId', false);
  $imageSize = Util::cgiValue('ImageSize', 400);

// If it is a Drag'n'Drop transfer it's handled here.
  $fn = (isset($_SERVER['HTTP_X_FILE_NAME']) ? $_SERVER['HTTP_X_FILE_NAME'] : false);
  if ($fn) {
    if (!Util::cgiValue('RecordId')) {
      Ajax::bailOut(L::t('No record ID was submitted.'));
    }
    $tmpkey = 'cafevdb-inline-image-'.md5($fn);
    $data = file_get_contents('php://input');
    $image = new \OC_Image();
    sleep(1); // Apparently it needs time to load the data.
    if ($image->loadFromData($data)) {
      if($image->width() > $imageSize || $image->height() > $imageSize) {
        $image->resize($imageSize); // Prettier resizing than with browser and saves bandwidth.
      }
      if(!$image->fixOrientation()) { // No fatal error so we don't bail out.
        Ajax::debug('Couldn\'t save correct image orientation: '.$tmpkey);
      }
      if(FileCache::set($tmpkey, $image->data(), 600)) {
        \OCP\JSON::success(array(
                            'data' => array(
                              'mime'=>$_SERVER['CONTENT_TYPE'],
                              'name'=>$fn,
                              'recordId'=>$recordId,
                              'tmp'=>$tmpkey)));
        exit();
      } else {
        Ajax::bailOut(L::t('Couldn\'t save temporary image: %s', $tmpkey));
      }
    } else {
      Ajax::bailOut(L::t('Couldn\'t load temporary image: %s', $tmpkey));
    }
  }

// Uploads from file dialog are handled here.
  if ($recordId === false) {
    Ajax::bailOut(L::t('No record ID was submitted.'));
  }
  if (!isset($_FILES['imagefile'])) {
    Ajax::bailOut(L::t('No file was uploaded. Unknown error'));
  }

  $error = $_FILES['imagefile']['error'];
  if($error !== UPLOAD_ERR_OK) {
    $errors = array(
      0=>L::t("There is no error, the file uploaded with success"),
      1=>L::t("The uploaded file exceeds the upload_max_filesize directive in php.ini").ini_get('upload_max_filesize'),
      2=>L::t("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form"),
      3=>L::t("The uploaded file was only partially uploaded"),
      4=>L::t("No file was uploaded"),
      6=>L::t("Missing a temporary folder")
      );
    Ajax::bailOut($errors[$error]);
  }
  $file = $_FILES['imagefile'];

  if(file_exists($file['tmp_name'])) {
    $tmpkey = 'cafevdb-inline-image-'.md5(basename($file['tmp_name']));
    $image = new \OC_Image();
    if($image->loadFromFile($file['tmp_name'])) {
      if($image->width() > $imageSize || $image->height() > $imageSize) {
        $image->resize($imageSize); // Prettier resizing than with browser and saves bandwidth.
      }
      if(!$image->fixOrientation()) { // No fatal error so we don't bail out.
        Ajax::debug('Couldn\'t save correct image orientation: '.$tmpkey);
      }
      if(FileCache::set($tmpkey, $image->data(), 600)) {
        \OCP\JSON::success(array(
                            'data' => array(
                              'mime'=>$file['type'],
                              'size'=>$file['size'],
                              'name'=>$file['name'],
                              'recordId'=>$recordId,
                              'tmp'=>$tmpkey,
                              )));
        exit();
      } else {
        Ajax::bailOut(L::t('Couldn\'t save temporary image: %s', $tmpkey));
      }
    } else {
      Ajax::bailOut(L::t('Couldn\'t load temporary image: %s', $file['tmp_name']));
    }
  } else {
    Ajax::bailOut(L::t('Temporary file: \'%s\' has gone AWOL?', $file['tmp_name']));
  }

} // namespace

?>

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
 *
 */
/**@file
 * Upload an attachment from the users computer.
 */

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;
use CAFEVDB\Ajax;
use CAFEVDB\EmailComposer;

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled(Config::APP_NAME);
OCP\JSON::callCheck();

//trigger_error(print_r($_POST, true).print_r($_FILES, true), E_USER_NOTICE);

// Firefox and Konqueror tries to download application/json for me.  --Arthur
OCP\JSON::setContentTypeHeader('text/plain; charset=utf-8');

//Ajax::bailOut(L::t('Test'));o

$fileKey = 'files';

if (!isset($_FILES[$fileKey])) {
  Ajax::bailOut(L::t('No file was uploaded. Unknown error'));
}

foreach ($_FILES[$fileKey]['error'] as $error) {
  if ($error != 0) {
    $errors = array(
      UPLOAD_ERR_OK => L::t('There is no error, the file uploaded with success'),
      UPLOAD_ERR_INI_SIZE => L::t('The uploaded file exceeds the upload_max_filesize directive in php.ini: %s', 
                                  array(ini_get('upload_max_filesize'))),
      UPLOAD_ERR_FORM_SIZE => L::t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
      UPLOAD_ERR_PARTIAL => L::t('The uploaded file was only partially uploaded'),
      UPLOAD_ERR_NO_FILE => L::t('No file was uploaded'),
      UPLOAD_ERR_NO_TMP_DIR => L::t('Missing a temporary folder'),
      UPLOAD_ERR_CANT_WRITE => L::t('Failed to write to disk'),
      );
    Ajax::bailOut($errors[$error]);
  }
}
$files = $_FILES[$fileKey];

$upload_max_filesize = OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
$post_max_size = OCP\Util::computerFileSize(ini_get('post_max_size'));
$maxUploadFileSize = min($upload_max_filesize, $post_max_size);

$maxHumanFileSize = OCP\Util::humanFileSize($maxUploadFileSize);

$totalSize = 0;
foreach ($files['size'] as $size) {
	$totalSize += $size;
}
if ($maxUploadFileSize >= 0 and $totalSize > $maxUploadFileSize) {
  OCP\JSON::error(array('data' => array('message' => L::t('Not enough storage available'),
                                        'uploadMaxFilesize' => $maxUploadFileSize,
                                        'maxHumanFilesize' => $maxHumanFileSize)));
  exit();
}

// First re-order the array
$fileCount = count($files['name']);
$fileRecord = array();
$result = array();
for ($i = 0; $i < $fileCount; $i++) {
  foreach($files as $key => $values) {
    $fileRecord[$key] = $values[$i];
  }
  // Move the temporary files to locations where we can find them later.
  $fileRecord = EmailComposer::saveAttachment($fileRecord);

  // Submit the file-record back to the java-script in order to add the
  // data to the form.
  if ($fileRecord === false) {
    Ajax::bailOut('Couldn\'t save temporary file for: '.$files['name'][$i]);
    return false;
  } else {
    $fileRecords['originalname']      = $fileRecord['name']; // clone
    $fileRecords['uploadMaxFilesize'] = $maxUploadFileSize;    
    $fileRecords['maxHumanFilesize']  = $maxHumanFileSize;
    $result[] = array('status' => 'success',
                      'data'   => $fileRecord);
  }
}

OCP\JSON::encodedPrint($result);
exit();

?>

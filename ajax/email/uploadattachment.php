<?php

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;
use CAFEVDB\Ajax;
use CAFEVDB\Email;

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled(Config::APP_NAME);
OCP\JSON::callCheck();

// Firefox and Konqueror tries to download application/json for me.  --Arthur
OCP\JSON::setContentTypeHeader('text/plain; charset=utf-8');

//Ajax::bailOut(L::t('Test'));

$fileKey = 'fileAttach';

if (!isset($_FILES[$fileKey])) {
  Ajax::bailOut(L::t('No file was uploaded. Unknown error'));
}

$error = $_FILES[$fileKey]['error'];
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

// Move the temporary file to location where we can find it later.
$fileRecord = Email::saveAttachment($_FILES[$fileKey]);

// Submit the file-record back to the java-script in order to add the
// data to the form.

if ($fileRecord === false) {
  Ajax::bailOut('Couldn\'t save temporary file for: '.$_FILES[$filesKey]['name']);
  return false;
} else {
  OCP\JSON::success(
    array(
      'data' => array(
        'type'     => $fileRecord['type'],
        'size'     => $fileRecord['size'],
        'name'     => $fileRecord['name'],
        'tmp_name' => $fileRecord['tmp_name'])));
  return true;
}

?>

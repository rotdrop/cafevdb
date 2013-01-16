<?php

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;
use CAFEVDB\Ajax;
use CAFEVDB\Email;

OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled(Config::APP_NAME);
OCP\JSON::callCheck();

if (!isset($_GET['path'])) {
  Ajax::bailOut(L::t('No attachment path was submitted.'));
}

$localpath = $_GET['path'];
$name      = OC_Filesystem::getLocalFile($localpath);

if (!file_exists($name)) {
  Ajax::bailOut(L::t('File doesn\'t exist: ').$localpath);
}

if (false) {
  Ajax::bailOut(L::t('File doesn\'t exist:').'<br/>'
                .$name.'<br/>'
                .$localpath.'<br/>'
                .OC_Filesystem::getLocalPath($name).'<br/>'
                .OC_Filesystem::getLocalPath($localpath).'<br/>'
                .print_r($fileInfo, true));
}

// Should coincide with $localpath
if ($localpath != OC_Filesystem::getLocalPath($localpath)) {
  Ajax::bailOut(L::t('Inconsistency:').' '.
                $localpath.
                ' <=> '.
                OC_Filesystem::getLocalPath($localpath));
}

$fileInfo = \OC_Files::getFileInfo($localpath);

// We emulate an uploaded file here:
$fileRecord = array(
  'name' => $localpath,
  'tmp_name' => $name,
  'type' => $fileInfo['mimetype'],
  'size' => $fileInfo['size']);

$fileRecord = Email::saveAttachment($fileRecord, false);

if ($fileRecord === false) {
  Ajax::bailOut('Couldn\'t save temporary file for: '.$localpath);
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

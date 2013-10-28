<?php
/**
 * Copyright (c) 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Originally copied from:
 *
 * Copyright (c) 2012 Thomas Tanghus <thomas@tanghus.net>
 * Copyright (c) 2011, 2012 Bart Visscher <bartv@thisnet.nl>
 * Copyright (c) 2011 Jakob Sack mail@jakobsack.de
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Musicians;

// Init owncloud

OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled('cafevdb');

Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'notamember');
  return $tmpl->printPage();
}

function getStandardImage() {
	//OCP\Response::setExpiresHeader('P10D');
	OCP\Response::enableCaching();
	OCP\Response::redirect(OCP\Util::imagePath('cafevdb', 'person_large.png'));
	exit();
}

try {
  
  Error::exceptions(true);

  $memberId = Util::cgiValue('MemberId', -1);
  $etag = null;
  $caching = null;

  if($memberId < 0) {
    getStandardImage();
  }

  if(!extension_loaded('gd') || !function_exists('gd_info')) {
    OCP\Util::writeLog('cafevdb',
                       'memberportrait.php. GD module not installed', OCP\Util::DEBUG);
    getStandardImage();
  }

  $photo = Musicians::fetchPortrait($memberId);

  if (!$photo || $photo == '') {
    OCP\Util::writeLog('cafevdb',
                       'memberportrait.php. Empty photo string for memberId = '.$memberId, OCP\Util::DEBUG);
    getStandardImage();
  }

  $image = new OC_Image();
  if (!$image) {
    OCP\Util::writeLog('cafevdb',
                       'memberportrait.php. Image could not be created', OCP\Util::DEBUG);
    getStandardImage();
  }
  

  // Photo :-), perhaps
  if ($image->loadFromBase64($photo)) {
    // OK
    $etag = md5($photo);
  }

  if ($image->valid()) {
    $modified = Musicians::fetchModified($memberId);

    // Force refresh if modified within the last minute.
    if ($modified > 0) {
      $caching = (time() - $modified > 60) ? null : 0;
    }

    OCP\Util::writeLog('cafevdb',
                       'modified: '.$modified." time: ".time()." diff: ".(time() - $modified), OCP\Util::DEBUG);
    
    OCP\Response::enableCaching($caching);
    if(!is_null($modified)) {
      OCP\Response::setLastModifiedHeader($modified);
    }
    if($etag) {
      OCP\Response::setETagHeader($etag);
    }
    $max_size = 200;
    if ($image->width() > $max_size || $image->height() > $max_size) {
      $image->resize($max_size);
    }
  } else if (!$image->valid()) {
    // Not found :-(
    getStandardImage();
  }
  header('Content-Type: '.$image->mimeType());
  $image->show();

} catch (Exception $e) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'exception');
  $tmpl->assign('exception', $e->getMessage());
  $tmpl->assign('trace', $e->getTraceAsString());
  $tmpl->assign('debug', true);
  return $tmpl->printPage();
}

?>

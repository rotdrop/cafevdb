<?php

/**Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 */

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

  $recordId   = Util::cgiValue('RecordId', -1);
  $imageClass = Util::cgiValue('ImagePHPClass', '');

  $etag = null;
  $caching = null;

  if($recordId < 0) {
    OCP\Util::writeLog('cafevdb',
                       'inlineimage.php: record-id invalid', OCP\Util::DEBUG);
    getStandardImage();
  }

  if(!extension_loaded('gd') || !function_exists('gd_info')) {
    OCP\Util::writeLog('cafevdb',
                       'inlineimage.php: GD module not installed', OCP\Util::DEBUG);
    getStandardImage();
  }

  $imageData = call_user_func(array($imageClass, 'fetchImage'), $recordId);

  if (!$imageData || $imageData == '') {
    OCP\Util::writeLog('cafevdb',
                       'inlineimage.php: Empty image string for recordId = '.$recordId, OCP\Util::DEBUG);
    getStandardImage();
  }

  $image = new OC_Image();
  if (!$image) {
    OCP\Util::writeLog('cafevdb',
                       'inlineimage.php: Image could not be created', OCP\Util::DEBUG);
    getStandardImage();
  }
  

  // Image :-), perhaps
  if ($image->loadFromBase64($imageData)) {
    // OK
    $etag = md5($imageData);
  }

  if ($image->valid()) {
    $modified = call_user_func(array($imageClass, 'fetchModified'), $recordId);

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
    OCP\Util::writeLog('cafevdb',
                       'inlineimage.php: no valid image found', OCP\Util::DEBUG);
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

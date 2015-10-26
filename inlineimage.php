<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB
{

  // Init owncloud

  \OCP\User::checkLoggedIn();
  \OCP\App::checkAppEnabled('cafevdb');

  Config::init();

  $group = Config::getAppValue('usergroup', '');
  $user  = \OCP\USER::getUser();

  if (!\OC_Group::inGroup($user, $group)) {
    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'notamember');
    return $tmpl->printPage();
  }

  function getStandardImage($placeHolder) {
    //\OCP\Response::setExpiresHeader('P10D');
    \OCP\Response::enableCaching();
    \OCP\Response::redirect(\OCP\Util::imagePath('cafevdb', $placeHolder));
    exit();
  }

  try {

    Error::exceptions(true);

    $itemId    = Util::cgiValue('ItemId', -1);
    $itemTable  = Util::cgiValue('ImageItemTable', '');
    $imageSize   = Util::cgiValue('ImageSize', 400);

    $inlineImage = new InlineImage($itemTable);

    $defaultPlaceHolder = $inlineImage->placeHolder();
    $placeHolder = Util::cgiValue('PlaceHolder', $defaultPlaceHolder);

    $etag = null;
    $caching = null;

    if($itemId <= 0) {
      \OCP\Util::writeLog('cafevdb',
                          'inlineimage.php: item-id invalid', \OCP\Util::DEBUG);
      getStandardImage($placeHolder);
    }

    if(!extension_loaded('gd') || !function_exists('gd_info')) {
      \OCP\Util::writeLog('cafevdb',
                          'inlineimage.php: GD module not installed', \OCP\Util::DEBUG);
      getStandardImage($placeHolder);
    }

    $imageData = $inlineImage->fetch($itemId);

    if (!$imageData) {
      \OCP\Util::writeLog('cafevdb',
                          'inlineimage.php: Empty image string for recordId = '.$itemId, \OCP\Util::DEBUG);
      getStandardImage($placeHolder);
    }

    $image = new \OC_Image();
    if (!$image) {
      \OCP\Util::writeLog('cafevdb',
                          'inlineimage.php: Image could not be created', \OCP\Util::DEBUG);
      getStandardImage($placeHolder);
    }


    // Image :-), perhaps
    if ($image->loadFromBase64($imageData['Data'])) {
      // OK
      $etag = $imageData['MD5'];
    }

    if ($image->valid()) {
      $modified = mySQL::fetchModified($itemId, $itemTable);

      // Force refresh if modified within the last minute.
      if ($modified > 0) {
        $caching = (time() - $modified > 60) ? null : 0;
      }

      \OCP\Util::writeLog('cafevdb',
                          'modified: '.$modified." time: ".time()." diff: ".(time() - $modified), \OCP\Util::DEBUG);

      \OCP\Response::enableCaching($caching);
      if(!is_null($modified)) {
        \OCP\Response::setLastModifiedHeader($modified);
      }
      if($etag) {
        \OCP\Response::setETagHeader($etag);
      }
      $max_size = $imageSize;
      if ($image->width() > $max_size || $image->height() > $max_size) {
        $image->resize($max_size);
      }
    } else if (!$image->valid()) {
      // Not found :-(
      \OCP\Util::writeLog('cafevdb',
                          'inlineimage.php: no valid image found', \OCP\Util::DEBUG);
      getStandardImage($placeHolder);
    }

    header('Content-Type: '.$image->mimeType());
    $image->show();

  } catch (Exception $e) {

    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'exception');
    $tmpl->assign('exception', $e->getMessage());
    $tmpl->assign('trace', $e->getTraceAsString());
    $tmpl->assign('debug', true);
    return $tmpl->printPage();
  }

} // namespace

?>

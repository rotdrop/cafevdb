<?php
/**
 * Upload portraits (photos).
 *
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Originally borrowed from:
 *
 * ownCloud - Image generator for contacts.
 *
 * @author Thomas Tanghus
 * @copyright 2012 Thomas Tanghus <thomas@tanghus.net>
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

use CAFEVDB\Util;

header("Cache-Control: no-cache, no-store, must-revalidate");

OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled('cafevdb');

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'notamember');
  return $tmpl->printPage();
}

$tmpkey = Util::cgiValue('tmpkey');
$maxsize = Util::cgiValue('maxsize', -1);

OCP\Util::writeLog('cafevdb', 'tmpphoto.php: tmpkey: '.$tmpkey, OCP\Util::DEBUG);

$image = new OC_Image();
$image->loadFromData(OC_Cache::get($tmpkey));
if($maxsize != -1) {
	$image->resize($maxsize);
}
$image();


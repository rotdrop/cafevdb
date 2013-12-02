<?php
/**
 *
 * Member photos,
 *
 * @author Claus-Justus Heine
 * @copyright 2013 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * Borrowed from:
 *
 * ownCloud - Addressbook
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

// Check if we are a user
OCP\JSON::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');

$tmpkey = Util::cgiValue('tmpkey');
$requesttoken = Util::cgiValue('requesttoken');
$recordId = Util::cgiValue('RecordId');
$imageClass = Util::cgiValue('ImagePHPClass', '');

$tmpl = new OCP\Template("cafevdb", "part.cropimage");
$tmpl->assign('tmpkey', $tmpkey);
$tmpl->assign('recordId', $recordId);
$tmpl->assign('imageClass', $imageClass);
$tmpl->assign('requesttoken', $requesttoken);
$page = $tmpl->fetchPage();

OCP\JSON::success(array('data' => array( 'page' => $page )));

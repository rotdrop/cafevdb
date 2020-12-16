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
 */

namespace CAFEVDB
{

  // Check if we are a user and the needed apps are enabled.
  \OCP\User::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::checkAppEnabled('calendar');

  Config::init();

  $group = Config::getAppValue('usergroup', '');
  $user  = \OCP\USER::getUser();

  \OCP\Util::addStyle('cafevdb', 'cafevdb');
  \OCP\Util::addStyle('cafevdb', 'tooltips');

  if (!\OC_Group::inGroup($user, $group)) {
    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'notamember');
    return $tmpl->printPage();
  }

  try {

    Error::exceptions(true);

    $tmpl = new \OCP\Template( 'cafevdb', 'expertmode');

    $expertmode = \OCP\Config::getUserValue(\OCP\USER::getUser(),'cafevdb','expertmode','');
    $tooltips = \OCP\Config::getUserValue(\OCP\USER::getUser(),'cafevdb','tooltips','');

    $tmpl->assign('expertmode', $expertmode);
    $tmpl->assign('tooltips', $tooltips );

    $links = array('phpmyadmin',
                   'phpmyadminoc',
                   'sourcecode',
                   'sourcedocs',
                   'ownclouddev');
    foreach ($links as $link) {
      $tmpl->assign($link, Config::getValue($link));
    }

    return $tmpl->printPage();

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

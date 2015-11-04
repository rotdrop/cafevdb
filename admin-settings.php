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

namespace CAFEVDB {

  \OCP\User::checkAdminUser();

  //OCP\Util::addStyle('cafevdb', 'cafevdb');
  //OCP\Util::addStyle('cafevdb', 'tooltips');
  \OCP\Util::addStyle( "cafevdb", "admin-settings" );
  \OCP\Util::addScript( "cafevdb", "admin-settings" );

  $tmpl = new \OCP\Template( 'cafevdb', 'admin-settings');

  Config::init();
  $strict = !Config::encryptionKeyValid();

  $tmpl->assign('usergroup', Config::getAppValue('usergroup', ''));

  return $tmpl->fetchPage();

} // namespace CAFEVDB

?>

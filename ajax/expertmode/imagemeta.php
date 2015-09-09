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

  // Check if we are a user
  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  try {

    Error::exceptions(true);

    Config::init();

    foreach (array('MemberPortraits', 'ProjectFlyers') as $imageTable) {
      echo '<H4>Processing '.$imageTable.'</H4><BR/>';
      Admin::sanitizeImageData($imageTable);
    }

  } catch (\Exception $e) {

    echo '<H4>Exception</H4><BR/>';
    echo '<H4>'.$e->getFile().'('.$e->getLine().'): '.$e->getMessage().'</H4><BR/>';

  }

} // namespace CAFEVDB

?>

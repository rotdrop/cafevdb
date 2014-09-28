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

/**@file
 * @brief Admin settings.
 */

namespace CAFEVDB 
{

  /** I AM HERE. */
  class Foobar
  {
    /** I AM HERE. */
    public function myMethod()
    {
      return 0;
    }
    
  }
  

  \OCP\User::checkAdminUser();
  \OCP\JSON::callCheck();
  
  if (isset($_POST['CAFEVgroup'])) {
    $value = $_POST['CAFEVgroup'];
    \OC_AppConfig::setValue('cafevdb', 'usergroup', $value);
  
    \OC_JSON::success(
      array("data" => array( "message" => L::t('Setting orchestra group to `%s\'. Please login as group administrator and configure the Camerata DB application.',
                                               array($value)))));
    return true;
  }

  \OC_JSON::error(
    array("data" => array( "message" => L::t('Unknown request.'))));
  
  return false; ///< error return
}
?>

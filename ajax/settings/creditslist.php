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
 *
 * Hall-of-fame support.
 * 
 */

namespace CAFEVDB 
{
  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  Error::exceptions(true);
  $debugText = '';
  
  ob_start();

  try {

    Config::init();

    $appInfo = \OCP\App::getAppInfo(Config::APP_NAME);

    $tmpl = new \OCP\Template(Config::APP_NAME, 'creditslist');
    $tmpl->assign('credits', $appInfo['credits']['item']);
    $html = $tmpl->fetchPage();

    $debugText .= ob_get_contents();
    @ob_end_clean();
  
    \OCP\JSON::success(
      array('data' => array(
              'contents' => $html,
              'debug' => $debugText)));
  
    return true;    

  } catch (\Exception $e) {
    $debugText .= ob_get_contents();
    @ob_end_clean();

    \OCP\JSON::error(
      array(
        'data' => array(
          'error' => 'exception',
          'message' => L::t('Error, caught an exception'),
          'debug' => $debugText,
          'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
          'trace' => $e->getTraceAsString(),
          'debug' => $debugText)));
    
    return false;
  }
}

?>

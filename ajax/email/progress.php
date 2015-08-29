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

/**@file
 * Progress bar updates.
 */

namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  try {

    ob_start();

    Error::exceptions(true);

    $_GET = array();

    $debugText = '';
    $messageText = '';

    $progressId   = Util::cgiValue('ProgressId', 0);

    $progress = new ProgressStatus($progressId);
    $result = $progress->fetch();

    if ($result === false) {
      \OCP\JSON::error(
        array('data' => array('message' => L::t('Unable to fetch progress status with id %d',
                                                array($progressId)))));
      return false;
    } else {
      \OCP\JSON::success(array('progress' => $result));  
      return true;
    }

  } catch (\Exception $e) {

    $debugText .= ob_get_contents();
    @ob_end_clean();

    \OCP\JSON::error(
      array(
        'data' => array(
          'error' => 'exception',
          'exception' => $e->getFile().'('.$e->getLine().'): '.$e->getMessage(),
          'trace' => $e->getTraceAsString(),
          'message' => L::t('Error, caught an exception'),
          'debug' => $debugText)));
    return false;
  }

} // namespace

?>

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
 * This file is responsible to load the main data-base views as
 * requested by the internal navigation buttons. It operates in two
 * modes:
 *
 * - in regular mode it records the current post data in the history
 * and loads respective template from the template folder, as
 * specified b y the Template post-variable.
 *
 * - in history restore mode, it restores a snapshot from the history,
 * replaces the $_POST variable with the history snapshot and then
 * goes on as usual. History restore is triggered by the post-variable
 * 'HistoryOffset' which, if present, triggers restoration of the
 * history and gives the offset into the current history.
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
    $_GET = array(); // only post is allowed

    $pageLoader = new PageLoader();

    $historyOffset = Util::cgiValue('HistoryOffset', false);

    if ($historyOffset !== false) {
      // Restore from history or die. The call may throw
      $originalPost = $_POST;
      $_POST = $pageLoader->fetchHistory($historyOffset);
      $_POST['OriginalPost'] = $originalPost;
    } else {
      // record new history
      $pageLoader->pushHistory($_POST);
    }
    
    // now proceed with either real of faked post-data.

    $tmpl = $pageLoader->template();
    $html = $tmpl->fetchPage();
    
    $debugText .= ob_get_contents();
    @ob_end_clean();
  
    \OCP\JSON::success(
      array('data' => array(
              'contents' => $html,
              'history' => array('size' => $pageLoader->historySize(),
                                 'position' => $pageLoader->historyPosition()),
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

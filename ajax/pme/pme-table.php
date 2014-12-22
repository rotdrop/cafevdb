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
 * Load a PME table without outer controls, intended usage are
 * jQuery dialogs. This is the Ajax query callback; it loads a
 * template with "pme-table" which actually echos the HTML.
 */

namespace CAFEVDB 
{

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  $debugText = '';

  try {

    ob_start();

    Error::exceptions(true);
    Config::init();

    $_GET = array();

    $messageText = '';
    $mySQLError = array('error' => 0,
                        'message' => '');

    if (Util::debugMode('request')) {
      $debugText .= '$_POST[] = '.print_r($_POST, true);
    }  

    $displayClass = Util::cgiValue('DisplayClass', false);
    $classArguments = Util::cgiValue('ClassArguments', array());
    $dialogMode = Util::cgiValue('AmbientContainerSelector', false) != false;
    $reloadAction = false;
    $reloadAction = Util::cgiValue('PME_sys_reloadlist', $reloadAction) != false;
    $reloadAction = Util::cgiValue('PME_sys_reloadfilter', $reloadAction) != false;

    $historySize = -1;
    $historyPosition = -1;
    if (!$dialogMode && !$reloadAction) {
      $pageLoader = new PageLoader();
      $pageLoader->pushHistory($_POST);
      $historySize = $pageLoader->historySize();
      $historyPosition = $pageLoader->historyPosition();
    } else {
      $pageLoader = false;
    }

    if (!$displayClass) {
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t("missing arguments"),
                          'message' => L::t("No class name submitted."),
                          'debug' => $debugText)));
      return false;
    }

    if (!is_array($classArguments)) {
      $debugText .= ob_get_contents();
      @ob_end_clean();

      \OCP\JSON::error(
        array(
          'data' => array('error' => L::t('invalid arguments'),
                          'message' => L::t('Class arguments are not an array'),
                          'debug' => $debugText)));
      return false;
    }

    $tmpl = new \OCP\Template('cafevdb', 'pme-table');
    $tmpl->assign('DisplayClass', $displayClass);
    $tmpl->assign('ClassArguments', $classArguments);
  
    $tmpl->assign('recordId', Util::getCGIRecordId());

    $html = $tmpl->fetchPage();

    // Search for MySQL error messages echoes by phpMyEdit, sometimes
    // the contents of the template will be discarded, but we still want
    //
    // to get the error messages.
    //
    // <h4>MySQL error 1288</h4>The target table Spielwiese2013View of the DELETE is not updatable<hr size="1">

    if (preg_match('|<h4>MySQL error (\d+)</h4>\s*([^<]+)|', $html, $matches)) {
      $mySQLError = array('error' => $matches[1],
                          'message' => $matches[2]);
    }

    $debugText .= ob_get_contents();
    @ob_end_clean();
  
    \OCP\JSON::success(
      array('data' => array(
              'contents' => $html,
              'sqlerror' => $mySQLError,
              'history' => array('size' => $historySize,
                                 'position' => $historyPosition),
              'debug' => $debugText)));

    if ($pageLoader !== false) {
      $pageLoader->storeHistory();
    }

    return true;

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
          'sqlerror' => $mySQLError,
          'debug' => $debugText)));
    return false;
  }

} // namespace CAFEVDB

?>

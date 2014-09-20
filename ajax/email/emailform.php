<?php
/**Orchestra member, musician and project management application.
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

/**@file Load a PME table without outer controls, intended usage are
 * jQuery dialogs. This is the Ajax query callback; it loads a
 * template with "pme-table" which actually echos the HTML.
 */

\OCP\JSON::checkLoggedIn();
\OCP\JSON::checkAppEnabled('cafevdb');
\OCP\JSON::callCheck();

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Events;
use CAFEVDB\Util;
use CAFEVDB\Error;
use CAFEVDB\Navigation;

try {

  ob_start();

  Error::exceptions(true);
  Config::init();

  $_GET = array();

  $debugText = '';
  $messageText = '';

  if (Util::debugMode('request')) {
    $debugText .= '$_POST[] = '.print_r($_POST, true);
  }  

  $tmpl = new OCP\Template('cafevdb', 'emailform');

  $tmpl->assign('uploadMaxFilesize', Util::maxUploadSize(), false);
  $tmpl->assign('uploadMaxHumanFilesize',
                OCP\Util::humanFileSize(Util::maxUploadSize()), false);
  $tmpl->assign('requesttoken', \OCP\Util::callRegister());

  $tmpl->assign('ProjectName', 'Foobar');
  $tmpl->assign('ProjectId', 42);
  $tmpl->assign('templateName', '');
  $tmpl->assign('templateNames', array());

  // Needed for the editor
  $tmpl->assign('BCC', '');
  $tmpl->assign('CC', '');
  $tmpl->assign('mailTag', '[CAFEV-Blah]');
  $tmpl->assign('subject', '');
  $tmpl->assign('message', '');
  $tmpl->assign('sender', '');
  $tmpl->assign('catchAllEmail', '');
  $tmpl->assign('fileAttach', array());
  
  // Needed for the recipient selection
  $tmpl->assign('FormData', array('blah' => array('foo' => 'bar')));
  $tmpl->assign('BasicRecipientSet', array('FromProject' => 1, 'ExceptProject' => 0));
  $tmpl->assign('MemberStatusFilter', array(array('name' => 'Name1',
                                                  'value' => 'Value1',
                                                  'flags' => Navigation::SELECTED),
                                            array('name' => 'Name2',
                                                  'value' => 'Value2',
                                                  'flags' => Navigation::DISABLED)
                  ));
  $tmpl->assign('EmailRecipientsChoices', array(array('value' => 14,
                                                      'name' => 'himself@claus-justus-heine.de',
                                                      'flags' => Navigation::SELECTED),
                                                array('value' => 15,
                                                      'label' => 'blah',
                                                      'name' => 'baggins@mordor.lost')
                 ));
  $tmpl->assign('InstrumentsFilter', array(array('name' => '*',
                                                 'value' => '*'),
                                           array('name' => 'Bratsche',
                                                 'value' => 'Bratsche')
                  ));
  $tmpl->assign('MissingEmailAddresses', array('Bug Bunny', 'Mr. Universe'));

  $html = $tmpl->fetchPage();

  $debugText .= ob_get_contents();
  @ob_end_clean();
  
  OCP\JSON::success(
    array('data' => array('contents' => $html,
                          'debug' => $debugText)));
  
  return true;

} catch (\Exception $e) {

  $debugText .= ob_get_contents();
  @ob_end_clean();

  OCP\JSON::error(
    array(
      'data' => array(
        'error' => 'exception',
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'message' => L::t('Error, caught an exception'),
        'debug' => $debugText)));
  return false;
}

?>

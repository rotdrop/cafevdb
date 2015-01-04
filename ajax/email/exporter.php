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
 * @brief Mass-email composition AJAX handler.
 */

namespace CAFEVDB {

  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::callCheck();

  date_default_timezone_set(Util::getTimezone());
  
  $name = strftime('%Y%m%d-%H%M%S').'-CAFEVDB-emailform.html';

  header('Content-type: text/html');
  header('Content-disposition: attachment;filename='.$name);
  header('Cache-Control: max-age=0');

  echo <<<__EOT__
<!DOCTYPE HTML>
  <html>
    <head>
      <title>$name</title>
      <meta charset="utf-8">
    </head>
    <body>
__EOT__;
  
  try {

    Error::exceptions(true);
    Config::init();

    $_GET = array();

    $defaultData = array('Request' => 'update',
                         'FormElement' => 'everything',
                         'ProjectId' => Util::cgiValue('ProjectId', -1),
                         'ProjectName' => Util::cgiValue('ProjectName', ''));
    $requestData = array_merge($defaultData, Util::cgiValue('emailComposer', array()));
    $projectId   = $requestData['ProjectId'];
    $projectName = $requestData['ProjectName'];

    $recipientsFilter = new EmailRecipientsFilter();
    $recipients = $recipientsFilter->selectedRecipients();
    $composer = new EmailComposer($recipients);
    $requestData['errorStatus'] = $composer->errorStatus();
    $requestData['diagnostics'] = $composer->statusDiagnostics();

    if ($requestData['errorStatus']) {
      $caption = $requestData['diagnostics']['Caption'];

      $tmpl = new \OCP\Template('cafevdb', 'part.emailform.statuspage');
      $tmpl->assign('Projectname', $projectName);
      $tmpl->assign('ProjectId', $projectId);
      $tmpl->assign('Diagnostics', $requestData['diagnostics']);

      $tmpl->printPage();
    }

  } catch (\Exception $e) {

    $exceptionText = $e->getFile().'('.$e->getLine().'): '.$e->getMessage();
    $trace = $e->getTraceAsString();

    $admin = Config::adminContact();

    $mailto = $admin['email'].
      '?subject='.rawurlencode('[CAFEVDB-Exception] Exceptions from Email-Form').
      '&body='.rawurlencode($exceptionText."\r\n".$trace);
    $mailto = '<span class="error email"><a href="mailto:'.$mailto.'">'.$admin['name'].'</a></span>';

    echo '<h1>'.L::t('PHP Exception Caught').'</h1>
<blockquote>'.L::t('Error, caught an exception. '.
                   'Please copy the displayed text and send it by email to %s.',
                   array($mailto)).'</blockquote>
<pre>'.$exceptionText.'</pre>
<h2>'.L::t('Trace').'</h2>
<pre>'.$trace.'</pre>';
  }

  echo <<<__EOT__
  </body>
</html>
__EOT__;

} // namespace

?>

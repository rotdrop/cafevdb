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

/**@file
 * Main entry point.
 */

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Admin;
use CAFEVDB\ConfigCheck;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\Error;

// Check if we are a user
OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled('cafevdb');

Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addStyle('cafevdb', 'tipsy');

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'notamember');
  return $tmpl->printPage();
} else if( !\OC_App::isEnabled('calendar')) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'nocalendar');
  return $tmpl->printPage();
} else if( !\OC_App::isEnabled('contacts')) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'nocontacts');
  return $tmpl->printPage();
}

try {
  
  Error::exceptions(true);
  
  // Are we a group-admin?
  $admin = OC_SubAdmin::isGroupAccessible($user, $group);

  $tooltips   = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'tooltips', 'on');
  $usrHdrVis  = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'headervisibility', 'expanded');
  $usrFiltVis = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'filtervisibility', 'off');
  $encrkey    = Config::getEncryptionKey();

  // Initialize with cgi or user-value
  $headervisibility = Util::cgiValue('headervisibility', $usrHdrVis);

  // Filter visibility is stored here:  
  Config::$pmeopts['cgi']['append'][Config::$pmeopts['cgi']['prefix']['sys'].'fl'] =
    $usrFiltVis == 'off' ? 0 : 1;

  Util::addExternalScript("https://maps.google.com/maps/api/js?sensor=false");
  Util::addExternalScript(OC_Helper::linkTo('calendar/js', 'l10n.php'));

  // js/config.php generated dynamic JavaScript and thus "cheats" the
  // CSP rules. We have here the possibility to pass some selected
  // CGI-parameters or other PHP-variables on to the JavaScript code.
  Util::addExternalScript(OC_Helper::linkToRoute('cafevdb_config',
                                                 array('headervisibility' => $headervisibility))); 

  OCP\App::setActiveNavigationEntry( 'cafevdb' );

  OCP\Util::addStyle('cafevdb', 'pme-table');
  OCP\Util::addStyle('cafevdb', 'settings');
  OCP\Util::addStyle('cafevdb', 'events');
  OCP\Util::addStyle('cafevdb', 'sepa-debit-mandate');
  OCP\Util::addStyle('cafevdb', 'email');
  OCP\Util::addStyle('cafevdb', 'blog');
  OCP\Util::addStyle('cafevdb', 'projects');
  OCP\Util::addStyle('cafevdb', 'inlineimage');  
  OCP\Util::addStyle('cafevdb', 'jquery.Jcrop');  
  OCP\Util::addStyle("cafevdb/3rdparty", "chosen/chosen");
  OCP\Util::addStyle('3rdparty/fontawesome', 'font-awesome');
  OCP\Util::addStyle('cafevdb', 'font-awesome');

  OCP\Util::addScript('cafevdb', 'cafevdb');
  OCP\Util::addScript('cafevdb', 'pme');
  OCP\Util::addScript('cafevdb', 'transpose');
  OCP\Util::addScript('cafevdb', 'page');
  OCP\Util::addScript('cafevdb', 'events');
  OCP\Util::addScript('cafevdb', 'blog');
  OCP\Util::addScript('cafevdb', 'projects');
  OCP\Util::addScript('cafevdb', 'inlineimage');
  OCP\Util::addScript('cafevdb', 'sepa-debit-mandate');
  OCP\Util::addScript('cafevdb', 'jquery.Jcrop');

  OCP\Util::addscript('files',   'jquery.iframe-transport');
  OCP\Util::addscript('files',   'jquery.fileupload');
  OCP\Util::addscript('cafevdb', 'file-upload');

  OCP\Util::addScript('cafevdb', 'email');

  // TinyMCE stuff
  OCP\Util::addScript('cafevdb/3rdparty', 'tinymce/tinymce.min');
  OCP\Util::addScript('cafevdb/3rdparty', 'tinymce/jquery.tinymce.min');
  OCP\Util::addScript('cafevdb/3rdparty', 'tinymceinit');
  // CKEditor stuff
  OCP\Util::addScript('cafevdb/3rdparty', 'ckeditor/ckeditor');
  OCP\Util::addScript('cafevdb/3rdparty', 'ckeditor/adapters/jquery');
  
  OCP\Util::addscript("cafevdb/3rdparty", "chosen/chosen.jquery.min");

  OCP\Util::addscript("cafevdb/3rdparty/QuickForm2", "quickform");
  OCP\Util::addscript("cafevdb/3rdparty/QuickForm2", "dualselect");

  // Calendar event hacks
  OCP\Util::addscript('3rdparty/timepicker', 'jquery.ui.timepicker');
  OCP\Util::addStyle('3rdparty/timepicker', 'jquery.ui.timepicker');
  OCP\Util::addscript('', 'jquery.multiselect');
  OCP\Util::addStyle('', 'jquery.multiselect');
  OCP\Util::addscript('contacts','jquery.multi-autocomplete');
  OCP\Util::addscript('','tags');
  OCP\Util::addScript('cafevdb', 'calendar');
  OCP\Util::addScript('calendar', 'on-event');

  // end event hacks

  // Determine which template has to be used

  $config = ConfigCheck::configured();

  // following three may or may not be set
  $project    = Util::cgiValue('Project', '');
  $projectId  = Util::cgiValue('ProjectId', -1);
  $musicianId = Util::cgiValue('MusicianId',-1);
  $recordKey  = Config::$pmeopts['cgi']['prefix']['sys'].'rec';
  $recordId   = Util::cgiValue($recordKey, -1);

  if (!$config['summary']) {
    $tmplname = 'configcheck';
  } else {
    /* Special hack to determine if the email-form was requested through
     * the pme-miscinfo button.
     */
    $opreq = Util::cgiValue(Config::$pmeopts['cgi']['prefix']['sys'].'operation');

    $op     = parse_url($opreq, PHP_URL_PATH);
    $opargs = array();
    parse_str(parse_url($opreq, PHP_URL_QUERY), $opargs);

    if ($recordId < 0 && isset($opargs[$recordKey])) {
      $recordId = $opargs[$recordKey];
    }

    if (false) {
      echo "<PRE>\n";
      print_r($opargs);
      echo $recordId;
      echo "</PRE>\n";
    }

    if ($op == "Em@il") {
      $tmplname = 'email';
      $_POST['Template'] = 'email';
    } else if (strpos($op, strval(L::t('Add all to %s', $project))) === 0) {
      $tmplname = 'bulk-add-musicians';
      $_POST['Template'] = 'bulk-add-musicians';
    } else {
      $tmplname = Util::cgiValue('Template', 'blog');
      if (false) {
        // Does not seem to work well.
        if ($op == '' && $recordId < 0) {
          // Enable 5 Minutes of Cache for non-critical requests.
          \OCP\Response::enableCaching(15);
          \OCP\Response::setLastModifiedHeader(Admin::getLastModified());
        }
      }
    }
  }

  switch ($tmplname) {
  case 'projects':
    OCP\Util::addStyle('cafevdb', $tmplname);
    OCP\Util::addScript('cafevdb', $tmplname);
    break;
  case 'project-instruments':
    OCP\Util::addStyle('cafevdb', $tmplname);
    OCP\Util::addScript('cafevdb', $tmplname);
    break;
  default:
    /* nothing */
    break;
  }

  // One last script to load after the other, e.g. to get the tipsy
  // stuff and so on right
  OCP\Util::addScript('cafevdb', 'document-ready');

  $tmpl = new OCP\Template('cafevdb', $tmplname, 'user');
  
  $tmpl->assign('configcheck', $config);
  $tmpl->assign('orchestra', Config::getValue('orchestra'));
  $tmpl->assign('groupadmin', $admin);
  $tmpl->assign('usergroup', $group);
  $tmpl->assign('user', $user);
  $tmpl->assign('expertmode', Config::$expertmode);
  $tmpl->assign('tooltips', $tooltips);
  $tmpl->assign('encryptionkey', $encrkey);
  $tmpl->assign('uploadMaxFilesize', Util::maxUploadSize(), false);
  $tmpl->assign('uploadMaxHumanFilesize',
                OCP\Util::humanFileSize(Util::maxUploadSize()), false);
  $tmpl->assign('projectName', $project);
  $tmpl->assign('projectId', $projectId);
  $tmpl->assign('musicianId', $musicianId);
  $tmpl->assign('recordId', $recordId);
  $tmpl->assign('locale', Util::getLocale());
  $tmpl->assign('timezone', Util::getTimezone());

  $tmpl->assign('headervisibility', $headervisibility);

  $tmpl->printPage();

} catch (Exception $e) {
  ob_end_clean();
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'exception');
  $tmpl->assign('exception', $e->getMessage());
  $tmpl->assign('trace', $e->getTraceAsString());
  $tmpl->assign('debug', true);
  $admin =
    \OCP\User::getDisplayName('admin').
    ' <'.\OCP\Config::getUserValue('admin', 'settings', 'email').'>';
  $tmpl->assign('admin', htmlentities($admin));

  return $tmpl->printPage();
}

?>

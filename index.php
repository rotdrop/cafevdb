<?php
/**@file
 * Main entry point.
 */

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\ConfigCheck;
use CAFEVDB\Util;
use CAFEVDB\Navigation;
use CAFEVDB\Error;

// Check if we are a user
OCP\User::checkLoggedIn();

Config::init();

$group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
$user  = OCP\USER::getUser();

OCP\Util::addStyle('cafevdb', 'cafevdb');

if (!OC_Group::inGroup($user, $group)) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'notamember');
  return $tmpl->printPage();
}

try {
  
  Error::exceptions(true);
  
  // Are we a group-admin?
  $admin = OC_SubAdmin::isGroupAccessible($user, $group);

  $expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'expertmode','');
  $debugmode  = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'debugmode','');
  $tooltips   = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb', 'tooltips','');
  $encrkey    = Config::getEncryptionKey();

  $headervisibility = Util::cgiValue('headervisibility', 'expanded');

  Util::addExternalScript("https://maps.google.com/maps/api/js?sensor=false");
  Util::addExternalScript(OC_Helper::linkTo('calendar/js', 'l10n.php'));
  Util::addExternalScript(OC_Helper::linkTo('cafevdb/js', 'config.php'));

  OCP\App::setActiveNavigationEntry( 'cafevdb' );

  OCP\Util::addStyle('cafevdb', 'cafevdb');
  OCP\Util::addStyle('cafevdb', 'pme-table');
  OCP\Util::addStyle('cafevdb', 'settings');
  OCP\Util::addStyle('cafevdb', 'events');
  OCP\Util::addStyle('cafevdb', 'email');
  OCP\Util::addStyle('cafevdb', 'blog');
  OCP\Util::addStyle('cafevdb', 'photo');  
  OCP\Util::addStyle('cafevdb', 'jquery.Jcrop');  
  OCP\Util::addStyle("cafevdb/3rdparty", "chosen/chosen");

  OCP\Util::addScript('cafevdb', 'cafevdb');
  OCP\Util::addScript('cafevdb', 'transpose');
  OCP\Util::addScript('cafevdb', 'page');
  OCP\Util::addScript('cafevdb', 'email');
  OCP\Util::addScript('cafevdb', 'events');
  OCP\Util::addScript('cafevdb', 'blog');
  OCP\Util::addScript('cafevdb', 'photo');
  OCP\Util::addScript('cafevdb', 'jquery.Jcrop');
  OCP\Util::addScript('cafevdb/3rdparty', 'tinymce/jscripts/tiny_mce/tiny_mce');
  OCP\Util::addScript('cafevdb/3rdparty', 'tinymce/jscripts/tiny_mce/jquery.tinymce');
  OCP\Util::addScript('cafevdb/3rdparty', 'tinymceinit');
  OCP\Util::addscript("cafevdb/3rdparty", "chosen/chosen.jquery.min");

// Calendar event hacks
  OCP\Util::addscript('3rdparty/fullcalendar', 'fullcalendar');
  OCP\Util::addscript('3rdparty/timepicker', 'jquery.ui.timepicker');
  OCP\Util::addStyle('3rdparty/timepicker', 'jquery.ui.timepicker');
  OCP\Util::addscript('', 'jquery.multiselect');
  OCP\Util::addStyle('', 'jquery.multiselect');
  OCP\Util::addscript('contacts','jquery.multi-autocomplete');
  OC_Util::addScript('','oc-vcategories');
  OCP\Util::addScript('cafevdb', 'calendar');
  OCP\Util::addScript('calendar', 'on-event');
  $categories = json_encode(OC_Calendar_App::getCategoryOptions());
//OCP\Util::addScript('cafevdb', 'debug');

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
    }
  }

  $tmpl = new OCP\Template( 'cafevdb', $tmplname, 'user' );

  $tmpl->assign('configcheck', $config);
  $tmpl->assign('orchestra', Config::getValue('orchestra'));
  $tmpl->assign('groupadmin', $admin);
  $tmpl->assign('usergroup', $group);
  $tmpl->assign('debugmode', $debugmode);
  $tmpl->assign('expertmode', $expertmode);
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
  $tmpl->assign('headervisibility', $headervisibility);

  $tmpl->printPage();

} catch (Exception $e) {
  $tmpl = new OCP\Template( 'cafevdb', 'errorpage', 'user' );
  $tmpl->assign('error', 'exception');
  $tmpl->assign('exception', $e->getMessage());
  $tmpl->assign('trace', $e->getTraceAsString());
  $tmpl->assign('debug', true);
  return $tmpl->printPage();
}

?>

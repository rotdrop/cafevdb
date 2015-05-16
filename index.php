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
 * Main entry point.
 */

namespace CAFEVDB 
{

  // Check if we are a user
  \OCP\User::checkLoggedIn();
  \OCP\App::checkAppEnabled('cafevdb');

  Config::init();

  $group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');
  $user  = \OCP\USER::getUser();

  \OCP\Util::addStyle('cafevdb', 'cafevdb');
  \OCP\Util::addStyle('cafevdb', 'tipsy');

  if (!\OC_Group::inGroup($user, $group)) {
    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'notamember');
    return $tmpl->printPage();
  } else if( !\OC_App::isEnabled('calendar')) {
    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'nocalendar');
    return $tmpl->printPage();
  } else if( !\OC_App::isEnabled('contacts')) {
    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'nocontacts');
    return $tmpl->printPage();
  }

  try {
  
    Error::exceptions(true);
  
    Util::addExternalScript("https://maps.google.com/maps/api/js?sensor=false");
    Util::addExternalScript(\OC_Helper::linkTo('calendar/js', 'l10n.php'));

    // js/config.php generated dynamic JavaScript and thus "cheats" the
    // CSP rules. We have here the possibility to pass some selected
    // CGI-parameters or other PHP-variables on to the JavaScript code.
    Util::addExternalScript(\OC_Helper::linkToRoute('cafevdb_config', array()));

    \OCP\App::setActiveNavigationEntry( 'cafevdb' );

    \OCP\Util::addStyle('cafevdb', 'pme-table');
    \OCP\Util::addStyle('cafevdb', 'settings');
    \OCP\Util::addStyle('cafevdb', 'about');
    \OCP\Util::addStyle('cafevdb', 'events');
    \OCP\Util::addStyle('cafevdb', 'sepa-debit-mandate');
    //\OCP\Util::addStyle('cafevdb', 'email');
    \OCP\Util::addStyle('cafevdb', 'emailform');
    \OCP\Util::addStyle('cafevdb', 'blog');
    \OCP\Util::addStyle('cafevdb', 'projects');
    \OCP\Util::addStyle('cafevdb', 'project-instruments');
    \OCP\Util::addStyle('cafevdb', 'instrumentation');
    \OCP\Util::addStyle('cafevdb', 'inlineimage');  
//    \OCP\Util::addStyle('3rdparty/fontawesome', 'font-awesome');
    \OCP\Util::addStyle('cafevdb', 'font-awesome');
    \OCP\Util::addStyle('core', 'icons');
    \OCP\Util::addStyle('cafevdb', 'navsnapper');
    
    \OCP\Util::addScript('cafevdb', 'cafevdb');
    \OCP\Util::addScript('cafevdb', 'pme');
    \OCP\Util::addScript('cafevdb', 'page');
    \OCP\Util::addScript('cafevdb', 'inlineimage');
    \OCP\Util::addScript('cafevdb', 'events');
    \OCP\Util::addScript('cafevdb', 'blog');
    \OCP\Util::addScript('cafevdb', 'projects');
    \OCP\Util::addScript('cafevdb', 'project-instruments');
    \OCP\Util::addScript('cafevdb', 'sepa-debit-mandate');
    \OCP\Util::addScript('cafevdb', 'instrumentation');
    \OCP\Util::addScript('cafevdb', 'musicians');
    \OCP\Util::addScript('cafevdb', 'insurance');

    \OCP\Util::addScript('cafevdb', 'jquery.Jcrop');
    \OCP\Util::addStyle('cafevdb', 'jquery.Jcrop');

    \OCP\Util::addScript('core', 'jquery-showpassword');

    \OCP\Util::addscript('files',   'jquery.iframe-transport');
    \OCP\Util::addscript('files',   'jquery.fileupload');
    \OCP\Util::addscript('cafevdb', 'file-upload');

    \OCP\Util::addScript('cafevdb', 'email');

    // TinyMCE stuff
    \OCP\Util::addScript('cafevdb/3rdparty', 'tinymce/tinymce.min');
    \OCP\Util::addScript('cafevdb/3rdparty', 'tinymce/jquery.tinymce.min');
    \OCP\Util::addScript('cafevdb/3rdparty', 'tinymceinit');
    // CKEditor stuff
    \OCP\Util::addScript('cafevdb/3rdparty', 'ckeditor/ckeditor');
    \OCP\Util::addScript('cafevdb/3rdparty', 'ckeditor/adapters/jquery');
  
    // Updated chosen version
    \OCP\Util::addscript("cafevdb/3rdparty/chosen", "chosen.jquery.min");
    \OCP\Util::addStyle("cafevdb/3rdparty/chosen", "chosen.min");

    // Callback for waiting until images have been loaded
    \OCP\UTIL::addscript("cafevdb/3rdparty", "imagesloaded/imagesloaded.pkgd.min");

    // dual-select list-box for email recipient selection
    //\OCP\Util::addstyle("cafevdb/3rdparty/bootstrap", "bootstrap.min");
    \OCP\Util::addstyle("cafevdb/3rdparty/bootstrap-duallistbox", "bootstrap-duallistbox-quirks");
    \OCP\Util::addstyle("cafevdb/3rdparty/bootstrap-duallistbox", "bootstrap-duallistbox.min");
    \OCP\Util::addscript("cafevdb/3rdparty/bootstrap-duallistbox", "jquery.bootstrap-duallistbox.min");
  
    // Calendar event hacks ... TODO: check whether still needed ...
    \OCP\Util::addscript('calendar/3rdparty/timepicker', 'jquery.ui.timepicker');
    \OCP\Util::addStyle('calendar/3rdparty/timepicker', 'jquery.ui.timepicker');
    \OCP\Util::addscript('calendar/3rdparty/jquery.multiselect', 'jquery.multiselect');
    \OCP\Util::addStyle('calendar/3rdparty/jquery.multiselect', 'jquery.multiselect');
    \OCP\Util::addscript('contacts','jquery.multi-autocomplete');
    \OCP\Util::addscript('','tags');
    \OCP\Util::addScript('cafevdb', 'calendar');
    \OCP\Util::addScript('calendar', 'on-event');

    // end event hacks

    // One last script to load after the other, e.g. to get the
    // tipsy stuff and so on right
    \OCP\Util::addScript('cafevdb', 'document-ready');    

    // Load the requested page :)
    $pageLoader = new PageLoader();
    if (!isset($_POST['Template']) && !$pageLoader->historyEmpty()) {
      $originalPost = $_POST;
      $_POST = $pageLoader->fetchHistory(0);
      $_POST['OriginalPost'] = $originalPost;
    } else {
      $pageLoader->pushHistory($_POST);
    }
    $pageLoader->template('user')->printPage();

  } catch (\Exception $e) {

    $tmpl = new \OCP\Template( 'cafevdb', 'errorpage', 'user' );
    $tmpl->assign('error', 'exception');
    $tmpl->assign('exception', $e->getMessage());
    $tmpl->assign('trace', $e->getTraceAsString());
    $tmpl->assign('debug', true);
    $admin = \OCP\User::getDisplayName('admin').
      ' <'.\OCP\Config::getUserValue('admin', 'settings', 'email').'>';
    $tmpl->assign('admin', Util::htmlEncode($admin));

    return $tmpl->printPage();

  }

} // namespace CAFEVDB

?>

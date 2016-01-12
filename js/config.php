<?php
// Trick the CSP in order to configure js variables and stuff from PHP code

namespace CAFEVDB {

  // Check if we are a user
  \OCP\User::checkLoggedIn();

  // And whether the app is enabled
  \OCP\App::checkAppEnabled('cafevdb');

  // Set the content type to Javascript
  \OCP\JSON::setContentTypeHeader('text/javascript');

  // Disallow caching
  header("Cache-Control: no-cache, must-revalidate");
  header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

  Config::init();

  $user = \OCP\USER::getUser();

  $tooltips  = \OCP\Config::getUserValue($user, 'cafevdb', 'tooltips', '');
  $directChg = \OCP\Config::getUserValue($user, 'cafevdb', 'directchange', '');
  $language  = \OCP\Config::getUserValue($user, 'core', 'lang', 'en');
  $editor    = \OCP\Config::getUserValue($user, 'cafevdb', 'wysiwygEditor', 'tinymce');

  $admin = Config::adminContact();
  $adminEmail = $admin['email'];
  $adminName = $admin['name'];

  $pageLoader = new PageLoader();

  $array = array(
    "CAFEVDB.toolTipsEnabled" => ($tooltips == "off" ? 'false' : 'true'),
    "CAFEVDB.wysiwygEditor" => "'".$editor."'",
    "CAFEVDB.language" => "'".$language."'",
    "CAFEVDB.Page.historySize" => $pageLoader->historySize(),
    "CAFEVDB.Page.historyPosition" => $pageLoader->historyPosition(),
    "CAFEVDB.adminEmail" => "'".$adminEmail."'",
    "CAFEVDB.adminName" => "'".$adminName."'",
    "CAFEVDB.phpUserAgent" => "'".$_SERVER['HTTP_USER_AGENT']."'",
    "PHPMYEDIT.directChange" => ($directChg == "on" ? 'true' : 'false'),
    "PHPMYEDIT.selectChosen" => "true",
    "PHPMYEDIT.filterSelectPlaceholder" => "'".L::t("Select a filter option.")."'",
    "PHPMYEDIT.filterSelectNoResult" => "'".L::t("No values match.")."'",
    "PHPMYEDIT.filterSelectChosenTitle" => "'".L::t("Select from the pull-down menu. ".
                                                    "Double-click will submit the form. ".
                                                    "The pull-down can be closed by clicking ".
                                                    "anywhere outside the menu.")."'",
    "PHPMYEDIT.inputSelectPlaceholder" => "'".L::t("Select an option.")."'",
    "PHPMYEDIT.inputSelectNoResult" => "'".L::t("No values match.")."'",
    "PHPMYEDIT.inputSelectChosenTitle" => "'".L::t("Select from the pull-down menu. ".
                                                   "The pull-down can be closed by clicking ".
                                                   "anywhere outside the menu.")."'",
    );

// Echo it
  echo "var CAFEVDB = CAFEVDB || {};\n";
  echo "CAFEVDB.Projects = CAFEVDB.Projects || {};\n";
  echo "CAFEVDB.Page = CAFEVDB.Page || {};\n";
  echo "var PHPMYEDIT = PHPMYEDIT || {} ;\n";
  foreach ($array as  $setting => $value) {
    echo($setting ."=".$value.";\n");
  }

}

?>

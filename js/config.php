<?php
// Trick the CSP in order to configure js variables and stuff from PHP code

use CAFEVDB\L;
use CAFEVDB\Config;
use CAFEVDB\Util;
use CAFEVDB\Error;

// Set the content type to Javascript
header("Content-type: text/javascript");

// Disallow caching
header("Cache-Control: no-cache, must-revalidate"); 
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); 

Config::init();

$headervisibility = Util::cgiValue('headervisibility', 'expanded');
$tooltips         = OCP\Config::getUserValue(OCP\USER::getUser(), 'cafevdb', 'tooltips', '');
$language         = OCP\Config::getUserValue(OCP\User::getUser(), 'core', 'lang', 'en');
$editor           = OCP\Config::getUserValue(OCP\User::getUser(), 'cafevdb', 'wysiwygEditor', 'tinymce');

$array = array(
  "CAFEVDB.headervisibility" => "'".$headervisibility."'",
  "CAFEVDB.toolTips" => ($tooltips == "off" ? 'false' : 'true'),
  "CAFEVDB.wysiwygEditor" => "'".$editor."'",
  "CAFEVDB.language" => "'".$language."'",
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
  "PHPMYEDIT.chosenPixelWidth" => "['projectname', 'add-instruments']",
  );

// Echo it
echo "var CAFEVDB = CAFEVDB || {};\n";
echo "CAFEVDB.Projects = CAFEVDB.Projects || {};\n";
echo "var PHPMYEDIT = PHPMYEDIT || {} ;\n";
foreach ($array as  $setting => $value) {
	echo($setting ."=".$value.";\n");
}

?>

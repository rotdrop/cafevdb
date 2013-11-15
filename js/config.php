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

$array = array(
  "CAFEVDB.headervisibility" => "'".$headervisibility."'",
  "CAFEVDB.toolTips" => ($tooltips == "off" ? 'false' : 'true'),
  "CAFEVDB.wysiwygEditor" => "'".Config::$opts['editor']."'",
  "CAFEVDB.language" => "'".$language."'",
  "PHPMYEDIT.filterSelectPlaceholder" => "'".L::t("Select a filter Option")."'",
  "PHPMYEDIT.filterSelectChosen" => "true",
  );

// Echo it
foreach ($array as  $setting => $value) {
	echo($setting ."=".$value.";\n");
}

print <<< __EOT__
$(document).ready(function() {
    if (CAFEVDB.toolTips) {
      $.fn.tipsy.enable();
    } else {
      $.fn.tipsy.disable();
    }
});
__EOT__

?>

<?php

$tmpl = new OCP\Template( 'cafevdb', 'settings');

$expertmode  = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');
$tooltips    = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','tooltips','');
$encrkey     = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','encryptionkey','');
$exampletext = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','exampletext','');

$jsscript = 'var toolTips = '.($tooltips == 'on' ? 'true' : 'false').';
';
$jsscript .=<<<__EOT__
if (toolTips) {
  \$.fn.tipsy.disable();
} else {
  \$.fn.tipsy.disable();
}
__EOT__;

$tmpl->assign('expertmode', $expertmode);
$tmpl->assign('tooltips', $tooltips);
$tmpl->assign('encryptionkey', $encrkey);
$tmpl->assign('exampletext', $exampletext);
$tmpl->assign( 'jsscript', $jsscript );

OCP\Util::addStyle('cafevdb', 'cafevdb');
OCP\Util::addScript( "cafevdb", "settings" );

return $tmpl->printPage();


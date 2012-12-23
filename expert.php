<?php

use CAFEVDB\L;

$tmpl = new OCP\Template( 'cafevdb', 'expertmode');

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');
$tooltips = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','tooltips','');

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
$tmpl->assign( 'tooltips', $tooltips );
$tmpl->assign( 'jsscript', $jsscript );

OCP\Util::addScript( "cafevdb", "expertmode" );

return $tmpl->printPage();



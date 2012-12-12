<?php

$tmpl = new OCP\Template( 'cafevdb', 'settings');

$expertmode  = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');
$exampletext = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','exampletext','');

$tmpl->assign('expertmode', $expertmode);
$tmpl->assign('exampletext', $exampletext);

OCP\Util::addScript( "cafevdb", "settings" );

return $tmpl->printPage();


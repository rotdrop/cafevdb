<?php

$tmpl = new OCP\Template( 'cafevdb', 'settings');

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');

$tmpl->assign('expertmode', $expertmode);

OCP\Util::addScript( "cafevdb", "settings" );

return $tmpl->printPage();

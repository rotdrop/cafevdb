<?php

$l=OC_L10N::get('cafevdb');

$tmpl = new OCP\Template( 'cafevdb', 'expertmode');

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');

$tmpl->assign('expertmode', $expertmode);

OCP\Util::addScript( "cafevdb", "expertmode" );

return $tmpl->printPage();



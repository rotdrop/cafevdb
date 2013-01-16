<?php

use CAFEVDB\L;
use CAFEVDB\Util;
use CAFEVDB\Config;

// Check if we are a user and the needed apps are enabled.
OCP\User::checkLoggedIn();
OCP\JSON::checkAppEnabled('cafevdb');
OCP\JSON::checkAppEnabled('calendar');

Config::init();

$tmpl = new OCP\Template( 'cafevdb', 'expertmode');

$expertmode = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','expertmode','');
$tooltips = OCP\Config::getUserValue(OCP\USER::getUser(),'cafevdb','tooltips','');

Util::addInlineScript('var toolTips = '.($tooltips == 'on' ? 'true' : 'false'));
Util::addInlineScript(<<<__EOT__
$(document).ready(function(){
  if (toolTips) {
    $.fn.tipsy.enable();
  } else {
    $.fn.tipsy.disable();
  }
});
__EOT__
);

OCP\Util::addScript( "cafevdb", "expertmode" );

$tmpl->assign('expertmode', $expertmode);
$tmpl->assign( 'tooltips', $tooltips );

$links = array('phpmyadmin',
               'phpmyadminoc',
               'sourcecode',
               'sourcedocs',
               'ownclouddev');
foreach ($links as $link) {
  $tmpl->assign($link, Config::getValue($link));
}

return $tmpl->printPage();



<?php

use CAFEVDB\L;
use CAFEVDB\Events;
use CAFEVDB\Config;
use CAFEVDB\Util;

OCP\User::checkLoggedIn();
OCP\App::checkAppEnabled(Config::APP_NAME);

/* TODO: add a date to the export file */
//print_r($_POST);

$template = Util::cgiValue('Template', '');

$table = false;
switch ($template) {
case 'all-musicians':
  $table = new CAFEVDB\Musicians(false, false);
  $name  = 'musicians';
  break;
case 'brief-instrumentation':
  $table = new CAFEVDB\BriefInstrumentation(false);
  $name = Util::cgiValue('Project').'-brief';
  break;
case 'detailed-instrumentation':
  $table = new CAFEVDB\DetailedInstrumentation(false);
  $name = Util::cgiValue('Project').'-detailed';
  break;
}

if ($table) {
  $filename = strftime('%Y%m%d-%H%M%S').'-CAFEV-'.$name.'.html';
  $title    = 'CAFEV-'.$name.' from '.strftime('%x at time %X.');

  $css = file_get_contents(__DIR__.'/../../css/pme-table.css');

  header('Content-type: text/html; carset=utf-8;');
  header('Content-disposition: attachment;filename='.htmlspecialchars($filename).';');
  echo <<<__EOT__
<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML//EN">
<html>
  <head>
    <title>$title</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style type="text/css">
$css
    </style>
  </head>
  <body>
    <h4>$title</h4>
__EOT__;
  $table->navigation(false); // no buttons
  $table->display();
  $table->execute();
  echo <<<__EOT__
  </body>
</html>
__EOT__;
} else {
  header('Content-type: text/plain');
  header('Content-disposition: attachment;filename=debug.txt');

  print_r($_POST);
}

?>

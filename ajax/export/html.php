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
case 'instrument-insurance':
  $table = new CAFEVDB\InstrumentInsurance(false);
  $name = L::t('instrument insurances');
  break;
}

if ($table) {
  $filename = strftime('%Y%m%d-%H%M%S').'-CAFEV-'.$name.'.html';
  $title    = 'CAFEV-'.$name.' from '.strftime('%x at time %X.');

  $css = file_get_contents(__DIR__.'/../../css/pme-table.css');

/* Funny thing: the various spread-sheet applications might be able to
 * even import an HTML-table in a sensible way if we fake on the
 * file-type. Libre-/Openoffice for example opens HTML-files with the
 * "writer"-application but very happily converts the stuff to a
 * spread-sheet if we set the mime-type acordingly.
 */

  $mimetype = Util::cgiValue('mimetype','text/html');
  
  switch ($mimetype) {
  case 'text/html':
  default:
    header('Content-type: text/html; carset=utf-8;');
    header('Content-disposition: attachment;filename='.htmlspecialchars($filename).';');
    break;
  case 'application/spreadsheet':
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="'.$filename.'"');
    break;
  }
  header('Cache-Control: max-age=0');

  echo <<<__EOT__
<!DOCTYPE html>
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

  echo L::t('The export function for this table is not implemented, sorry.')."\n\n";
  print_r($_POST);
}

?>

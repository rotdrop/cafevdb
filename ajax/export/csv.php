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
  $name = strftime('%Y%m%d-%H%M%S').'-CAFEV-'.$name.'.csv';
  header('Content-type: text/csv');
  header('Content-disposition: attachment;filename='.htmlspecialchars($name));
  header('Cache-Control: max-age=0');

  $outstream = fopen("php://output",'w');

  $table->deactivate();
  $table->display(); // strange, but need be here
  $h2t = new \html2text();
  $table->csvExport($outstream, ';', '"', function ($cell) use ($h2t) {
      $h2t->set_html($cell);
      $text = $h2t->get_text();
      // If a hyper-link is just an email, then simply return the email
      $email = preg_split('/[ ,]/',$text);
      $email = $email[0];
      if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $h2t->omit_links = true;
        $h2t->set_html($cell);
        $text = $h2t->get_text();
        $h2t->omit_links = false;
      }
      $text = trim($text);
      // filter out dummy dates, I really should clean up on the data-base level
      if ($text == '01.01.1970') {
        $text = '';
      }
      return $text;
    });

  fclose($outstream);
} else {
  header('Content-type: text/plain');
  header('Content-disposition: attachment;filename=debug.txt');

  print_r($_POST);
}

?>

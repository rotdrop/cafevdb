<?php

//global $debug_query;

// $debug_query = true;

echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
		"http://www.w3.org/TR/html4/loose.dtd">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>CAFEV FillBesetzungZahl</title>
</head>
<body>
';

include('config.php.inc');
include('functions.php.inc');

// Fetch the actual list of instruments, we will need it anyway
$handle = CAFEVmyconnect($opts);

$Instruments = sql_multikeys('Musiker', 'Instrumente', $handle);

// Current mysql versions do not seem to support "IF NOT EXISTS", so
// we simply try to do our best and add one column in each request.

foreach ($Instruments as $instr) {
  $query = 'ALTER TABLE `BesetzungsZahl` ADD COLUMN `'.$instr.'` TINYINT NOT NULL DEFAULT '0'";
  $result = CAFEVmyquery($query, $handle); // simply ignore any error
}

CAFEVmyclose($handle);

echo '</body></html>';

?>

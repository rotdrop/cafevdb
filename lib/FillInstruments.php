<?php

//global $debug_query;

// $debug_query = true;

echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
		"http://www.w3.org/TR/html4/loose.dtd">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>CAFEV FillInstruments</title>
</head>
<body>
';

include('config.php.inc');
include('functions.php.inc');
include('Instruments.php');

$handle = CAFEVDB_mySQL::connect($opts);

sanitizeInstrumentsTable($handle);

CAFEVDB_mySQL::close($handle);

echo '</body></html>';

?>


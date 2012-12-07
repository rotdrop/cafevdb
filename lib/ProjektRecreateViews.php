<?php

global $debug_query;

$debug_query = true;

echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
		"http://www.w3.org/TR/html4/loose.dtd">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>CAFEV RecreateProjektView</title>
</head>
<body>
';

include('ProjektFunktionen.php');
include('config.php.inc');

// Connect to the data-base

$handle = CAFEVDB_mySQL::connect($opts);

// Fetch the list of projects
$query = 'SELECT `Id` FROM `Projekte` WHERE 1';
$result = CAFEVDB_mySQL::query($query, $handle);

while ($line = CAFEVDB_mySQL::fetch($result)) {
  $ProjektId = $line['Id'];

  print '<H4>Recreating view for project '.$ProjektId.'</H4><BR/>';
  CAFEVerror("Before Create ".$ProjektId, false);

  ProjektCreateView($ProjektId, false, $handle);

  CAFEVerror("After Create ".$ProjektId, false);
}

CAFEVDB_mySQL::close($handle);

echo '</body></html>';

?>



<?php

// $newvals contains the new values

// Fetch the current list of instruments
$instruments = CAFEVDB\mySQL::multiKeys('Musiker', 'Instrumente', $this->dbh);
array_push($instruments, $newvals['Instrument']);
sort($instruments, SORT_FLAG_CASE|SORT_STRING);

// Now inject the new chain of instruments into Musiker table
$sqlquery = "ALTER TABLE `Musiker` CHANGE `Instrumente`
 `Instrumente` SET('" . implode("','", $instruments) . "')";
if (!$this->myquery($sqlquery)) {
  CAFEVDB\Util::error("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

// Now do the same with the Besetzungen-table
$instruments = CAFEVDB\mySQL::multiKeys('Besetzungen', 'Instrument', $this->dbh);
array_push($instruments, $newvals['Instrument']);
sort($instruments, SORT_FLAG_CASE|SORT_STRING);

$sqlquery = "ALTER TABLE `Besetzungen` CHANGE `Instrument`
 `Instrument` ENUM('" . implode("','", $instruments) . "')";
if (!$this->myquery($sqlquery)) {
  CAFEVDB\Util::error("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

// Now do the same with the Projekte-table
$instruments = CAFEVDB\mySQL::multiKeys('Projekte', 'Besetzung', $this->dbh);
array_push($instruments, $newvals['Instrument']);
sort($instruments, SORT_FLAG_CASE|SORT_STRING);

$sqlquery = "ALTER TABLE `Projekte` CHANGE `Besetzung`
 `Besetzung` SET('" . implode("','", $instruments) . "') COMMENT 'Benötigte Instrumente'";
if (!$this->myquery($sqlquery)) {
  CAFEVDB\Util::error("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

// Now insert also another column into BesetzungsZahlen
$sqlquery = "ALTER TABLE `BesetzungsZahlen` ADD COLUMN `".$newvals['Instrument']."` TINYINT NOT NULL DEFAULT '0'";
if (!$this->myquery($sqlquery)) {
  CAFEVDB\Util::error("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

return true;

?>

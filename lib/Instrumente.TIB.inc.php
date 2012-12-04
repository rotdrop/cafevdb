<?php

// $newvals contains the new values

// Fetch the current list of instruments
$Instrumente = sql_multikeys('Musiker', 'Instrumente', $this->dbh);
array_push($Instrumente, $newvals['Instrument']);
sort($Instrumente, SORT_FLAG_CASE|SORT_STRING);

// Now inject the new chain of instruments into Musiker table
$sqlquery = "ALTER TABLE `Musiker` CHANGE `Instrumente`
 `Instrumente` SET('" . implode("','", $Instrumente) . "')";
if (!$this->myquery($sqlquery)) {
  CAFEVerror("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

// Now do the same with the Besetzungen-table
$Instrumente = sql_multikeys('Besetzungen', 'Instrument', $this->dbh);
array_push($Instrumente, $newvals['Instrument']);
sort($Instrumente, SORT_FLAG_CASE|SORT_STRING);

$sqlquery = "ALTER TABLE `Besetzungen` CHANGE `Instrument`
 `Instrument` ENUM('" . implode("','", $Instrumente) . "')";
if (!$this->myquery($sqlquery)) {
  CAFEVerror("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

// Now do the same with the Projekte-table
$Instrumente = sql_multikeys('Projekte', 'Besetzung', $this->dbh);
array_push($Instrumente, $newvals['Instrument']);
sort($Instrumente, SORT_FLAG_CASE|SORT_STRING);

$sqlquery = "ALTER TABLE `Projekte` CHANGE `Besetzung`
 `Besetzung` SET('" . implode("','", $Instrumente) . "') COMMENT 'Benötigte Instrumente'";
if (!$this->myquery($sqlquery)) {
  CAFEVerror("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

// Now insert also another column into BesetzungsZahlen
$sqlquery = "ALTER TABLE `BesetzungsZahlen` ADD COLUMN `".$newvals['Instrument']."` TINYINT NOT NULL DEFAULT '0'";
if (!$this->myquery($sqlquery)) {
  CAFEVerror("Could not execute the query\n".$sqlquery."\nSQL-Error: ".mysql_error(), true);
}

return true;

?>

<?php

// $newvals contains the new values

// Create the view and make sure we have enough extra fields in the
// Besetzungen table
ProjektCreateView($this->rec, $newvals['Name'], $this->dbh);

// Add also a new line to the BesetzungsZahlen table
$sqlquery = 'INSERT IGNORE INTO `BesetzungsZahlen` (`ProjektId`) VALUES ('.$this->rec.')';
$this->myquery($sqlquery) or die ("Could not execute the query. " . mysql_error());

return true;

?>

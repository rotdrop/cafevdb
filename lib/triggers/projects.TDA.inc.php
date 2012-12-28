<?php

\CAFEVDB\Util::authorized();

// $newvals contains the new values

$sqlquery = "DROP VIEW IF EXISTS Lago2012View";
$this->myquery($sqlquery) or die ("Could not execute the query. " . mysql_error());

// This was the view. We should also remove all stuff from the Besetzungen list.
$sqlquery = "DELETE FROM Besetzungen WHERE ProjektId = $this->rec";
$this->myquery($sqlquery) or die ("Could not execute the query. " . mysql_error());


return true;

?>

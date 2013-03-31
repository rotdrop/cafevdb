<?php

use CAFEVDB\Util;
use CAFEVDB\Projects;

Util::authorized();

// $newvals contains the new values

$projectName = Projects::fetchName($this->rec, $this->dbh);

$sqlquery = 'DROP VIEW IF EXISTS `'.$projectName.'View`';
$this->myquery($sqlquery) or die ("Could not execute the query. " . mysql_error());

// This was the view. We should also remove all stuff from the Besetzungen list.
$sqlquery = "DELETE FROM Besetzungen WHERE ProjektId = $this->rec";
$this->myquery($sqlquery) or die ("Could not execute the query. " . mysql_error());


return true;

?>

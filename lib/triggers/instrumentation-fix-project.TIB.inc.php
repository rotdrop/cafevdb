<?php

$project = CAFEVDB_Instrumentation::$project;
$projectId = CAFEVDB_Instrumentation::$projectId;
$musicianId = CAFEVDB_Instrumentation::$musicianId;

// We check here whether the change of the instrument or player is in
// some sense consistent with the Musiker table. We know that only
// MusikerId and instrument can change

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed 

// For an unknown reason the project Id is zero ....

$newvals['ProjektId'] = $projectId;

return true;

?>

<?php

\CAFEVDB\Util::authorized();

$project = CAFEVDB\Util::cgiValue('Project');
$projectId =  CAFEVDB\Util::cgiValue('ProjectId');
$musicianId = CAFEVDB\Util::cgiValue('MusicianId');

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

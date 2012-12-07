<?php

global $ProjektId;
global $Projekt;
global $MusikerId;

// We check here whether the change of the instrument or player is in
// some sense consistent with the Musiker table. We know that only
// MusikerId and instrument can change

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed 

// For an unknown reason the project Id is zero ....

$newvals['ProjektId'] = $ProjektId;

return true;

?>

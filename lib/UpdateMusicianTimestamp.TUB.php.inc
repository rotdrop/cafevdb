<?php

/* This should be preceeded by RemoveUnchanged.TUB.php.inc */

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed 

//print_r($changed);

$key = 'Aktualisiert';
$changed[] = $key;
$newvals[$key] = date('Y-m-d H:i:s');

return true;

?>

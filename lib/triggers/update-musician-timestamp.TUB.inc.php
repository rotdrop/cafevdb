<?php

\CAFEVDB\Util::authorized();

/* This should be preceeded by RemoveUnchanged.TUB.php.inc */

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed 

//print_r($changed);

if (count($changed) != 0) {
  $key = 'Aktualisiert';
  $changed[] = $key;
  $newvals[$key] = date('Y-m-d H:i:s');
}

return true;

?>

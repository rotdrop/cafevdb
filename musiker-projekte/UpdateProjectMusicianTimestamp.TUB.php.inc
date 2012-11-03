<?php

/* This should be preceeded by RemoveUnchanged.TUB.php.inc */

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed 

//print_r($changed);

// Here we need to be a little bit more careful: the view is composed
// of two tables, but we only want to update the time-stamp if the
// Musiker-table is modified.

$stampkeys = array('AlleInstrumente',
                   'Name','Vorname',
                   'Email',
                   'Telefon','Telefon2',
                   'Strasse','Postleitzahl','Start','Land',
                   'Sprachpräferenz','Geburtstag','Status',
                   'Bemerkung');

if (count(array_intersect($changed,$stampkeys)) != 0) {
  $key = 'Aktualisiert';
  $changed[] = $key;
  $newvals[$key] = date('Y-m-d H:i:s');
}

return true;

?>

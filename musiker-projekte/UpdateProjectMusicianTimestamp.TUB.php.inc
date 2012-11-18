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
                   'Strasse','Postleitzahl','Stadt','Land',
                   'Sprachpräferenz','Geburtstag','Status',
                   'Bemerkung');

$musikerkeys = array_intersect($changed, $stampkeys);
$otherkeys = array_diff($changed, $stampkeys);

if (count($otherkeys) != 0 && count($musikerkeys) != 0) {
  echo "<HR/><H4>";
  echo "Unfortunately, it is not possible to change<BR/>";
  foreach ($musikerkeys as $field) {
    echo '<EM style="color:red">'.$field.'</EM>&nbsp;';
  }
  echo "<BR/>and<BR/>";
  foreach ($otherkeys as $field) {
    echo '<EM style="color:red">'.$field.'</EM>&nbsp;';
  }
  echo "<BR/>at the same time. Sorry for the inconvenience.</H4><HR/>\n";
  return false;
}

if (count($musikerkeys) != 0) {
  $key = 'Aktualisiert';
  $changed[] = $key;
  $newvals[$key] = date('Y-m-d H:i:s');
}

return true;

?>

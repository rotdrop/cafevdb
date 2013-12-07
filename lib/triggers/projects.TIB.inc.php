<?php

// TIB == "trigger insert before"
//
// This code executes before the project is inserted in to the
// database. Tasks done here:
//
// Sanitize the project name. We only support names without
// spaces. And the project year must not be unset.

\CAFEVDB\Util::authorized();

// $newvals contains the new values


// Insert suitable checks here and prevent insertion of bogus data into the DB.
// This comes to gether with a couple of pre-form-submit check with JavaScript and Ajax calls.

if (!isset($newvals['Jahr'])) {
  return false;
} else {
  return true;  
}  

?>

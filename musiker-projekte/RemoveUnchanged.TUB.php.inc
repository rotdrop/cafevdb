<?php

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed 

//print_r($changed);

// Force default from data-base
foreach ($newvals as $key => $value) {
  if (array_search($key, $changed) === false) {
    unset($newvals["$key"]);
  }
}

if (count($newvals) == 0) {
  return false;
} else {
  return true;
}

?>

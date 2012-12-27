<?php

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed   -- changed fields

// $newvals contains the new values

if (array_search('Name', $changed) === false) {
  return;
}

// Simply recreate the view, update the extra tables etc.
CAFEVDB\Projects::createView($this->rec, $newvals['Name'], $this->dbh);

// Now that we link events to projects using their short name as
// category, we also need to update all linke events in case the
// short-name has changed.
$events = CAFEVDB\Events::events($this->rec, $this->dbh);

// Hack: close the DB connection and re-open again. Somehow OwnCloud
// damages the stuff ...
$this->sql_disconnect();

foreach ($events as $event) {
  // Last parameter "true" means to also perform string substitution
  // in the summary field of the event.
  CAFEVDB\Events::replaceCategory($event, $oldvals['Name'], $newvals['Name'], true);
}

$this->sql_connect();

return true;

?>

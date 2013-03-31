<?php

\CAFEVDB\Util::authorized();

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed   -- changed fields

// $newvals contains the new values

// Simply recreate the view, update the extra tables etc.
CAFEVDB\Projects::createView($this->rec, $newvals['Name'], $this->dbh);

if (array_search('Name', $changed) === false) {
  return;
}

// Drop the old view, which still exists with the old name

$sqlquery = 'DROP VIEW IF EXISTS `'.$oldvals['Name'].'View`';
$this->myquery($sqlquery) or die ("Could not execute the query. " . mysql_error());

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

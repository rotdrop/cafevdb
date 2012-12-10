<?php

// $newvals contains the new values

// Simply recreate the view, update the extra tables etc.
CAFEVDB\Projects::createView($this->rec, $newvals['Name'], $this->dbh);

return true;

?>

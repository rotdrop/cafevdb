<?php

// $newvals contains the new values

// Simply recreate the view, update the extra tables etc.
ProjektCreateView($this->rec, $newvals['Name'], $this->dbh);

return true;

?>

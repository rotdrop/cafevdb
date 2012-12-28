<?php

\CAFEVDB\Util::authorized();

// $newvals contains the new values

//print_r($changed);

if (array_search('Instrument',$changed) === false) {
  return true;
} else {
  echo '<H4>Attempt to Change Instrument\'s Name Denied!!!! Never do that.<BR/>';
  return false;
}

?>

<?php

global $ProjektId;
global $Projekt;
global $MusikerId;

// We check here whether the change of the instrument or player is in
// some sense consistent with the Musiker table. We know that only
// MusikerId and instrument can change

// $this      -- pme object
// $newvals   -- contains the new values
// $this->rec -- primary key
// $oldvals   -- old values
// $changed 

/* echo '<PRE> */
/* '; */
/* print_r($newvals); */
/* print_r($oldvals); */
/* print_r($changed); */
/* echo "Rec: ".$this->rec."\n"; */
/* echo '</PRE> */
/* '; */

if (!isset($newvals['Instrument'])) {
  // No need to check.
  return true;
}

if ($MusikerId < 0) {
  if (isset($newvals['MusikerId'])) {
    $MusikerId = $newvals['MusikerId'];
  } else {
    // otherwise it must be this->rec
    $MusikerId = $this->rec;
  }
} else if ($MusikerId != $newvals['MusikerId']) {
  echo "Data inconsistency: Ids do not match (" . $newvals['MusikerId'] . " != $MusikerId)";
  return false;
}

// Fetch the list of instruments from the Musiker data-base

$musquery = "SELECT `Instrumente`,`Vorname`,`Name` FROM Musiker WHERE `Id` = $MusikerId";

$musres = $this->myquery($musquery) or die ("Could not execute the query. " . mysql_error());
$musnumrows = mysql_num_rows($musres);

if ($musnumrows != 1) {
  echo "Data inconsisteny, " . $MusikerId . " is not a unique Id\n";
  return false;
}

$musrow = $this->sql_fetch($musres);
//$instruments = explode(',',$musrow['Instrumente']);

$musname = $musrow['Vorname'] . " " . $musrow['Name'];
$instrumente = $musrow['Instrumente'];
$instrument  = $newvals['Instrument'];

if (!strstr($instrumente, $instrument)) {
  echo "<HR/><H4>";
  echo "Instrument not known by $musname, correct that first! ";
  echo "$musname only plays " . $musrow['Instrumente'] . "!";
  echo "</H4><HR/>\n";
  echo "<H4>Click on the following button to enforce your decision:</H4>
<form name=\"CAFEV_form_besetzung\" method=\"post\" action=\"ProjektBesetzung.php\">
  <input type=\"submit\" name=\"\" value=\"Really Change $musname's instrument!!!\">
  <input type=\"hidden\" name=\"Action\" value=\"ChangeOneMusician\">
  <input type=\"hidden\" name=\"Projekt\" value=\"$Projekt\" />
  <input type=\"hidden\" name=\"ProjektId\" value=\"$ProjektId\" />
  <input type=\"hidden\" name=\"MusikerId\" value=\"$MusikerId\" />
  <input type=\"hidden\" name=\"ForcedInstrument\" value=\"$instrument\" />
</form>
<p>
This will also add \"$instrument\" to $musname's list of known instruments. Unfortunately, all your
other changes will be discarded. You may want to try the \"Back\"-Button of your browser.
<HR/>
<p>
";
  return false;
}

/* $sqlquery = "CREATE OR REPLACE VIEW `" . $newvals["Name"] . "View` AS */
/*  SELECT */
/*  `Musiker`.`Id`,`Instrument`, `Name`, `Vorname`, */
/*  `Email`, `Telefon`, `Telefon2`, `Strasse`, `Postleitzahl`, `Stadt`, `Land`, */
/*  `Geburtstag`, `Status`, `Bemerkung` FROM `Musiker` JOIN `Besetzungen` */
/*   ON `Musiker`.`Id` = MusikerId AND " . $this->rec . "= `ProjektId`"; */

/* //echo $sqlquery; */

/* $this->myquery($sqlquery) or die ("Could not execute the query. " . mysql_error()); */

return true;

?>

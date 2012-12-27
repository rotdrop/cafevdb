<?php

use CAFEVDB\L;

$project = CAFEVDB\Util::cgiValue('Project');
$projectId =  CAFEVDB\Util::cgiValue('ProjectId');
$musicianId = CAFEVDB\Util::cgiValue('MusicianId');

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

if ($musicianId < 0) {
  if (isset($newvals['MusikerId'])) {
    $musicianId = $newvals['MusikerId'];
  } else {
    // otherwise it must be this->rec
    $musicianId = $this->rec;
  }
} else if ($musicianId != $newvals['MusikerId']) {
  echo "Data inconsistency: Ids do not match (" . $newvals['MusikerId'] . " != $musicianId)";
  return false;
}

// Fetch the list of instruments from the Musiker data-base

$musquery = "SELECT `Instrumente`,`Vorname`,`Name` FROM Musiker WHERE `Id` = $musicianId";

$musres = $this->myquery($musquery) or die ("Could not execute the query. " . mysql_error());
$musnumrows = mysql_num_rows($musres);

if ($musnumrows != 1) {
  echo "Data inconsisteny, " . $musicianId . " is not a unique Id\n";
  return false;
}

$musrow = $this->sql_fetch($musres);
//$instruments = explode(',',$musrow['Instrumente']);

$musname = $musrow['Vorname'] . " " . $musrow['Name'];
$instruments = $musrow['Instrumente'];
$instrument  = $newvals['Instrument'];

if (!strstr($instruments, $instrument)) {
  $text = strval(L::t('Instrument not known by')." $musname, ".
                 L::t('correct that first!').
                 " $musname ".L::t('only plays')." $instruments!");
  echo <<<__EOT__
<div class="cafevdb-pme-header-box" style="height:18ex">
  <div class="cafevdb-pme-header">
  <HR/><H4>
  $text;
  </H4><HR/>
  <H4>Click on the following button to enforce your decision:
<form name="CAFEV_form_besetzung" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="Really Change $musname's instrument!!!">
  <input type="hidden" name="Template" value="change-one-musician">
  <input type="hidden" name="Project" value="$project" />
  <input type="hidden" name="ProjectId" value="$projectId" />
  <input type="hidden" name="MusicianId" value="$musicianId" />
  <input type="hidden" name="ForcedInstrument" value="$instrument" />
</form>
</H4>
<p>
This will also add "$instrument" to $musname's list of known instruments. Unfortunately, all your
other changes will be discarded. You may want to try the "Back"-Button of your browser.
<HR/>
<p>
  </div>
</div>
__EOT__;

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

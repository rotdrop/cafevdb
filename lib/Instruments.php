<?php

require_once('functions.php.inc');

// Sort the given list of instruments according to orchestral ordering
// as defined in the Instrumente table.
function sortInstrumentsOrchestral($list, $handle)
{
  $query = 'SELECT `Instrument`,`Sortierung` FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
  $result = CAFEVmyquery($query, $handle);
  
  $final = array();
  while ($line = CAFEVmyfetch($result)) {
    $tblInst = $line['Instrument'];
    if (array_search($tblInst, $list) !== false) {
      $final[] = $tblInst;
    }
  }

  return $final;
}

// Fetch the instruments required for a specific project
function fetchProjectInstruments($ProjektId, $handle) {

  $query = 'SELECT `Besetzung` FROM `Projekte` WHERE `Id` = '.$ProjektId;
  $result = CAFEVmyquery($query);

  // Ok there should be only one row
  if (!($line = CAFEVmyfetch($result))) {
    CAFEVerror("Could not fetch instruments for project", true);
  }
  $ProjInsts = explode(',',$line['Besetzung']);

  // Now sort it in "natural" order
  return sortInstrumentsOrchestral($ProjInsts, $handle);
}

// Fetch the project-instruments of the project musicians, possibly to
// do some sanity checks with the project's instrumentation, or simply
// to add all instruments to the projects instrumentation list.
function fetchProjectMusiciansInstruments($ProjektId, $handle)
{
  $query = 'SELECT DISTINCT `Instrument` FROM `Besetzungen` WHERE `ProjektId` = '.$ProjektId;
  $result = CAFEVmyquery($query);
  
  $instruments = array();
  while ($line = CAFEVmyfetch($result)) {
    $instruments[] = $line['Instrument'];
  }

  // Now sort it in "natural" order
  return sortInstrumentsOrchestral($instruments, $handle);
}

// Fetch all instruments of the musicians subscribed to the project
// and add them to the instrumentation. If $replace is true, then
// remove the old instrumentation, otherwise the new instrumentation
// is the union of the old instrumentation and the instruments
// actually subscribed to the project.
function updateProjectInstrumentationFromMusicians($ProjektId, $handle, $replace = false)
{
  $musinst = fetchProjectMusiciansInstruments($ProjektId, $handle);
  if ($replace) {
    $prjinst = $musinst;
  } else {
    $prjinst = fetchProjectInstruments($ProjektId, $handle);
  }
  $prjinst = array_unique(array_merge($musinst, $prjinst));
  $prjinst = sortInstrumentsOrchestral($prjinst, $handle);

  $query = "UPDATE `Projekte` SET `Besetzung`='".implode(',',$prjinst)."' WHERE `Id` = $ProjektId";
  CAFEVmyquery($query, $handle);
  //CAFEVerror($query, false);
  
  return $prjinst;
}

// Fetch the instruments and sort them according to Instruments.Sortierung
function fetchInstruments($handle) {

  $Instruments = sql_multikeys('Musiker', 'Instrumente', $handle);

  $query = 'SELECT `Instrument`,`Sortierung` FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
  $result = CAFEVmyquery($query, $handle);
  
  $final = array();
  while ($line = CAFEVmyfetch($result)) {
    //CAFEVerror("huh".$line['Instrument'],false);
    $tblInst = $line['Instrument'];
    if (array_search($tblInst, $Instruments) === false) {
      CAFEVerror('"'.$tblInst.'" not found in '.implode(',',$Instruments), true);
    }
    array_push($final, $tblInst);
  }

  return $final;
}

// Check for consistency
function checkInstruments($handle) {

  $InstrumentsSet  = sql_multikeys('Musiker', 'Instrumente', $handle);
  $InstrumentsEnum = sql_multikeys('Besetzungen', 'Instrument', $handle);

  
  sort($InstrumentsSet);
  sort($InstrumentsEnum);

  $cnt1 = count($InstrumentsSet);
  $cnt2 = count($InstrumentsEnum);

  if ($cnt1 != $cnt2) {
    echo "<P><HR/>
<H4>Anzahl der Instrumente in \"Musiker\" und \"Besetzungen\" stimmen nicht &uuml;berein!</H4>
<HR/><P>
";
  }

  $cntmax = max($cnt1,$cnt2);
  $cntmin = min($cnt1,$cnt2);

  for ($i = 0; $i < $cntmin; $i++) {
    if ($InstrumentsSet[$i] != $InstrumentsEnum[$i]) {
      echo "<P><HR/>
<H4>Instrumente in \"Musiker\" und \"Besetzungen\" stimmen nicht &uuml;berein: \""
        . $InstrumentsSet[$i] . "\" != \"" . $InstrumentsEnum[$i] . "\"!</H4>
<HR/><P>
";
    }
  }
  $excess = $cnt1 < $cnt2 ? $InstrumentsEnum : $InstrumentsSet;
  echo "<P><HR/>
<H4>&Uuml;bersch&uuml;ssige Instrumente: ";
  for (; $i < $cntmax; $i++) {
    echo $excess[$i] . " ";
  }
  echo "<HR/><P>\n";
}

// Make sure the Instrumente table as all instruments used in the Musiker
// table. Delete everything else.
function sanitizeInstrumentsTable($handle) {

  $Instrumente = sql_multikeys('Musiker', 'Instrumente', $handle);

  foreach ($Instrumente as $value) {
    $query = "INSERT IGNORE INTO `Instrumente` (`Instrument`) VALUES ('$value')";
    $result = CAFEVmyquery($query, $handle);
  }

  // Now the table contains at least all instruments, now remove excess elements.


  // Build SQL Query  
  $query = "SELECT `Instrument` FROM `Instrumente` WHERE 1";

  // Fetch the result or die
  $result = CAFEVmyquery($query, $query);

  $dropList = array();
  while ($line = CAFEVmyfetch($result)) {
    $tblInst = $line['Instrument'];
    if (array_search($tblInst, $Instrumente) === false) {
      $dropList[$tblInst] = true;
    }
  }

  foreach ($dropList as $key => $value) {
    $query = "DELETE FROM `Instrumente` WHERE `Instrument` = '$key'";
    $result = CAFEVmyquery($query);
  }
}


?>

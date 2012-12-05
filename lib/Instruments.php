<?php

class CAFEV_DB_Instruments
{

  // Sort the given list of instruments according to orchestral ordering
  // as defined in the Instrumente table.
  public static function sortInstrumentsOrchestral($list, $handle)
  {
    $query = 'SELECT `Instrument`,`Sortierung` FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
    $result = CAFEV_DB_Util::myquery($query, $handle);
  
    $final = array();
    while ($line = CAFEV_DB_Util::myfetch($result)) {
      $tblInst = $line['Instrument'];
      if (array_search($tblInst, $list) !== false) {
        $final[] = $tblInst;
      }
    }

    return $final;
  }

  // Fetch the instruments required for a specific project
  public static function fetchProjectInstruments($ProjektId, $handle) {

    $query = 'SELECT `Besetzung` FROM `Projekte` WHERE `Id` = '.$ProjektId;
    $result = CAFEV_DB_Util::myquery($query);

    // Ok there should be only one row
    if (!($line = CAFEV_DB_Util::myfetch($result))) {
      CAFEVerror("Could not fetch instruments for project", true);
    }
    $ProjInsts = explode(',',$line['Besetzung']);

    // Now sort it in "natural" order
    return sortInstrumentsOrchestral($ProjInsts, $handle);
  }

  // Fetch the project-instruments of the project musicians, possibly to
  // do some sanity checks with the project's instrumentation, or simply
  // to add all instruments to the projects instrumentation list.
  public static function fetchProjectMusiciansInstruments($ProjektId, $handle)
  {
    $query = 'SELECT DISTINCT `Instrument` FROM `Besetzungen` WHERE `ProjektId` = '.$ProjektId;
    $result = CAFEV_DB_Util::myquery($query);
  
    $instruments = array();
    while ($line = CAFEV_DB_Util::myfetch($result)) {
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
  public static function updateProjectInstrumentationFromMusicians($ProjektId, $handle, $replace = false)
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
    CAFEV_DB_Util::myquery($query, $handle);
    //CAFEVerror($query, false);
  
    return $prjinst;
  }

  // Fetch the instruments and sort them according to Instruments.Sortierung
  public static function fetchInstruments($handle) {

    $Instruments = CAFEV_DB_Util::sqlMultikeys('Musiker', 'Instrumente', $handle);

    $query = 'SELECT `Instrument`,`Sortierung` FROM `Instrumente` WHERE  1 ORDER BY `Sortierung` ASC';
    $result = CAFEV_DB_Util::myquery($query, $handle);
  
    $final = array();
    while ($line = CAFEV_DB_Util::myfetch($result)) {
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
  public static function checkInstruments($handle) {

    $InstrumentsSet  = CAFEV_DB_Util::sqlMultikeys('Musiker', 'Instrumente', $handle);
    $InstrumentsEnum = CAFEV_DB_Util::sqlMultikeys('Besetzungen', 'Instrument', $handle);

  
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
  public static function sanitizeInstrumentsTable($handle) {

    $Instrumente = CAFEV_DB_Util::sqlMultikeys('Musiker', 'Instrumente', $handle);

    foreach ($Instrumente as $value) {
      $query = "INSERT IGNORE INTO `Instrumente` (`Instrument`) VALUES ('$value')";
      $result = CAFEV_DB_Util::myquery($query, $handle);
    }

    // Now the table contains at least all instruments, now remove excess elements.


    // Build SQL Query  
    $query = "SELECT `Instrument` FROM `Instrumente` WHERE 1";

    // Fetch the result or die
    $result = CAFEV_DB_Util::myquery($query, $query);

    $dropList = array();
    while ($line = CAFEV_DB_Util::myfetch($result)) {
      $tblInst = $line['Instrument'];
      if (array_search($tblInst, $Instrumente) === false) {
        $dropList[$tblInst] = true;
      }
    }

    foreach ($dropList as $key => $value) {
      $query = "DELETE FROM `Instrumente` WHERE `Instrument` = '$key'";
      $result = CAFEV_DB_Util::myquery($query);
    }
  }

};

?>

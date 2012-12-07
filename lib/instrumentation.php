<?php

/* require_once("functions.php.inc"); */
/* require_once("ProjektFunktionen.php"); */
/* require_once('Instruments.php'); */
/* include('config.php.inc'); */

class CAFEVDB_Instrumentation
{
  static $action;
  static $subaction;
  static $MusikerId;
  static $ProjektId;
  static $Projekt;
  static $RecordsPerPage;

  static public function display()
  {
    CAFEVDB_Config::init();

    //CAFEVDB_Config::$debug_query = true;

    if (CAFEVDB_Config::$debug_query) {
      echo "<PRE>\n";
      print_r($_POST);
      print_r($_GET);
      echo "</PRE>\n";
    }

    // Not needed globally, AFAIK
    /* global $Projekt; */
    /* global $ProjektId; */
    /* global $CAFEV_action; */
    /* global $MusikerId; */

    $opts = CAFEVDB_Config::$pmeopts;
    foreach (CAFEVDB_Config::$cgiVars as $key => $value) {
      $opts['cgi']['persist']["$key"] = $value = CAFEVDB_Util::cgiValue("$key");
      //echo "$key =&gt; $value <BR/>";
    }

    $action = $opts['cgi']['persist']['Action'];
    $subaction = $opts['cgi']['persist']['Subaction'];
    $MusikerId = $opts['cgi']['persist']['MusikerId'];
    $ProjektId = $opts['cgi']['persist']['ProjektId'];
    $Projekt = $opts['cgi']['persist']['Projekt'];;
    $RecordsPerPage = $opts['cgi']['persist']['RecordsPerPage'];

    // Fetch some data we probably will need anyway

    $handle = CAFEVDB_mySQL::connect($opts);

    // List of instruments
    $Instrumente = CAFEVDB_Instruments::fetch($handle);
    $InstrumentenFamilie = CAFEVDB_mySQL::multiKeys('Instrumente', 'Familie', $handle);

    // Fetch project specific user fields
    if ($ProjektId >= 0) {
      //  echo "Id: $ProjektId <BR/>";
      $UserExtraFields = CAFEVDB_Projects::extraFields($ProjektId, $handle);
    }

    /* echo "<PRE>\n"; */
    /* print_r($Instrumente); */
    /* /\*print_r($Instrumente2);*\/ */
    /* echo "</PRE>\n"; */

    /* checkInstruments($handle); */
    /* sanitizeInstrumentsTable($handle); */

    CAFEVDB_mySQL::close($handle);

    /* if ($CAFEV_action == "DisplayProjectMusicians") { */

    /*   include('DisplayProjectMusicians.php'); */

    /* } else */
    if ($action == "ShortDisplayProjectMusicians") {

      new CAFEVDB_ShortDisplayProjectMusicians($opts);

    /* } else if ($CAFEV_action == "DisplayProjectsNeeds") { */

    /*   include('DisplayProjectNeeds.php'); */

    /* } else if ($CAFEV_action == "TODO") { */

    /*   include('TODO.php'); */

    /* } else if ($CAFEV_action == "AddMusicians") { */
    /*   // !display, i.e Add. */

    /*   include('AddMusicians.php'); */

    /* } else if ($CAFEV_action == "DisplayMusicians") { */

    /*   include('DisplayMusicians.php'); */

    /* } else if ($CAFEV_action == "AddOneMusician" || $CAFEV_action == "ChangeOneMusician") {   */

    /*   include('AddChangeOneMusician.php'); */

    /* } else if ($CAFEV_action == "AddInstruments") { */

    /*   include('AddInstruments.php'); */

    }
  }

}; // class CAFEVDB_Instrumentation

?>

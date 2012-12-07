<?php

/* require_once("functions.php.inc"); */
/* require_once("ProjektFunktionen.php"); */
/* require_once('Instruments.php'); */
/* include('config.php.inc'); */

class CAFEVDB_Instrumentation
{
  public static $action;
  public static $subAction;
  public static $musicianId;
  public static $projectId;
  public static $project;
  public static $recordsPerPage;
  public static $userExtraFields;
  public static $instruments;
  public static $instrumentFamilies;

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

    $opts = CAFEVDB_Config::$pmeopts;
    global $HTTP_SERVER_VARS;
    $opts['page_name'] = $HTTP_SERVER_VARS['PHP_SELF'].'?app=cafevdb'.'&Template=instrumentation';
    foreach (CAFEVDB_Config::$cgiVars as $key => $value) {
      $opts['cgi']['persist']["$key"] = $value = CAFEVDB_Util::cgiValue("$key");
      // echo "$key =&gt; $value <BR/>";
    }

    self::$action = $opts['cgi']['persist']['Action'];
    self::$subAction = $opts['cgi']['persist']['SubAction'];
    self::$musicianId = $opts['cgi']['persist']['MusicianId'];
    self::$projectId = $opts['cgi']['persist']['ProjectId'];
    self::$project = $opts['cgi']['persist']['Project'];;
    self::$recordsPerPage = $opts['cgi']['persist']['RecordsPerPage'];

    // Fetch some data we probably will need anyway

    $handle = CAFEVDB_mySQL::connect($opts);

    // List of instruments
    self::$instruments = CAFEVDB_Instruments::fetch($handle);
    $instrumentFamilies = CAFEVDB_mySQL::multiKeys('Instrumente', 'Familie', $handle);

    // Fetch project specific user fields
    if (self::$projectId >= 0) {
      //  echo "Id: self::$projectId <BR/>";
      self::$userExtraFields = CAFEVDB_Projects::extraFields(self::$projectId, $handle);
    }

    /* echo "<PRE>\n"; */
    /* print_r(self::$instruments); */
    /* /\*print_r(self::$instruments);*\/ */
    /* echo "</PRE>\n"; */

    /* checkInstruments($handle); */
    /* sanitizeInstrumentsTable($handle); */

    CAFEVDB_mySQL::close($handle);

    switch (self::$action) {
    case "DetailedInstrumentation": {
      CAFEVDB_DetailedInstrumentation::display($opts);
      break;
    }
    case "BriefInstrumentation": {
      CAFEVDB_BriefInstrumentation::display($opts);
      break;
    }
    case "TODO": {
      // ... the TODO-table actually has nothing to do with the
      // instrumentation.
      break;
    }
    case "AddMusicians": {
      CAFEVDB_Musicians::display($opts, true);
      break;
    }
    case "DisplayAllMusicians": {
      CAFEVDB_Musicians::display($opts, false);
      break;
    }
    case "AddOneMusician":
    case "ChangeOneMusician": {
      CAFEVDB_Musicians::displayAddChangeOne($opts);
      break;
    }
    case "AddInstruments": {
      CAFEVDB_Instruments::display($opts);
      break;
    }
    default: {
      // should emit an error here ...
    }
    }
  }
}; // class CAFEVDB_Instrumentation

?>

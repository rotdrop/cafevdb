<?php

namespace CAFEVDB;

class Instrumentation
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
    Config::init();

    //Config::$debug_query = true;
    if (Config::$debug_query) {
      echo "<PRE>\n";
      print_r($_POST);
      print_r($_GET);
      echo "</PRE>\n";
    }

    $opts = Config::$pmeopts;
    global $HTTP_SERVER_VARS;
    $opts['page_name'] = $HTTP_SERVER_VARS['PHP_SELF'].'?app=cafevdb'.'&Template=instrumentation';
    foreach (Config::$cgiVars as $key => $value) {
      $opts['cgi']['persist']["$key"] = $value = Util::cgiValue("$key");
      // echo "$key =&gt; $value <BR/>";
    }

    self::$action = $opts['cgi']['persist']['Action'];
    self::$subAction = $opts['cgi']['persist']['SubAction'];
    self::$musicianId = $opts['cgi']['persist']['MusicianId'];
    self::$projectId = $opts['cgi']['persist']['ProjectId'];
    self::$project = $opts['cgi']['persist']['Project'];;
    self::$recordsPerPage = $opts['cgi']['persist']['RecordsPerPage'];

    // Fetch some data we probably will need anyway

    $handle = mySQL::connect($opts);

    // List of instruments
    self::$instruments = Instruments::fetch($handle);
    $instrumentFamilies = mySQL::multiKeys('Instrumente', 'Familie', $handle);

    // Fetch project specific user fields
    if (self::$projectId >= 0) {
      //  echo "Id: self::$projectId <BR/>";
      self::$userExtraFields = Projects::extraFields(self::$projectId, $handle);
    }

    /* echo "<PRE>\n"; */
    /* print_r(self::$instruments); */
    /* /\*print_r(self::$instruments);*\/ */
    /* echo "</PRE>\n"; */

    /* checkInstruments($handle); */
    /* sanitizeInstrumentsTable($handle); */

    mySQL::close($handle);

    switch (self::$action) {
    case "DetailedInstrumentation": {
      DetailedInstrumentation::display($opts);
      break;
    }
    case "BriefInstrumentation": {
      BriefInstrumentation::display($opts);
      break;
    }
    case "TODO": {
      // ... the TODO-table actually has nothing to do with the
      // instrumentation.
      break;
    }
    case "AddMusicians": {
      Musicians::display($opts, true);
      break;
    }
    case "DisplayAllMusicians": {
      Musicians::display($opts, false);
      break;
    }
    case "AddOneMusician":
    case "ChangeOneMusician": {
      Musicians::displayAddChangeOne($opts);
      break;
    }
    case "AddInstruments": {
      Instruments::display($opts);
      break;
    }
    default: {
      // should emit an error here ...
    }
    }
  }
}; // class Instrumentation

?>

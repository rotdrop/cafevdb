<?php

namespace CAFEVDB;

class Instrumentation
{
  public $action;
  public $subAction;
  public $musicianId;
  public $projectId;
  public $project;
  protected $recordsPerPage;
  protected $userExtraFields;
  protected $instruments;
  protected $instrumentFamilies;
  protected $opts;

  protected function __construct()
  {
    Config::init();

    //Config::$debug_query = true;
    if (Config::$debug_query) {
      echo "<PRE>\n";
      print_r($_POST);
      print_r($_GET);
      echo "</PRE>\n";
    }

    $this->opts = Config::$pmeopts;
    foreach (Config::$cgiVars as $key => $value) {
      $this->opts['cgi']['persist']["$key"] = $value = Util::cgiValue("$key");
      // echo "$key =&gt; $value <BR/>";
    }

    $this->action = $this->opts['cgi']['persist']['Action'];
    $this->subAction = $this->opts['cgi']['persist']['SubAction'];
    $this->musicianId = $this->opts['cgi']['persist']['MusicianId'];
    $this->projectId = $this->opts['cgi']['persist']['ProjectId'];
    $this->project = $this->opts['cgi']['persist']['Project'];;
    $this->recordsPerPage = $this->opts['cgi']['persist']['RecordsPerPage'];

    // Fetch some data we probably will need anyway

    $handle = mySQL::connect($this->opts);

    // List of instruments
    $this->instruments = Instruments::fetch($handle);
    $instrumentFamilies = mySQL::multiKeys('Instrumente', 'Familie', $handle);

    // Fetch project specific user fields
    if ($this->projectId >= 0) {
      //  echo "Id: $this->projectId <BR/>";
      $this->userExtraFields = Projects::extraFields($this->projectId, $handle);
    }

    /* echo "<PRE>\n"; */
    /* print_r($this->instruments); */
    /* /\*print_r($this->instruments);*\/ */
    /* echo "</PRE>\n"; */

    /* checkInstruments($handle); */
    /* sanitizeInstrumentsTable($handle); */

    mySQL::close($handle);
  }
};


/*   public function display() */
/*   { */
/*     switch ($this->action) { */
/*     case "DetailedInstrumentation": { */
/*       DetailedInstrumentation::display($this->opts); */
/*       break; */
/*     } */
/*     case "BriefInstrumentation": { */
/*       BriefInstrumentation::display($this->opts); */
/*       break; */
/*     } */
/*     case "TODO": { */
/*       // ... the TODO-table actually has nothing to do with the */
/*       // instrumentation. */
/*       break; */
/*     } */
/*     case "AddMusicians": { */
/*       Musicians::display($this->opts, true); */
/*       break; */
/*     } */
/*     case "DisplayAllMusicians": { */
/*       Musicians::display($this->opts, false); */
/*       break; */
/*     } */
/*     case "AddOneMusician": */
/*     case "ChangeOneMusician": { */
/*       Musicians::displayAddChangeOne($this->opts); */
/*       break; */
/*     } */
/*     case "AddInstruments": { */
/*       Instruments::display($this->opts); */
/*       break; */
/*     } */
/*     default: { */
/*       // should emit an error here ... */
/*     } */
/*     } */
/*   } */
/* }; // class Instrumentation */

?>

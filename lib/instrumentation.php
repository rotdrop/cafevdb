<?php

namespace CAFEVDB
{

class Instrumentation
{
  public $musicianId;
  public $projectId;
  public $project;
  public $template;
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

    $this->musicianId = $this->opts['cgi']['persist']['MusicianId'];
    $this->projectId = $this->opts['cgi']['persist']['ProjectId'];
    $this->project = $this->opts['cgi']['persist']['Project'];;
    $this->template = $this->opts['cgi']['persist']['Template'];;
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

}

?>

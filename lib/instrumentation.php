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
  protected $pme;
  protected $execute;
  protected $pme_bare;

  public function deactivate() 
  {
    $this->execute = false;
  }

  public function activate() 
  {
    $this->execute = true;
  }

  public function execute()
  {
    if ($this->pme) {
      $this->pme->execute();
    }
  }

  public function navigation($enable)
  {
    $this->pme_bare = !$enable;
  }

  function csvExport(&$handle, $delim = ',', $enclosure = '"', $filter = false)
  {
    if (!$this->pme) {
      return;
    }
    if ($this->pme->connect() == false) {
      return false;
    }
    $this->pme->csvExport($handle, $delim, $enclosure, $filter);
    $this->pme->sql_disconnect();
  }

  /* Quick and dirty general export. On each cell a call-back
   * function is invoked with the html-output of that cell.
   *
   * This is just like list_table(), i.e. only the chosen range of
   * data is displayed and in html-display order.
   *
   * @param[in] $cellFilter $line[] = Callback($i, $j, $celldata)
   *
   * @param[in] $lineCallback($i, $line)
   *
   * @param[in] $css CSS-class to pass to cellDisplay().
   */
  function export($cellFilter = false, $lineCallback = false, $css = 'noescape')
  {
    if (!$this->pme) {
      return;
    }
    if ($this->pme->connect() == false) {
      return false;
    }
    $this->pme->export($cellFilter, $lineCallback, $css);
    $this->pme->sql_disconnect();
  }        

  protected function __construct($_execute = true)
  {
    $this->execute = $_execute;
    $this->pme = false;
    $this->pme_bare = false;

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

<?php

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB
{

/**Base class to support instrumentation specific PME-tables.
 */
class Instrumentation
{
  public $musicianId;
  public $projectId;
  public $project;
  public $template;
  protected $operation;
  protected $recordsPerPage;
  protected $userExtraFields;
  protected $instruments;
  protected $instrumentFamilies;
  protected $memberStatus;
  protected $memberStatusNames;
  protected $opts;
  protected $pme;
  protected $execute;
  protected $pme_bare;
  protected $secionLeaderColumn;
  protected $registrationColumn;

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

  /**Disable some extra stuff (image upload etc.) when displaying the entire table.
   */
  public function changeOperation()
  {
    if (!isset($this->pme)) {
      return false;
    } else {
      return $this->pme->change_operation() || $this->pme->add_operation();
    }
  }

  /**Disable some extra stuff (image upload etc.) when displaying the entire table.
   */
  public function listOperation()
  {
    if (!isset($this->pme)) {
      return true;
    } else {
      return $this->pme->list_operation();
    }
  }

  /**Determine if we have the default ordering of rows. */
  public function defaultOrdering() 
  {
    if (!isset($this->pme)) {
      return false;
    }
    return empty($this->pme->sfn);
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

    $this->operation  = Util::cgiValue(Config::$pmeopts['cgi']['prefix']['sys']."operation", false);
    $this->cancelSave = Util::cgiKeySearch('/'.Config::$pmeopts['cgi']['prefix']['sys'].'(save|cancel)/');

    $this->sectionLeaderColumn = array(
      'name' => $this->operation ? L::t("Section Leader") : ' &alpha;',
      'options'  => 'LAVCPDF',
      'select' => 'D',
      'maxlen' => '1',
      'sort' => true,
      'escape' => false,
      'values2' => array('0' => '&nbsp;', '1' => '&alpha;'),
      'tooltip' => L::t("Set to `%s' in order to mark the section leader",
                        array("&alpha;")),
      );

    $this->registrationColumn = array(
      'name' => $this->operation ? L::t("Registration") : ' &#10004;',
      'options'  => 'LAVCPDF',
      'select' => 'D',
      'maxlen' => '1',
      'sort' => true,
      'escape' => false,
      'values2' => array('0' => '&nbsp;', '1' => '&#10004;'),
      'tooltip' => L::t("Set to `%s' in order to mark participants who passed a personally signed registration form to us.",
                        array("&#10004;")),      
      );      

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
    $this->groupedInstruments = Instruments::fetchGrouped($handle);
    $this->instrumentFamilies = mySQL::multiKeys('Instrumente', 'Familie', $handle);
    $this->memberStatus = mySQL::multiKeys('Musiker', 'MemberStatus', $handle);
    $this->memberStatusNames = array();
    foreach ($this->memberStatus as $tag) {
      $this->memberStatusNames[$tag] = strval(L::t($tag));
    }
    if (false) {
      // Dummies to keep the translation right.
      L::t('regular');
      L::t('passive');
      L::t('soloist');
      L::t('conductor');
      L::t('temporary');
    }

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

  /** phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param[in] $pme The phpMyEdit instance
   *
   * @param[in] $op The operation, 'insert', 'update' etc.
   *
   * @param[in] $step 'before' or 'after'
   *
   * @param[in] $oldvals Self-explanatory.
   *
   * @param[in,out] &$changed Set of changed fields, may be modified by the callback.
   *
   * @param[in,out] &$newvals Set of new values, which may also be modified.
   *
   * @return boolean. If returning @c false the operation will be terminated
   */
  public static function beforeInsertFixProjectTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
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

    // For an unknown reason the project Id is zero ....

    $newvals['ProjektId'] = $projectId;

    return true;    
  }

  /** phpMyEdit calls the trigger (callback) with the following arguments:
   *
   * @param[in] $pme The phpMyEdit instance
   *
   * @param[in] $op The operation, 'insert', 'update' etc.
   *
   * @param[in] $step 'before' or 'after'
   *
   * @param[in] $oldvals Self-explanatory.
   *
   * @param[in,out] &$changed Set of changed fields, may be modified by the callback.
   *
   * @param[in,out] &$newvals Set of new values, which may also be modified.
   *
   * @return boolean. If returning @c false the operation will be terminated
   */
  public static function beforeUpdateInstrumentTrigger(&$pme, $op, $step, $oldvals, &$changed, &$newvals)
  {
    $project    = Util::cgiValue('Project');
    $projectId  = Util::cgiValue('ProjectId');
    $musicianId = Util::cgiValue('MusicianId', -1);
    $table      = Util::cgiValue('Table', false);

    // We check here whether the change of the instrument or player is in
    // some sense consistent with the Musiker table. We know that only
    // MusikerId and instrument can change
    //
    // TODO: change to a pop-up window, Javascript and AJAX-calls

    // $pme      -- pme object
    // $newvals   -- contains the new values
    // $pme->rec -- primary key
    // $oldvals   -- old values
    // $changed 

    /* echo '<PRE> */
    /* '; */
    /* print_r($newvals); */
    /* print_r($oldvals); */
    /* print_r($changed); */
    /* echo "Rec: ".$pme->rec."\n"; */
    /* echo '</PRE> */
	 /* '; */

    if (!isset($newvals['Instrument'])) {
      // No need to check.
      return true;
    }

    if (false) {
      echo '<PRE>';
      echo "musid: ".$musicianId."\n";
      print_r($_POST);
      print_r($newvals);
      print_r($oldvals);
      print_r($changed);
      echo '</PRE>';
    }

    $dontCheckMusicianId = $musicianId < 0;
    if ($musicianId < 0) {
      if (isset($newvals['MusikerId'])) {
        // Should normally not happen ...
        $musicianId = $newvals['MusikerId'];
      } else if (isset($oldvals['MusikerId'])) {
        $musicianId = $oldvals['MusikerId'];
      } else {
        // otherwise it must be this->rec
        //
        // TODO: check for consistency with Table cgi var.
        $musicianId = $pme->rec;
      }

    }
    
    // Fetch the list of instruments from the Musiker data-base
    
    $musquery = "SELECT `Instrumente`,`Vorname`,`Name` FROM Musiker WHERE `Id` = $musicianId";

    $musres = $pme->myquery($musquery) or die ("Could not execute the query. " . mysql_error());
    $musnumrows = mysql_num_rows($musres);

    if ($musnumrows != 1) {
      echo L::t("Data inconsisteny, %d is not a unique Id, got %d data sets.",
                array($musicianId, $musnumrows));
      return false;
    }

    $musrow = $pme->sql_fetch($musres);
    //$instruments = explode(',',$musrow['Instrumente']);
    $musname = $musrow['Vorname'] . " " . $musrow['Name'];

    // Consistency check; $newvals['MusikerId'] may either be a
    // numeric id or just the name of the musician.
    if (!$dontCheckMusicianId
        &&
        ((is_numeric($newvals['MusikerId']) && $musicianId != $newvals['MusikerId'])
         ||
         (!is_numeric($newvals['MusikerId']) && $musname !=  $newvals['MusikerId']))) {
      echo L::t("Data inconsistency: Ids do not match (`%s' != `%s')",
                array($newvals['MusikerId'], $musicianId));
      return false;
    }

    $instruments = $musrow['Instrumente'];
    $instrument  = $newvals['Instrument'];
    
    if (!strstr($instruments, $instrument)) {
      $text1 = L::t('Instrument not known by %s, correct that first! %s only plays %s!!!',
                    array($musname, $musname, $instruments));
      $text2 = L::t('Click on the following button to enforce your decision');
      $text3 = L::t('This will also add `%s\' to %s\'s list of known instruments. '
                    .'Unfortunately, all your other changes might be discarded. '.
                    'You may want to try the `Back\'-Button of your browser.',
                    array($instrument, $musname));
      $btnValue = L::t('Really Change %s\'s instrument!!!', array($musname));
      $btn =<<<__EOT__
<form style="display:inline;" name="CAFEV_form_besetzung" method="post" action="?app=cafevdb">
  <input type="submit" name="" value="$btnValue">
__EOT__;

      if ($pme->cgi['persist'] != '') {
        $btn .= $pme->get_origvars_html($pme->cgi['persist']);
      }
      $btn .= $pme->htmlHiddenSys('mtable', $pme->tb);
      $btn .= $pme->htmlHiddenSys('mkey', $pme->key);
      $btn .= $pme->htmlHiddenSys('mkeytype', $pme->key_type);
      foreach ($pme->mrecs as $key => $val) {
        $btn .= $pme->htmlHiddenSys('mrecs['.$key.']', $val);
      }
      $btn .=<<<__EOT__
  <input type="hidden" name="Template" value="change-one-musician">
  <input type="hidden" name="Project" value="$project" />
  <input type="hidden" name="ProjectId" value="$projectId" />
  <input type="hidden" name="MusicianId" value="$musicianId" />
  <input type="hidden" name="ForcedInstrument" value="$instrument" />

__EOT__;
      $btn .=<<<__EOT__
</form>
__EOT__;
// TODO: will probably not work with bulk-stuff
  echo <<<__EOT__
<div class="cafevdb-table-notes" style="height:18ex">
  <div class="cafevdb-note change-instrument">
  <div>$text1</div>
  <div>$text2: $btn</div>
  <div>$text3</div>
  </div>
</div>

__EOT__;

      return false;
    }
    
    return true;
  }  
  
};

}

?>

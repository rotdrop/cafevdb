<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

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
  protected $pmeSysPfx;
  protected $pmeTranslations;
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
  
  protected function pmeCompareButtonValue($a, $b)
  {
    return ($a == $b ||
            (isset($this->pmeTranslations[$a]) && $this->pmeTranslations[$a] == $b) ||
            (isset($this->pmeTranslations[$b]) && $this->pmeTranslations[$b] == $a));
  }

  /**Are we in change mode? */
  public function addOperation()
  {
    if (!isset($this->pme)) {
      return $this->pmeCompareButtonValue($this->operation, 'Add');
    } else {
      return $this->pme->add_operation();
    }
  }

  /**Are we in change mode? */
  public function changeOperation()
  {
    if (!isset($this->pme)) {
      return $this->pmeCompareButtonValue($this->operation, 'Change');
    } else {
      return $this->pme->change_operation();
    }
  }

  /**Are we in copy mode? */
  public function copyOperation()
  {
    if (!isset($this->pme)) {
      return $this->pmeCompareButtonValue($this->operation, 'Copy');
    } else {
      return $this->pme->copy_operation();
    }
  }

  /**Are we in view mode? */
  public function viewOperation()
  {
    if (!isset($this->pme)) {
      return $this->pmeCompareButtonValue($this->operation, 'View');
    } else {
      return $this->pme->view_operation();
    }
  }

  /**Are we in delete mode?*/
  public function deleteOperation()
  {
    if (!isset($this->pme)) {
      return $this->pmeCompareButtonValue($this->operation, 'Delete');
    } else {
      return $this->pme->delete_operation(); 
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

  /**Return a PME sys variable. */
  public function pmeSysValue($key)
  {
    return Util::cgiValue($this->pmeSysPfx.$key, false);
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
    $this->pme = null;
    $this->pme_bare = false;

    Config::init();

    global $debug_query;
    $debug_query = Util::debugMode('query');

    if (Util::debugMode('request')) {        
      echo "<PRE>\n";
      print_r($_POST);
      print_r($_GET);
      echo "</PRE>\n";
    }

    $this->pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];

    $this->pmeTranslations = $this->pmeSysValue('translations');
    if (file_exists($this->pmeTranslations)) {
      $this->pmeTranslations = include($this->pmeTranslations);
    } else {
      $this->pmeTranslations = array(
        // some default stuff
        'Add' => 'Add',
        'Change' => 'Change'
        );
    }

    $this->operation  = $this->pmeSysValue('operation');
    if ($this->pmeSysValue('reloadview')) {
      $this->operation = 'View';
    }
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

    if (Util::debugMode('request')) {
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
    if (!$dontCheckMusicianId && (isset($newvals['MusikerId']) || isset($oldvals['MusikerId']))) {
      $pmeMusId = isset($newvals['MusikerId']) ? $newvals['MusikerId'] : $oldvals['MusikerId'];

      if ((is_numeric($pmeMusId) && $musicianId != $pmeMusId)
          ||
          (!is_numeric($pmeMusId) && $musname !=  $pmeMusId)) {
        echo L::t("Data inconsistency: Ids do not match (`%s' != `%s')",
                  array($pmeMusId, $musicianId));
        return false;
      }
    }

    $instruments = $musrow['Instrumente'];
    $instrument  = $newvals['Instrument'];
    

    // TODO: replace by AJAX validation and provide a nice popup for
    // in order to change the instrument of the person.
    if (!strstr($instruments, $instrument)) {
      $text1 = L::t('Instrument not known by %s, correct that first! %s only plays %s!!!',
                    array($musname, $musname, $instruments));
  echo <<<__EOT__
<div class="cafevdb-table-notes" style="height:18ex">
  <div class="cafevdb-note change-instrument">
  <div>$text1</div>
  </div>
</div>

__EOT__;
      return true;
      // return false;
    }
    
    return true;
  }

  /**Look up the musician Id in the Besetzungen table and fetch the
   * musician's data from the Musiker table. We return all data from
   * the musician in order to be inefficient ;) and in particular the
   * project-instrument from the Besetzungen table.
   */
  public static function fetchMusicianData($recordId, $projectId, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = " SELECT `Musiker`.*,
 `Besetzungen`.`ProjektId`,`Besetzungen`.`Instrument` as 'ProjektInstrument',
 `Projekte`.`Name` AS `Projekt`,`Projekte`.`Jahr`,`Projekte`.`Besetzung`
 FROM `Musiker`
   LEFT JOIN `Besetzungen`
     ON `Musiker`.`Id` = `Besetzungen`.`MusikerId`
   LEFT JOIN `Projekte`
     ON `Besetzungen`.`ProjektId` = `Projekte`.`Id`
     WHERE `Besetzungen`.`Id` = $recordId";
    if ($projectId >= 0) {
      $query .= " AND `Besetzungen`.`ProjektId` = $projectId";
    }
   
    //throw new \Exception($query);
 
    $result = mySQL::query($query, $handle);
    if ($result !== false && mysql_num_rows($result) == 1) {
      $row = mySQL::fetch($result);
    } else {
      $row = false;
    }
    
    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $row;
  }

  /**Fetch all entries for the given musician-id (may be more than one or none)
   */
  public static function fetchByMusicianId($musicianId, $projectId, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $query = " SELECT `Musiker`.*,
 `Besetzungen`.`ProjektId`,`Besetzungen`.`Instrument` as 'ProjektInstrument',
 `Projekte`.`Name` AS `Projekt`,`Projekte`.`Jahr`,`Projekte`.`Besetzung`
 FROM `Musiker`
   LEFT JOIN `Besetzungen`
     ON `Musiker`.`Id` = `Besetzungen`.`MusikerId`
   LEFT JOIN `Projekte`
     ON `Besetzungen`.`ProjektId` = `Projekte`.`Id`
     WHERE `Musiker`.`Id` = $musicianId";
    if ($projectId >= 0) {
      $query .= " AND `Besetzungen`.`ProjektId` = $projectId";
    }
   
    //throw new \Exception($query);

    $result = mySQL::query($query, $handle);
    if ($result !== false) {
      $data = array();
      while ($row = mySQL::fetch($result)) {
        $data[] = $row;
      }
    } else {
      $data = false;
    }
    
    if ($ownConnection) {
      mySQL::close($handle);
    }

    return $data;
  }

  
};

}

?>

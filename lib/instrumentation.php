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
  public $projectName; ///< project name
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
    $this->projectName = $this->opts['cgi']['persist']['ProjectName'];;
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

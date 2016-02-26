<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  protected $instrumentInfo;
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

  /**Are we in add mode? */
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
    $this->instrumentInfo = Instruments::fetchInfo($handle);
    $this->instruments = $this->instrumentInfo['byId'];
    $this->groupedInstruments = $this->instrumentInfo['nameGroups'];
    $this->instrumentFamilies = mySQL::multiKeys('Instrumente', 'Familie', $handle);
    $this->memberStatus = mySQL::multiKeys('Musiker', 'MemberStatus', $handle);
    $this->memberStatusNames = array(
      'regular' => strval(L::t('regular musician')),
      'passive' => strval(L::t('passive member')),
      'soloist' => strval(L::t('soloist')),
      'conductor' => strval(L::t('conductor')),
      'temporary' => strval(L::t('temporary musician'))
      );
    foreach ($this->memberStatus as $tag) {
      if (!isset($this->memberStatusNames[$tag])) {
        $this->memberStatusNames[$tag] = strval(L::t($tag));
      }
    }
    if (false) {
      // Dummies to keep the translation right.
      L::t('regular');
      L::t('passive');
      L::t('soloist');
      L::t('conductor');
      L::t('temporary');
    }

    /* echo "<PRE>\n"; */
    /* print_r($this->instruments); */
    /* /\*print_r($this->instruments);*\/ */
    /* echo "</PRE>\n"; */

    /* checkInstruments($handle); */
    /* sanitizeInstrumentsTable($handle); */

    mySQL::close($handle);
  }

  public static function getExtraFields($projectId, $handle = false)
  {
    static $usedProject = -1;
    static $extraFields = null;

    if (empty($extraFields) || $usedProject !== $projectId) {
      $extraFields = ProjectExtra::projectExtraFields($projectId, true, $handle);
      $usedProject = $projectId;
    }

    return $extraFields;
  }


  /**Look up the musician Id in the Besetzungen table and fetch the
   * musician's data from the Musiker table. We return all data from
   * the musician in order to be inefficient ;) and in particular the
   * project-instrument from the Besetzungen table.
   */
  protected static function fetchMusician($where, $projectId, $handle = false)
  {
    $ownConnection = $handle === false;

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $where .= " AND b.`ProjektId` = $projectId";

    $query = " SELECT
 m.*, b.`ProjektId` AS ProjectId,
 b.`Id` AS InstrumentationId,
 GROUP_CONCAT(DISTINCT im.`Id` ORDER BY im.`Id`) AS MusicianInstrumentIds,
 GROUP_CONCAT(DISTINCT im.`Instrument` ORDER BY im.`Id`) AS MusicianInstruments,
 GROUP_CONCAT(DISTINCT ip.`Id` ORDER BY ip.`Id`) AS ProjectInstrumentIds,
 GROUP_CONCAT(DISTINCT ip.`Instrument` ORDER BY ip.`Id`) AS ProjectInstruments,
 p.`Name` AS `ProjectName`, p.`Jahr` AS ProjectYear, p.`Besetzung` AS Instrumentation
 FROM `Musiker` m
 LEFT JOIN `MusicianInstruments` mi
   ON m.`Id` = mi.`MusicianId`
 LEFT JOIN `Besetzungen` b
   ON m.`Id` = b.`MusikerId`
 LEFT JOIN `Projekte` p
   ON b.`ProjektId` = p.`Id`
 LEFT JOIN `ProjectInstruments` pi
   ON pi.`InstrumentationId` = b.`Id`
 LEFT JOIN `Instrumente` im
   ON im.`Id` = mi.`InstrumentId`
 LEFT JOIN `Instrumente` ip
   ON ip.`Id` = pi.`InstrumentId`
 ";
    $query .= " WHERE $where";
    $query .= " GROUP BY b.`Id`";

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

  public static function fetchMusicianData($recordId, $projectId, $handle = false)
  {
    $where = "b.`Id` = $recordId";

    $data = self::fetchMusician($where, $projectId, $handle);

    if (!empty($data) && count($data) === 1) {
      return $data[0];
    }

    //error_log(__METHOD__.' ups '.print_r($data, true));

    return false;
  }

  /**Fetch all entries for the given musician-id (may be more than one or none)
   */
  public static function fetchByMusicianId($musicianId, $projectId, $handle = false)
  {
    $where = "m.`Id` = $musicianId";

    $data = self::fetchMusician($where, $projectId, $handle);

    return $data;
  }

  /**Add musicians to a given project. The functions tries to make
   * some not-absurd choice for the project instrument, given the
   * instrument the musician is playing and the instruments needed by
   * the project.
   *
   * @param[in] mixed $musicians
   * - integer: single id
   * - string: single UUID
   * - array: flat array of integers meaning ids and/or strings meaning UUIDs.
   *
   * @param[in] $projectId Project id.
   *
   * @param[in] $handle Database handle, optional.
   *
   * @return array('added' => array('id' => MUSID, 'instrumentationId' => BESETZUNGENID,
   *                                'notice' => STR),
   *               'failed' => array('id' => MUSID,
   *                                 'notice' => STR,
   *                                 'sqlerror' => STR))
   *
   * Or === false, if operation failed early.
   */
  public static function addMusicians($musicians, $projectId, $handle = false)
  {
    $ownConnection = $handle === false;

    $failed = array();
    $added = array();

    if (is_scalar($musicians)) {
      $musicians = array($musicians);
    }
    if (!is_array($musicians)) {
      return false;
    }

    foreach ($musicians as $ident) {
      if (!is_numeric($ident) && !is_string($ident)) {
        return false;
      }
      if (!is_numeric($ident) && strlen($ident) != 36) {
        return false;
      }
    }

    $numRecords = count($musicians);
    if ($numRecords == 0) {
      return array('added' => $added,
                   'failed' => $failed);
    }

    if ($ownConnection) {
      Config::init();
      $handle = mySQL::connect(Config::$pmeopts);
    }

    $projectInstruments = Projects::fetchInstrumentation($projectId, $handle);
    if ($projectInstruments === false) {
      if ($ownConnection) {
        mySQL::close($handle);
      }
      return false;
    }

    foreach ($musicians as $ident) {
      $notice = '';

      if (is_numeric($ident)) {
        $musRow = Musicians::fetchMusicianById($ident, $handle);
      } else {
        $musRow = Musicians::fetchMusicianByUUID($ident, $handle);
      }

      if ($musRow === false) {
        $failed[] = array(
          'id' => $ident,
          'notice' => L::t('Unable to fetch musician\'s personal information for id %d',
                           array($ident)),
          'sqlerror' => mySQL::error($handle));
        continue;
      }

      $musicianId = $musRow['Id'];
      $musInstrumentIds = Util::explode(',', $musRow['InstrumentIds']);
      $musInstruments   = Util::explode(',', $musRow['Instruments']);
      $fullName = $musRow['Vorname']." ".$musRow['Name'];

      // Try to make likely default choice for the project instrument
      $musInstrument   = null;
      $musInstrumentId = null;

      $both = array_intersect($projectInstruments['InstrumentIds'], $musInstrumentIds);

      if (!empty($both)) {
        $musInstrumentId = reset($both);
        $key = array_search($musInstrumentId, $musInstrumentIds);
        $musInstrument   = $musInstruments[$key];
      } else if (!empty($musInstruments)) {
        $musInstrument   = $musInstruments[0];
        $musInstrumentId = $musInstrumentIds[0];
        $notice .= L::t("None of the instruments known by %s are mentioned in the "
                        ."instrumentation-list for the project. "
                        ."The musician is added nevertheless to the project with the instrument `%s'",
                        array($fullName, $musInstrument));
      } else {
        $musInstrument = null;
        $notice .= L::t("The musician %s doesn't seem to play any instrument ...",
                        array($fullName));
      }

      // Default project fees
      $fees = Projects::fetchFees($projectId, $handle);

      // Values to insert
      $values = array('MusikerId' => $musicianId,
                      'ProjektId' => $projectId,
                      'Unkostenbeitrag' => $fees['fee'],
                      'Anzahlung' => $fees['deposit']);

      // do it ...
      $instrumentationId = -1;
      if (mySQL::insert('Besetzungen', $values, $handle) === false) {
        $failed[] = array('id' => $musicianId,
                          'notice' => L::t('Adding %s (id = %d) failed.',
                                           array($fullName, $musicianId)),
                          'sqlerror' => mySQL::error($handle));
        continue;
      }
      $instrumentationId = mySQL::newestIndex($handle);
      if ($instrumentationId === false || $instrumentationId === 0) {
        $failed[] = array('id' => $musicianId,
                          'notice' => L::t('Unable to get the new id for %s (id = %d)',
                                           array($fullName, $musicianId)),
                          'sqlerror' => mySQL::error($handle));
        continue;
      }

      // update the log
      mySQL::logInsert('Besetzungen', $instrumentationId, $values, $handle);

      // record quasi success
      $added[] = array(
        'musicianId' => $musicianId, // keep for debugging
        'instrumentationId' => $instrumentationId, // <- required
        'notice' => $notice
        );

      mySQL::storeModified($projectId, 'Projekte', $handle);
      mySQL::storeModified($musicianId, 'Musiker', $handle);

      // instruments are now stored in a separate pivot-table
      $values = [ 'ProjectId' => $projectId,
                  'MusicianId' => $musicianId,
                  'InstrumentationId' => $instrumentationId,
                  'InstrumentId' => $musInstrumentId ];
      if (mySQL::insert('ProjectInstruments', $values, $handle) === false) {
        $failed[] = array('id' => $musicianId,
                          'notice' => L::t('Adding instrument %s for %s (id = %d) failed.',
                                           array($musInstrument, $fullName, $musicianId)),
                          'sqlerror' => mySQL::error($handle));
        continue;
      }
      $projectInstrumentId = mySQL::newestIndex($handle);
      if ($projectInstrumentId === false || $projectInstrumentId === 0) {
        $failed[] = array(
          'id' => $musicianId,
          'notice' => L::t('Unable to get the new id for %s\'s project instrument %s (id = %d)',
                           array($fullName, $musInstrument, $musicianId)),
          'sqlerror' => mySQL::error($handle));
        continue;
      }

      // update the log
      mySQL::logInsert('ProjectInstruments', $projectInstrumentId, $values, $handle);

      mySQL::storeModified($projectId, 'Projekte', $handle);
      mySQL::storeModified($musicianId, 'Musiker', $handle);

    }

    if ($ownConnection) {
      mySQL::close($handle);
    }

    return array('added' => $added, 'failed' => $failed);
  }



};

}

?>

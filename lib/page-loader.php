<?php
/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

/**@file
 *
 * @brief Page-loading with history.
 */

namespace CAFEVDB {

  /**A class managing principal page-loading and page-history. The
   * point is that the back and forward buttons of the web-browsers
   * are of no much use when replacing only parts of a page by
   * AJAX-calls. Also, there are always the annyoing dialog-boxes
   * which have to be clicked away to convince the browser to reload
   * form-data.
   *
   * We therefore record our own history in the PHP-session. We also
   * reload form-data when this is safe to do (reloading filtering,
   * sorting and email preselectons is not dangerous)
   */
  class PageLoader
  {
    const MAX_HISTORY_SIZE = 100;
    const SESSION_HISTORY_KEY = 'PageHistory';

    /**The data-contents. A "cooked" array structure with the
     * following components:
     *
     * array('size' => NUMBER_OF_HISTORY_RECORDS,
     *       'position' => CURRENT_POSITION_INTO_HISTORY_RECORDS,
     *       'records' => array(# => clone of $_POST)); 
     */
    private $session;
    private $historyRecords;
    private $historyPosition;
    private $historySize;

    /**Initialize a sane do-nothing record. */
    private function defaultHistory()
    {
      $this->historySize = 1;
      $this->historyPosition = 0;
      $this->historyRecords = array(array('md5' => md5(serialize(array())),
                                          'data' => array()));
    }
    
    /**Fetch any existing history from the session or initialize an
     * empty history if no history record is found.
     */
    public function __construct() {
      $this->session = new Session();
      $this->loadHistory();
    }

    /**Store the history away. */
    public function __destruct() {
      //\OCP\Util::writeLog(Config::APP_NAME, "PageLoader DTOR",
      // \OCP\Util::DEBUG); This just does not work as expected,
      // problems with order of execution of destructors.
      // $this->storeHistory();
    }

    /**Return a ready-to-use template, template-values are already
     * assigned. The returned template is ready for printPage() or
     * fetchPage().
     */
    public function template($renderas = "")
    {
      // Intialize the global config stuff
      Config::init();      

      // The most important ...
      $encrkey = Config::getEncryptionKey();

      // Get user and group
      $user  = \OCP\USER::getUser();
      $group = \OC_AppConfig::getValue('cafevdb', 'usergroup', '');

      // Are we a group-admin?
      $admin = \OC_SubAdmin::isGroupAccessible($user, $group);

      $tooltips   = Config::getUserValue('tooltips', 'on', $user);
      $usrFiltVis = Config::getUserValue('filtervisibility', 'off', $user);
      $pageRows   = Config::getUserValue('pagerows', 20, $user);

      // Filter visibility is stored here:
      $pmeSysPfx = Config::$pmeopts['cgi']['prefix']['sys'];
      Config::$pmeopts['cgi']['append'][$pmeSysPfx.'fl'] =
        $usrFiltVis == 'off' ? 0 : 1;

      // See if we are configured
      $config = ConfigCheck::configured();

      // following three may or may not be set
      $projectName = Util::cgiValue('ProjectName', '');
      $projectId   = Util::cgiValue('ProjectId', -1);
      $musicianId  = Util::cgiValue('MusicianId', -1);
      $recordId    = Util::getCGIRecordId();

      if (!$config['summary']) {
        $tmplname = 'configcheck';
      } else {
        $tmplname = Util::cgiValue('Template', 'blog');
      }

      $tmpl = new \OCP\Template('cafevdb', $tmplname, $renderas);
  
      $tmpl->assign('configcheck', $config);
      $tmpl->assign('orchestra', Config::getValue('orchestra'));
      $tmpl->assign('groupadmin', $admin);
      $tmpl->assign('usergroup', $group);
      $tmpl->assign('user', $user);
      $tmpl->assign('expertmode', Config::$expertmode);
      $tmpl->assign('tooltips', $tooltips);
      $tmpl->assign('encryptionkey', $encrkey);
      $tmpl->assign('uploadMaxFilesize', Util::maxUploadSize(), false);
      $tmpl->assign('uploadMaxHumanFilesize',
                    \OCP\Util::humanFileSize(Util::maxUploadSize()), false);
      $tmpl->assign('projectName', $projectName);
      $tmpl->assign('projectId', $projectId);
      $tmpl->assign('musicianId', $musicianId);
      $tmpl->assign('recordId', $recordId);
      $tmpl->assign('locale', Util::getLocale());
      $tmpl->assign('timezone', Util::getTimezone());
      $tmpl->assign('historySize', $this->historySize());
      $tmpl->assign('historyPosition', $this->historyPosition());

      return $tmpl;
    }

    /**Add a history snapshot. */
    public function pushHistory($data = false)
    {
      if ($data === false) {
        $data = $_POST;
      }
      ksort($data);
      $md5 = md5(serialize($data));
      $historyData = $this->historyRecords[$this->historyPosition];
      if ($historyData['md5'] != $md5) {
        // add the new record if it appears to be new
        array_splice($this->historyRecords, 0, $this->historyPosition);
        array_unshift($this->historyRecords, array('md5' => $md5,
                                                   'data' => $data));
        $this->historyPosition = 0;
        $this->historySize = count($this->historyRecords);
        while ($this->historySize > self::MAX_HISTORY_SIZE) {
          array_pop($this->historyRecords);
          --$this->historySize;
        }
      }
    }

    /**Fetch the history record at $offset. The function will through
     * an exception if offset is out of bounds.
     */
    public function fetchHistory($offset)
    {
      $newPosition = $this->historyPosition + $offset;
      if ($newPosition >= $this->historySize || $newPosition < 0) {
        throw new \OutOfBoundsException(
          L::t('Invalid history position %d request, history size is %d',
               array($newPosition, $this->historySize)));
      }
      
      $this->historyPosition = $newPosition;

      // Could check for valid data here, but so what
      return $this->historyRecords[$this->historyPosition]['data'];
    }

    /**Return the current position into the history. */
    public function historyPosition()
    {
      return $this->historyPosition;
    }

    /**Return the current position into the history. */
    public function historySize()
    {
      return $this->historySize;
    }

    /**Return true if the recorded history is essentially empty.
     */
    public function historyEmpty()
    {
      return $this->historySize <= 1 && count($this->historyRecords[0]['data']) == 0;
    }

    /**Store the current state whereever. Currently the PHP session
     * data, but this is not guaranteed.
     */
    public function storeHistory() 
    {
      $storageValue = array('size' => $this->historySize,
                            'position' => $this->historyPosition,
                            'records' => $this->historyRecords);
      $this->session->storeValue(self::SESSION_HISTORY_KEY, $storageValue);
    }

    /**Load the history state. Initialize to default state in case of
     * errors.
     */
    private function loadHistory()
    {
      $loadValue = $this->session->retrieveValue(self::SESSION_HISTORY_KEY);
      if (!$this->validateHistory($loadValue)) {
        $this->defaultHistory();
        return false;
      }
      $this->historySize = $loadValue['size'];
      $this->historyPosition = $loadValue['position'];
      $this->historyRecords = $loadValue['records'];
      return true;
    }

    /**Validate the given history records, return false on error.
     */
    private function validateHistory($history)
    {
      if ($history === false ||
          !isset($history['size']) ||
          !isset($history['position']) ||
          !isset($history['records']) ||
          !$this->validateHistoryRecords($history)) {
        return false;
      }
      return true;
    }
    
    /**Validate one history entry */
    private function validateHistoryRecord($record) {
      if (!is_array($record)) {
        return false;
      }
      if (!isset($record['md5']) || $record['md5'] != md5(serialize($record['data']))) {
        return false;
      }
      return true;
    }
    
    /**Validate all history records. */
    private function validateHistoryRecords($history) {
      foreach($history['records'] as $record) {
        if (!$this->validateHistoryRecord($record)) {
          return false;
        }
      }
      return true;
    }
    
  };

}


?>

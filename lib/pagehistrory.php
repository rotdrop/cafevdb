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
 * @brief Page-hitstory for CAFeV-DB.
 */

namespace CAFEVDB {

  /**A class managing a page-history. The point is that the back and
   * forward buttons of the web-browsers are of no much use when
   * replacing only parts of a page by AJAX-calls. Also, there are
   * always the annyoing dialog-boxes which have to be clicked away to
   * convince the browser to reload form-data.
   *
   * We therefore record our own history in the PHP-session. We also
   * reload form-data when this is safe to do (reloading filtering,
   * sorting and email preselectons is not dangerous)
   */
  class PageHistory 
  {
    const SESSION_KEY = 'PageHistory';

    /**The data-contents. A "cooked" array structure with the
     * following components:
     *
     * array('size' => <number of history records>,
     *       'position' => <current position into history records>,
     *       'records' => array(# => array('md5' => <checksum>,
     *                                     'data' => <clone of $_POST)); 
     */
    private $history;

    /**Fetch any existing history from the session or initialize an
     * empty history if no history record is found.
     */
    __construct() {
      if (!$this->load()) {
        $this->history = array(
          'size' => 1,
          'position' => 0,
          'records' => array(
            array('data' => array(),
                  'md5' => md5(serialize(array())))
            )
          );
        $this->store();
      }
    }

    /**Store the current state whereever. Currently the PHP session
     * data, but this is not guaranteed.
     */
    public function store() 
    {
      $storageValue = $this->history;
      
      foreach($storageValue['records'] as $idx => &$value) {
        $value['md5'] = serialize(ksort($value['data']));
      }
      Config::sessionStoreValue(self::SESSION_KEY, $storageValue);
    };

    /**Load the history sate. Initialize to default state in case of
     * errors.
     */
    public function load()
    {
      $loadValue = Config::sessionRetrieveValue(self::SESSION_KEY);
      if (!$this->validateHistory($loadValue)) {
        return false;
      }
      $this->history = $loadValue;
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
          !$this->validateRecords($history)) {
        return false;
      }
      return true;
    }
    
    /**Validate one history entry */
    private function validateRecord($record) {
      if (!is_array($record)) {
        return false;
      }
      if (!isset($record['md5'])) {
        return false;
      }
      if (!is_array($record['data'])) {
        return false;
      }      
      $md5 = md5(serialize(ksort($record['data'])));
      if ($md5 != $record['md5']) {
        return false;
      }
      return true;
    }
    
    /**Validate all history records. */
    private function validateRecords($history) {
      foreach($history['records'] as $record) {
        if (!$this->validateRecord($record)) {
          return false;
        }
      }
      return true;
    }
    
  };

}


?>

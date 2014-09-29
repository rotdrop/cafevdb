<?php
/**@author Claus-Justus Heine
 * @copyright 2012-2014 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * You should have received a copy of the GNU General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**@file
 * 
 * Server-client connector to fetch progress status for a
 * progress bar: is updated on the server, client may poll it via
 * AJAX.
 */

/**CamerataDB namespace to prevent name-collisions.
 */
namespace CAFEVDB 
{

  /**Progres-bar via data-base polling. Maybe too slow. We will see.
   */
  class ProgressStatus
  {
    const TABLE_SUFFIX = 'progress_status';
    private $table;
    private $user;
    private $id;
    private $fetchQuery;
    private $saveCurrentQuery;
    private $saveAllQuery;
    private $lastResult;

    /**Generate a row in the progress db-table. The combination of $id
     * and $user must be unique. $user defaults to the current one if
     * not specified. $tag may be any ASCI string up to 32 bytes.
     */
    public function __construct($id = 0, $tag = 'default', $user = false) 
    {
      if ($user === false) {
        $user = \OCP\USER::getUser();
      }
      $this->user = $user;
      $this->id   = $id;
      $this->table = '*PREFIX*'.Config::APP_NAME.'_'.self::TABLE_SUFFIX;

      $query = 'SELECT * FROM '.$this->table.' WHERE user = ? AND id = ?';
      $this->fetchQuery = \OCP\DB::prepare($query);

      $query = 'UPDATE '.$this->table.' SET current = ? WHERE user = ? AND id = ?';
      $this->saveCurrentQuery = \OCP\DB::prepare($query);

      $query = 'UPDATE '.$this->table.' SET current = ?, target = ?, tag = ? WHERE user = ? AND id = ?';
      $this->saveAllQuery = \OCP\DB::prepare($query);

      if ($this->fetch() === false) {
        $query = 'INSERT INTO '.$this->table.' (id, tag, user, current, target) VALUE (?,?,?,?,?)';
        $query = \OCP\DB::prepare($query);
        $query->execute(array($id, $tag, $this->user, 0, 0));
      }
      // Undo fetch.
      $this->lastResult = array('id' => $id, 'tag' => $tag, 'user' => $user, 'current' => 0, 'target' => 0);
    }  

    /**Return the unique id. */
    public function getId()
    {
      return $this->id;
    }

    /**Return the unique user. */
    public function getUser()
    {
      return $this->user;
    }

    /**Return the last fetched tag. */
    public function getTag()
    {
      return $this->lastResult['tag'];
    }

    /**Return the last stored or fetched total count. */
    public function getTarget()
    {
      return $this->lastResult['target'];
    }

    /**Return the last stored or fetched current count. */
    public function getCurrent()
    {
      return $this->lastResult['current'];
    }

    /**Store a progress value in the storage back-end.
     *
     * @param $value The current value.
     *
     * @param $target The current target value, optional, unchanged if
     * not specified.
     *
     * @param $tag Quais meta-information, up to 32 bytes,
     * optional. Last stored value if not given or 'default'.
     */
    public function save($value, $target = false, $tag = false)
    {
      if ($target === false) {
        $result = $this->saveCurrentQuery->execute(array($value, $this->user, $this->id));
        $this->lastResult['current'] = $value;
      } else {
        if ($tag === false) {
          $tag = $this->getTag(); // on write, tag spec from constructor takes precedence
        }
        $result = $this->saveAllQuery->execute(array($value, $target, $tag, $this->user, $this->id));
        $this->lastResult['current'] = $value;
        $this->lastResult['target'] = $target;
        $this->lastResult['tag'] = $tag;
      }
      return $result;
    }

    public function fetch()
    {
      $result = $this->fetchQuery->execute(array($this->user, $this->id));
      if ($result !== false) {
        $result = $result->fetchAll();
      }
      if (!is_array($result) || count($result) != 1) {
        return false;
      }
      $this->lastResult = $result[0];
    
      return $this->lastResult;
    }
  }

}

?>

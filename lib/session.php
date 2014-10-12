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

  class Session
  {
    private $session;
    
    public function __construct() {
      // Keep a reference to the underlying session handler
      $this->session = \OC::$session;
    }

    /**PHP session variable key to use for storing something tagged with
     * $key.
     */
    private static function sessionKey($key)
    {
      return Config::APP_NAME.'\\'.$key;
    }

    /**Store something in the session-data. It is completely left open
     * how this is done.
     *
     * sessionStoreValue() and sessionRetrieveValue() should be the only
     * interface points to the PHP session (except for, ahem, tweaks).
     */
    public function storeValue($key, $value)
    {
      $this->session->set(self::sessionKey($key), $value);
    }

    /**Fetch something from the session-data. It is completely left open
     * how this is done.
     *
     * @param $key The key tagging the desired data.
     *
     * @param $default What to return if the data is not
     * available. Defaults to @c false.
     *
     * sessionStoreValue() and sessionRetrieveValue() should be the only
     * interface points to the PHP session (except for, ahem, tweaks).
     */
    public function retrieveValue($key, $default = false)
    {
      $key = self::sessionKey($key);
      return $this->session->exists($key) ? $this->session->get($key) : $default;
    }
  }

} // namespace CAFEVDB

?>

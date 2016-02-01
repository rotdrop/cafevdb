<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  /**Yet another session wrapper. We store per-user data in an
   * array. As there is not in all cases a logout-procedure, we
   * remember the user and erase the data if the current user does not
   * match.
   */
  class Session
  {
    private $session;
    private $user;
    private $sessionKey;
    private $data;

    public function __construct() {
      // Keep a reference to the underlying session handler
      $this->session = \OC::$server->getSession();

      // Fetch the current user
      $this->user  = \OCP\USER::getUser();

      // Fetch our data
      $this->sessionKey = strtoupper(Config::APP_NAME);

      $clean = true;
      if ($this->session->exists($this->sessionKey)) {
        $this->data = $this->session->get($this->sessionKey);
        if ($this->data['user'] == $this->user) {
          $clean = false;
        }
      }

      // clean on no data or user mismatch
      if ($clean) {
        $this->clearValues();
      }
    }

    public function close()
    {
      $this->session->close();
      $this->session = null;
    }

    /**Remove all session variables for the current user. */
    public function clearValues()
    {
      $this->data = array('user' => $this->user);
      $this->session->set($this->sessionKey, $this->data);
    }

    /**Store something in the session-data. It is completely left open
     * how this is done.
     */
    public function storeValue($key, $value)
    {
      $this->data[$key] = $value;
      $this->session->set($this->sessionKey, $this->data);
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
      return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    public function sessionData()
    {
      return $this->data;
    }

  }

} // namespace CAFEVDB

?>

<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

//WIP
namespace OCA\CAFEVDB\Traits;

use OCP\ISession;

trait SessionTrait {

  /** @var \OCP\ISession */
  private $session;

  /**Store something in the session-data. It is completely left open
   * how this is done.
   *
   * sessionStoreValue() and sessionRetrieveValue() should be the only
   * interface points to the PHP session (except for, ahem, tweaks).
   */
  protected function sessionStoreValue($key, $value)
  {
    $this->session->set($key, $value);
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
  protected function sessionRetrieveValue($key, $default = null)
  {
    $value =  $this->session->get($key);
    if (empty($value)) {
      $value = $default;
    }
    return $value;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

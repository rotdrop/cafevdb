<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Traits;

use OCP\ISession;

/** Session-store abstraction trait. */
trait SessionTrait
{

  /** @var \OCP\ISession */
  private $session;

  /**
   * Store something in the session-data. It is completely left open
   * how this is done.
   *
   * The methods sessionStoreValue() and sessionRetrieveValue() should be the only
   * interface points to the PHP session (except for, ahem, tweaks).
   *
   * @param string $key
   *
   * @param mixed $value
   *
   * @return void
   */
  protected function sessionStoreValue(string $key, mixed $value):void
  {
    $this->session->set($key, $value);
  }

  /**
   * Fetch something from the session-data. It is completely left open
   * how this is done.
   *
   * The methods sessionStoreValue() and sessionRetrieveValue() should be the only
   * interface points to the PHP session.
   *
   * @param string $key The key tagging the desired data.
   *
   * @param mixed $default What to return if the data is not
   * available. Defaults to @c false.
   *
   * @return mixed
   */
  protected function sessionRetrieveValue(string $key, mixed $default = null)
  {
    $value =  $this->session->get($key);
    if (empty($value)) {
      $value = $default;
    }
    return $value;
  }
}

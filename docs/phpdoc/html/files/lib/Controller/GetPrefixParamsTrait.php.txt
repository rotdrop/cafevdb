<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use OCP\IRequest;

/** Provide the getPrefixParams() function */
trait GetPrefixParamsTrait
{
  /**
   * @var IRequest
   *
   * No type-hint in order to stay compatible with \OCP\AppFramework\Controller
   */
  protected $request;

  /**
   * Get all request parameters matching the given prefix at the start
   * as an associated array, with the prefix removed.
   *
   * @param string $prefix
   *
   * @return array<string, mixed>
   */
  public function getPrefixParams(string $prefix):array
  {
    $result = [];
    $allParameters = $this->request->getParams();
    foreach ($allParameters as $key => $value) {
      if (strpos($key, $prefix) === 0) {
        $outKey = substr($key, strlen($prefix));
        $result[$outKey] = $value;
      }
    }

    return $result;
  }
}

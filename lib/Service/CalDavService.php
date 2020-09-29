<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Service;

use OCA\DAV\CalDAV\CalDavBackend;

class CalDavService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  /** @var CalDavBackend */
  protected $calDavBackend;

  public function __construct(ConfigService $configService, CalDavBackend $calDavBackend)
  {
    $this->configService = $configService;
    $this->calDavBackend = $calDavBackend;
  }

  public function createCalendar($name, $userId = null) {
    empty($userId) && ($userId = $this->userId());
    $principal = "principals/users/$userId";
    $calendar = $this->calDavBackend->getCalendarByUri($principal, $name);
    if (!empty($calendar))  {
      return $calendar['id'];
    } else {
      return $this->calDavBackend->createCalendar($principal, $name, []);
    }
  }

  public function shareCalendar($calendarId, $groupId) {
    $share = [
      'href' => 'principal:principals/groups/'.$groupId,
      'commonName' => '',
      'summary' => '',
      'readOnly' => false
    ];
    $calendar = $this->calDavBackend->getCalendarById($calendarId);
    if (empty($calendar)) {
      return false;
    }
    $this->calDavBackend->updateShares($calendar, $share, []);
    return true;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object;

// Operation/Builder for VCalendar objects
class VCalendarService
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  private $legacyCalendarObject;

  public function __construct(
    ConfigService $configService,
    OC_Calendar_Object $legacyCalendarObject
  ) {
    $this->configService = $configService;
    $this->legacyCalendarObject = $legacyCalendarObject;
  }

  public function validateRequest($eventData, $kind = 'VEVENT') {
    return $this->legacyCalendarObject->validateRequest($eventData);
  }

  public function createVCalendarFromRequest($eventData, $kind = 'VEVENT') {
    return $this->legacyCalendarObject->createVCalendarFromRequest($eventData);
  }

  public function updateVCalendarFromRequest($eventData, $kind = 'VEVENT') {
    return $this->legacyCalendarObject->updateVCalendarFromRequest($eventData);
  }

  public function legacyEventObject()
  {
    return $this->legacyCalendarObject;
  }

  public function playground() {

    $eventData = [
      'title' => 'Title',
      'description' => 'Text',
      'location' => 'Where',
      'categories' => 'Cat1,Cat2',
      'priority' => true,
      'from' => '01-11-2020',
      'fromtime' => '10:20:22',
      'to' => '30-11-2020',
      'totime' => '00:00:00',
      'calendar' => 'calendarId',
      'repeat' => 'doesnotrepeat',
    ];

    $errors = $this->legacyCalendarObject->validateRequest($eventData);
    $this->logError('EventError' . print_r($errors, true));
    $vCalendar = $this->legacyCalendarObject->createVCalendarFromRequest($eventData);
    $this->logError('VCalendar entry' . print_r($vCalendar, true));
  }

  /*
    $taskData = array('title' => $title,
                      'due' => date('d-m-Y', $timeStamp),
                      'start' => date('d-m-Y'),
                      'location' => 'Cyber-Space',
                      'categories' => $categories,
                      'description' => $description,
                      'calendar' => $calendarId,
                      'priority' => 99, // will get a star if != 0
                      'alarm' => $alarm);

    return Events::newTask($taskData);
   */
  private function createVTodoFromRequest($eventData)
  {
    $vCalendar = new \Sabre\VObject\Component\VCalendar();
    $vCalendar->PRODID = "Nextloud cafevdb " . \OCP\App::getAppVersion($this->appName());
    $vCalendar->VERSION = '2.0';

    $vtodo = $vCalendar->createComponent('VTODO');
    $vCalendar->add($vtodo);

    $vtodo->CREATED = new \DateTime('now', new \DateTimeZone('UTC'));

    $vtodo->UID = \Sabre\VObject\UUIDUtil::getUUID();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

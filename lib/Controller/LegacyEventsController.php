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

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\CalDavService;

use OCA\CAFEVDB\Legacy\Calendar\OC_Calendar_Object;

class LegacyEventsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  const ERROR_TEMPLATE = "errorpage";

  /** @var IL10N. */
  private $l;

  /** @var IURLGenerator. */
  private $urlGenerator;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ProjectService */
  private $projectService;

  /** @var configCheckService */
  private $configCheckService;

  /** @var calDavService */
  private $calDavService;

  /** @var OC_Calendar_Object */
  private $ocCalendarObject;

  public function __construct(
    $appName,
    IRequest $request,
    ConfigService $configService,
    ConfigCheckService $configCheckService,
    RequestParameterService $parameterService,
    ProjectService $projectService,
    CalDavService $calDavService,
    OC_Calendar_Object $ocCalendarObject,
    \OCP\IURLGenerator $urlGenerator
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->configCheckService = $configCheckService;
    $this->parameterService = $parameterService;
    $this->projectService = $projectService;
    $this->calDavService = $calDavService;
    $this->ocCalendarObject = $ocCalendarObject;
    $this->urlGenerator = $urlGenerator;

    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function newEventForm()
  {
    $projectId = $this->parameterService['ProjectId'];
    $projectName = $this->parameterService['ProjectName'];
    $eventKind = $this->parameterService['EventKind'];

    if ($projectId < 0 ||
        (empty($projectName) &&
         empty($projectName = $this->projectService->fetchName($projectId)))) {
      return self::grumble($this->l->t('Project-id and/or name not set'));
    }

    // standard calendar values
    $start  = $this->parameterService['start'];
    $end    = $this->parameterService['end'];
    $allday = $this->parameterService['allday'];

    $categories   = $projectName.','.$this->l->t($eventKind);
    $calendarUri  = $eventKind.'calendar';
    $calendarName = $this->getConfigValue($calendarUri, $this->l->t($eventKind));
    $calendarId   = $this->getConfigValue($calendarUri.'id', -1);
    $shareOwner   = $this->getConfigValue('shareowner');

    // Default title for the event
    $title        = $this->l->t($eventKind).', '.$projectName;

    // make sure that the calendar exists and is writable
    $newId = $this->configCheckService->checkSharedCalendar($calendarUri, $calendarName, $calendarId);

    if ($newId == false) {
      return self::grumble($this->l->t('Cannot access calendar: `%s\'', [$calendarUri]));
    } else if ($newId != $calendarId) {
      $this->setConfigValue($calendarUri, $newId);
      $calendarId = $newId;
    }

    if (!$start) {
      $start = new \DateTime('now');
      $start = $start->getTimeStamp();
    }

    if (!$end) {
      $duration = $this->getConfigValue('eventduration', 180);
      $end = $start + ($duration * 60);
    }

    $start = new \DateTime('@'.$start);
    $end = new \DateTime('@'.$end);
    $timezone = $this->getTimezone();
    $start->setTimezone(new \DateTimeZone($timezone));
    $end->setTimezone(new \DateTimeZone($timezone));

    // compute the list of all writable calendars of the user
    $calendars = $this->calDavService->getCalendars(true);
    usort($calendars, function($a, $b) use ($calendarId) {
      if ($a->getKey() == $b->getKey()) {
        return 0;
      } else if ($a->getKey() == $calendarId) {
        return -1;
      } else if ($b->getKey() == $calendarId) {
        return 1;
      }
      return strcmp($a->getDisplayName(), $b->getDisplayName());
    });
    $calendarOptions = [];
    foreach($calendars as $calendar) {
      $calendarOptions[] = [
        'active' => 1,
        'id' => $calendar->getKey(),
        'displayname' => $calendar->getDisplayName(),
        'userid' => ''
      ];
    }

    $access_class_options = $this->ocCalendarObject->getAccessClassOptions();
    $repeat_options = $this->ocCalendarObject->getRepeatOptions();
    $repeat_end_options = $this->ocCalendarObject->getEndOptions();
    $repeat_month_options = $this->ocCalendarObject->getMonthOptions();
    $repeat_year_options = $this->ocCalendarObject->getYearOptions();
    $repeat_weekly_options = $this->ocCalendarObject->getWeeklyOptions();
    $repeat_weekofmonth_options = $this->ocCalendarObject->getWeekofMonth();
    $repeat_byyearday_options = $this->ocCalendarObject->getByYearDayOptions();
    $repeat_bymonth_options = $this->ocCalendarObject->getByMonthOptions();
    $repeat_byweekno_options = $this->ocCalendarObject->getByWeekNoOptions();
    $repeat_bymonthday_options = $this->ocCalendarObject->getByMonthDayOptions();

    return new TemplateResponse(
      $this->appName(),
      'legacy/calendar/part.newevent',
      [
        'urlGenerator' => $this->urlGenerator,
        'calendar_options' => $calendarOptions,

        'access' => 'owner',
        'accessclass' => 'PUBLIC',
        'calendar_options' => $calendarOptions,
        'access_class_options' => $access_class_options,
        'repeat_options' => $repeat_options,
        'repeat_month_options' => $repeat_month_options,
        'repeat_weekly_options' => $repeat_weekly_options,
        'repeat_end_options' => $repeat_end_options,
        'repeat_year_options' => $repeat_year_options,
        'repeat_byyearday_options' => $repeat_byyearday_options,
        'repeat_bymonth_options' => $repeat_bymonth_options,
        'repeat_byweekno_options' => $repeat_byweekno_options,
        'repeat_bymonthday_options' => $repeat_bymonthday_options,
        'repeat_weekofmonth_options' => $repeat_weekofmonth_options,

        'eventid' => 'new',
        'startdate' => $start->format('d-m-Y'),
        'starttime' => $start->format('H:i'),
        'enddate' => $end->format('d-m-Y'),
        'endtime' => $end->format('H:i'),
        'allday' => $allday,
        'repeat' => 'doesnotrepeat',
        'repeat_month' => 'monthday',
        'repeat_weekdays' => [],
        'repeat_interval' => 1,
        'repeat_end' => 'never',
        'repeat_count' => '10',
        'repeat_weekofmonth' => 'auto',
        'repeat_date' => '',
        'repeat_year' => 'bydate',

        // cafevdb defaults
        'categories' => $categories,
        'title' => $title,
      ],
      'blank');
  }

  /**
   * @NoAdminRequired
   */
  public function newEvent()
  {
    return self::grumble($this->l->t("Not yet implemented"));
  }

  /**
   * @NoAdminRequired
   */
  public function exportEvent()
  {
    return self::grumble($this->l->t("Not yet implemented"));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

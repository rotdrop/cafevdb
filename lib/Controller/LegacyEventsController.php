<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

use DateTime;
use DateTimeZone;
use Throwable;

use Sabre\VObject\Component\VCalendar;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\Constants;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\VCalendarService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Service\EventsService;

/**Serves the requests issued by the old OC v8 event popups.*/
class LegacyEventsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;

  const ERROR_TEMPLATE = "errorpage";

  const READONLY_CATEGORIES = 1;
  const HIDDEN_CATEGORIES = 2;

  const SUBTOPIC_CLONE = 'clone';
  const SUBTOPIC_NEW = 'new';
  const SUBTOPIC_EDIT = 'edit';
  const SUBTOPIC_DELETE = 'delete';
  const SUBTOPIC_EXPORT = 'export';

  /** @var OC_Calendar_Object */
  private $ocCalendarObject;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    ?string $appName,
    IRequest $request,
    protected ConfigService $configService,
    private ConfigCheckService $configCheckService,
    private RequestParameterService $parameterService,
    private ProjectService $projectService,
    private CalDavService $calDavService,
    private VCalendarService $vCalendarService,
    private EventsService $eventsService,
    protected ToolTipsService $toolTipsService,
  ) {
    parent::__construct($appName, $request);

    $this->l = $this->l10N();
    $this->ocCalendarObject = $vCalendarService->legacyEventObject();
  }
  // phpcs:enable

  /**
   * @param string $topic
   *
   * @param string $subTopic
   *
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function serviceSwitch(string $topic, string $subTopic):Http\Response
  {
    switch ($topic) {
      case 'forms':
        switch ($subTopic) {
          case self::SUBTOPIC_NEW:
            return $this->newEventForm();
          case self::SUBTOPIC_EDIT:
            return $this->editEventForm($subTopic);
          case self::SUBTOPIC_CLONE:
            return $this->editEventForm($subTopic);
          default:
            break;
        }
        break;
      case 'actions':
        switch ($subTopic) {
          case self::SUBTOPIC_NEW:
            return $this->newEvent();
          case self::SUBTOPIC_EDIT:
            return $this->editEvent();
          case self::SUBTOPIC_DELETE:
            return $this->deleteEvent();
          case self::SUBTOPIC_EXPORT:
            return $this->exportEvent();
          default:
            break;
        }
        break;
      default:
        break;
    }
    return self::grumble($this->l->t("unknown service requested: `%s/%s'.", [$topic, $subTopic]));
  }

  /** @return Http\Response */
  private function newEventForm():Http\Response
  {
    $projectId = $this->parameterService['projectId'];
    $projectName = $this->parameterService['projectName'];
    $eventKind = $this->parameterService['eventKind'];

    if ($projectId > 0 && empty($projectName)) {
      $projectName = $this->projectService->fetchName($projectId);
    }

    if ($projectId <= 0 || empty($projectName)) {
      return self::grumble($this->l->t('Project-id and/or name not set'));
    }

    // standard calendar values
    $start  = $this->parameterService['start'];
    $end    = $this->parameterService['end'];
    $allday = $this->parameterService['allday'];

    $categories = $projectName . ',' . $this->appL10n()->t($eventKind);
    if (EventsService::absenceFieldsDefault($eventKind)) {
      // request generation of absence fields.
      $categories .= ',' . $this->eventsService->getRecordAbsenceCategory();
    }

    $protectCategories = $this->parameterService->getParam('protectCategories', self::READONLY_CATEGORIES);
    $calendarUri  = $eventKind.'calendar';
    $calendarName = $this->getConfigValue($calendarUri, ucfirst($this->l->t($eventKind)));
    $calendarId   = $this->getConfigValue($calendarUri.'id', null);

    // Default title for the event
    $summary        = $this->l->t($eventKind).', '.$projectName;

    // make sure that the calendar exists and is writable
    $newId = $this->configCheckService->checkSharedCalendar($calendarUri, $calendarName, $calendarId);

    if ($newId == false) {
      return self::grumble($this->l->t('Cannot access calendar: `%s\'', [$calendarUri]));
    } elseif ($newId != $calendarId) {
      $this->setConfigValue($calendarUri, $newId);
      $calendarId = $newId;
    }

    if (!$start) {
      $start = new DateTime('now');
      $start->setTime($start->format('H'), ceil($start->format('i') / 15) * 15);
      $start = $start->getTimeStamp();
    }

    $defaultEventDuration = $this->getConfigValue('eventduration', 180) * 60;

    if (!$end) {
      $end = $start + $defaultEventDuration;
    }

    $duration = $end - $start;

    $start = new DateTime('@'.$start);
    $end = new DateTime('@'.$end);
    $timezone = $this->getTimezone();
    $start->setTimezone(new DateTimeZone($timezone));
    $end->setTimezone(new DateTimeZone($timezone));

    // compute the list of all writable calendars of the user
    $calendars = $this->calDavService->getCalendars(true);
    $calendarOptions = [];
    foreach ($calendars as $calendar) {
      $calendarUris = $this->calDavService->calendarUris($calendar->getKey());
      $calendarOptions[] = [
        'active' => 1,
        'id' => $calendar->getKey(),
        'displayname' => $calendar->getDisplayName(),
        'userid' => $calendarUris['ownerid'],
        'uri' => $calendarUris['shareuri'],
      ];
    }
    usort($calendarOptions, function($a, $b) use ($calendarId) {
      if ($a['id'] == $b['id']) {
        return 0;
      } elseif ($a['id'] == $calendarId) {
        return -1;
      } elseif ($b['id'] == $calendarId) {
        return 1;
      }
      return strcmp($a['displayname'], $b['displayname']);
    });

    $accessClassOptions = $this->ocCalendarObject->getAccessClassOptions();
    $repeatOptions = $this->ocCalendarObject->getRepeatOptions();
    $repeatEndOptions = $this->ocCalendarObject->getEndOptions();
    $repeatMonthOptions = $this->ocCalendarObject->getMonthOptions();
    $repeatYearOptions = $this->ocCalendarObject->getYearOptions();
    $repeatWeeklyOptions = $this->ocCalendarObject->getWeeklyOptions();
    $repeatWeekOfMonthOptions = $this->ocCalendarObject->getWeekofMonth();
    $repeatByYearDayOptions = $this->ocCalendarObject->getByYearDayOptions();
    $repeatByMonthOptions = $this->ocCalendarObject->getByMonthOptions();
    $repeatByWeekNoOptions = $this->ocCalendarObject->getByWeekNoOptions();
    $repeatByMonthDayOptions = $this->ocCalendarObject->getByMonthDayOptions();

    return $this->templateResponse(
      'legacy/calendar/part.newevent',
      [
        'appName' => $this->appName(),
        'appNameTag' => 'app-' . $this->appName,
        'toolTips' => $this->toolTipsService,

        'requesttoken' => \OCP\Util::callRegister(),
        'urlGenerator' => $this->urlGenerator(),

        'calendarid' => $calendarId,
        'calendarOwnerId' => $calendarOptions[0]['userid'],
        'calendarDisplayName' => $calendarOptions[0]['displayname'],
        'calendar_options' => $calendarOptions,

        'access' => 'owner',
        'accessclass' => 'PUBLIC',
        'access_class_options' => $accessClassOptions,
        'repeat_options' => $repeatOptions,
        'repeat_month_options' => $repeatMonthOptions,
        'repeat_weekly_options' => $repeatWeeklyOptions,
        'repeat_end_options' => $repeatEndOptions,
        'repeat_year_options' => $repeatYearOptions,
        'repeat_byyearday_options' => $repeatByYearDayOptions,
        'repeat_bymonth_options' => $repeatByMonthOptions,
        'repeat_byweekno_options' => $repeatByWeekNoOptions,
        'repeat_bymonthday_options' => $repeatByMonthDayOptions,
        'repeat_weekofmonth_options' => $repeatWeekOfMonthOptions,

        'eventuri' => self::SUBTOPIC_NEW,
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
        'protectCategories' => $protectCategories,
        // 'summary' => $summary,
        'title' => $summary,

        'duration' => $duration,
        'default_duration' => $defaultEventDuration,
      ],
    );
  }

  /**
   * Edit an existing event. If $subTopic equals self::SUBTOPIC_CLONE then a
   * new event will be generated on save.
   *
   * @param string $subTopic
   *
   * @return Http\Response
   */
  private function editEventForm(string $subTopic):Http\Response
  {
    // all this mess ...
    $uri = $this->parameterService['uri'];
    $calendarId = $this->parameterService['calendarid'];
    $data = $this->calDavService->getCalendarObject($calendarId, $uri);
    if (empty($data)) {
      return self::grumble($this->l->t("Could not fetch object `%s' from calendar `%s'.", [$uri, $calendarId]));
    }

    if ($data['calendarid'] != $calendarId) {
      return self::grumble($this->l->t("Submitted calendar id `%s' and stored id `%s' for object `%s' do not match.", [$calendarId, $data['calendarid'], $uri]));
    }
    $object = VCalendarService::getVCalendar($data);
    $calendar = $this->calDavService->calendarById($calendarId);
    if (empty($calendar)) {
      return self::grumble($this->l->t("Unable to access calendar with id `%s'.", [$calendarId]));
    }
    $calendarUris = $this->calDavService->calendarUris($calendarId);
    $ownerId = $calendarUris['ownerid'];
    $calendarUri = $calendarUris['shareuri'];
    $object = $this->ocCalendarObject->cleanByAccessClass($ownerId, $object);
    $accessClass = $this->accessClass($object);
    // $permissions = OC_Calendar_App::getPermissions($id, OC_Calendar_App::EVENT, $accessClass);
    $permissions = $calendar->getPermissions();
    switch ($accessClass) {
      case 'PUBLIC':
        break;
      case 'CONFIDENTIAL':
        $permissions &= ~Constants::PERMISSION_READ;
        break;
      case 'PRIVATE':
        $permissions &= ~Constants::PERMISSION_UPDATE;
        break;
    }
    //$permissions &= ~Constants::PERMISSION_UPDATE;
    $this->logError("Permissions: " . $calendar->getPermissions());

    $vEvent = $object->VEVENT;
    $dtstart = $vEvent->DTSTART;
    $dtend = $this->ocCalendarObject->getDTEndFromVEvent($vEvent);

    $summary = (string)$vEvent->SUMMARY;
    $location = (string)$vEvent->LOCATION;
    $description = (string)$vEvent->DESCRIPTION;

    // DATE
    if ($dtstart->hasTime()) {
      // UTC ?
      if (!$dtstart->isFloating()) {
        $timezone = new DateTimeZone($this->getTimezone());
        $newDT = $dtstart->getDateTime();
        $newDT->setTimezone($timezone);
        $dtstart->setDateTime($newDT);
        $newDT = $dtend->getDateTime();
        $newDT->setTimezone($timezone);
        $dtend->setDateTime($newDT);
      } // else it's LOCALTZ/LOCAL

      $startdate = $dtstart->getDateTime()->format('d-m-Y');
      $starttime = $dtstart->getDateTime()->format('H:i');
      $enddate = $dtend->getDateTime()->format('d-m-Y');
      $endtime = $dtend->getDateTime()->format('H:i');
      $allday = false;
    } else {
      // DATE
      $startdate = $dtstart->getDateTime()->format('d-m-Y');
      $starttime = '';
      $dtend->setDateTime($dtend->getDateTime()->modify('-1 day'));
      $enddate = $dtend->getDateTime()->format('d-m-Y');
      $endtime = '';
      $allday = true;
    }

    $duration = $dtend->getDateTime()->getTimestamp() - $dtstart->getDateTime()->getTimestamp();

    $protectCategories = $this->parameterService->getParam('protectCategories', 1);
    $categories = $vEvent->CATEGORIES;
    //$this->logError(print_r($categories, true));
    $lastModified = $vEvent->__get('LAST-MODIFIED');
    if ($lastModified) {
      $lastmodified = $lastModified->getDateTime()->format('U');
    } else {
      $lastmodified = 0;
    }

    $repeat = [];
    // if ($data['repeating'] == 1) {
    if (isset($vEvent->RRULE)) {
      $rrule = explode(';', $vEvent->RRULE);
      $rrulearr = [];
      foreach ($rrule as $rule) {
        list($attr, $val) = explode('=', $rule);
        $rrulearr[$attr] = $val;
      }
      if (!isset($rrulearr['INTERVAL']) || $rrulearr['INTERVAL'] == '') {
        $rrulearr['INTERVAL'] = 1;
      }
      if (array_key_exists('BYDAY', $rrulearr)) {
        if (substr_count($rrulearr['BYDAY'], ',') == 0) {
          if (strlen($rrulearr['BYDAY']) == 2) {
            $repeat['weekdays'] = array($rrulearr['BYDAY']);
          } elseif (strlen($rrulearr['BYDAY']) == 3) {
            $repeat['weekofmonth'] = substr($rrulearr['BYDAY'], 0, 1);
            $repeat['weekdays'] = array(substr($rrulearr['BYDAY'], 1, 2));
          } elseif (strlen($rrulearr['BYDAY']) == 4) {
            $repeat['weekofmonth'] = substr($rrulearr['BYDAY'], 0, 2);
            $repeat['weekdays'] = array(substr($rrulearr['BYDAY'], 2, 2));
          }
        } else {
          $byDayDays = explode(',', $rrulearr['BYDAY']);
          foreach ($byDayDays as $byDayDay) {
            if (strlen($byDayDay) == 2) {
              $repeat['weekdays'][] = $byDayDay;
            } elseif (strlen($byDayDay) == 3) {
              $repeat['weekofmonth'] = substr($byDayDay, 0, 1);
              $repeat['weekdays'][] = substr($byDayDay, 1, 2);
            } elseif (strlen($byDayDay) == 4) {
              $repeat['weekofmonth'] = substr($byDayDay, 0, 2);
              $repeat['weekdays'][] = substr($byDayDay, 2, 2);
            }
          }
        }
      }
      if (array_key_exists('BYMONTHDAY', $rrulearr)) {
        if (substr_count($rrulearr['BYMONTHDAY'], ',') == 0) {
          $repeat['bymonthday'][] = $rrulearr['BYMONTHDAY'];
        } else {
          $bymonthdays = explode(',', $rrulearr['BYMONTHDAY']);
          foreach ($bymonthdays as $bymonthday) {
            $repeat['bymonthday'][] = $bymonthday;
          }
        }
      }
      if (array_key_exists('BYYEARDAY', $rrulearr)) {
        if (substr_count($rrulearr['BYYEARDAY'], ',') == 0) {
          $repeat['byyearday'][] = $rrulearr['BYYEARDAY'];
        } else {
          $byyeardays = explode(',', $rrulearr['BYYEARDAY']);
          foreach ($byyeardays as $yearday) {
            $repeat['byyearday'][] = $yearday;
          }
        }
      }
      if (array_key_exists('BYWEEKNO', $rrulearr)) {
        if (substr_count($rrulearr['BYWEEKNO'], ',') == 0) {
          $repeat['byweekno'][] = (string) $rrulearr['BYWEEKNO'];
        } else {
          $byweekno = explode(',', $rrulearr['BYWEEKNO']);
          foreach ($byweekno as $weekno) {
            $repeat['byweekno'][] = (string) $weekno;
          }
        }
      }
      if (array_key_exists('BYMONTH', $rrulearr)) {
        $months = $this->ocCalendarObject->getByMonthOptions();
        if (substr_count($rrulearr['BYMONTH'], ',') == 0) {
          $repeat['bymonth'][] = $months[(string)$rrulearr['BYMONTH']];
        } else {
          $bymonth = explode(',', $rrulearr['BYMONTH']);
          foreach ($bymonth as $month) {
            $repeat['bymonth'][] = $months[$month];
          }
        }
      }
      switch ($rrulearr['FREQ']) {
        case 'DAILY':
          $repeat['repeat'] = 'daily';
          break;
        case 'WEEKLY':
          if (array_key_exists('BYDAY', $rrulearr) === false) {
            $rrulearr['BYDAY'] = '';
          }
          if ($rrulearr['INTERVAL'] % 2 == 0) {
            $repeat['repeat'] = 'biweekly';
            $rrulearr['INTERVAL'] = $rrulearr['INTERVAL'] / 2;
          } elseif ($rrulearr['BYDAY'] == 'MO,TU,WE,TH,FR') {
            $repeat['repeat'] = 'weekday';
          } else {
            $repeat['repeat'] = 'weekly';
          }
          break;
        case 'MONTHLY':
          $repeat['repeat'] = 'monthly';
          if (array_key_exists('BYDAY', $rrulearr)) {
            $repeat['month'] = 'weekday';
          } else {
            $repeat['month'] = 'monthday';
          }
          break;
        case 'YEARLY':
          $repeat['repeat'] = 'yearly';
          if (array_key_exists('BYMONTH', $rrulearr)) {
            $repeat['year'] = 'bydaymonth';
          } elseif (array_key_exists('BYWEEKNO', $rrulearr)) {
            $repeat['year'] = 'byweekno';
          } elseif (array_key_exists('BYYEARDAY', $rrulearr)) {
            $repeat['year'] = 'byyearday';
          } else {
            $repeat['year'] = 'bydate';
          }
      }
      $repeat['interval'] = $rrulearr['INTERVAL'];
      if (array_key_exists('COUNT', $rrulearr)) {
        $repeat['end'] = 'count';
        $repeat['count'] = $rrulearr['COUNT'];
      } elseif (array_key_exists('UNTIL', $rrulearr)) {
        $repeat['end'] = 'date';
        $endByDateDay = substr($rrulearr['UNTIL'], 6, 2);
        $endByDateMonth = substr($rrulearr['UNTIL'], 4, 2);
        $endByDateYear = substr($rrulearr['UNTIL'], 0, 4);
        $repeat['date'] = $endByDateDay . '-' .  $endByDateMonth . '-' . $endByDateYear;
      } else {
        $repeat['end'] = 'never';
      }
      if (array_key_exists('weekdays', $repeat)) {
        $repeatWeekdaysTmp = [];
        $days = $this->ocCalendarObject->getWeeklyOptions();
        foreach ($repeat['weekdays'] as $weekday) {
          $repeatWeekdaysTmp[] = $days[$weekday];
        }
        $repeat['weekdays'] = $repeatWeekdaysTmp;
      }
    } else {
      $repeat['repeat'] = 'doesnotrepeat';
    }

    // compute the list of all writable calendars of the user
    $calendars = $this->calDavService->getCalendars(true);
    $calendarOptions = [];
    foreach ($calendars as $calendar) {
      $calendarOptions[] = [
        'active' => 1,
        'id' => $calendar->getKey(),
        'displayname' => $calendar->getDisplayName(),
        'userid' => $calendarUris['ownerid'],
        'uri' => $calendarUris['shareuri'],
      ];
    }
    usort($calendarOptions, function($a, $b) use ($calendarId) {
      return strcmp($a['displayname'], $b['displayname']);
    });

    $accessClassOptions = $this->ocCalendarObject->getAccessClassOptions();
    $repeatOptions = $this->ocCalendarObject->getRepeatOptions();
    $repeatEndOptions = $this->ocCalendarObject->getEndOptions();
    $repeatMonthOptions = $this->ocCalendarObject->getMonthOptions();
    $repeatYearOptions = $this->ocCalendarObject->getYearOptions();
    $repeatWeeklyOptions = $this->ocCalendarObject->getWeeklyOptions();
    $repeatWeekOfMonthOptions = $this->ocCalendarObject->getWeekofMonth();
    $repeatByYearDayOptions = $this->ocCalendarObject->getByYearDayOptions();
    $repeatByMonthOptions = $this->ocCalendarObject->getByMonthOptions();
    $repeatByWeekNoOptions = $this->ocCalendarObject->getByWeekNoOptions();
    $repeatByMonthDayOptions = $this->ocCalendarObject->getByMonthDayOptions();

    $template = '';
    if ($subTopic == self::SUBTOPIC_CLONE
        && $permissions & Constants::PERMISSION_READ) {
      $template = 'legacy/calendar/part.newevent';
      $uri = self::SUBTOPIC_NEW;
    } elseif ($permissions & Constants::PERMISSION_UPDATE) {
      $template = 'legacy/calendar/part.editevent';
    } elseif ($permissions & Constants::PERMISSION_READ) {
      $template = 'legacy/calendar/part.showevent';
    } else {
      return self::grumble($this->l->t("Don't know how to react to permissions `%s'", [$permissions]));
    }

    $remoteUrl = $this->urlGenerator()->linkTo('', sprintf('remote.php/dav/calendars/%s/%s/%s', $this->userId(), $calendarUri, $uri));

    $templateParameters = [
      'appName' => $this->appName(),
      'appNameTag' => 'app-' . $this->appName,
      'toolTips' => $this->toolTipsService,

      'requestoken' => \OCP\Util::callRegister(),
      'urlGenerator' => $this->urlGenerator(),
      'categories' => $categories,
      'protectCategories' => $protectCategories,
      'eventuri' => $uri,
      'calendarid' => $calendarId,
      'calendarUri' => $calendarUri,
      'remoteEventUrl' => $remoteUrl,
      'calendarOwnerId' => $ownerId,
      'calendarDisplayName' => $calendar->getDisplayName(),
      'calendar_options' => $calendarOptions,
      'permissions' => $permissions,
      'lastmodified' => $lastmodified,
      'access_class_options' => $accessClassOptions,
      'repeat_options' => $repeatOptions,
      'repeat_month_options' => $repeatMonthOptions,
      'repeat_weekly_options' => $repeatWeeklyOptions,
      'repeat_end_options' => $repeatEndOptions,
      'repeat_year_options' => $repeatYearOptions,
      'repeat_byyearday_options' => $repeatByYearDayOptions,
      'repeat_bymonth_options' => $repeatByMonthOptions,
      'repeat_byweekno_options' => $repeatByWeekNoOptions,
      'repeat_bymonthday_options' => $repeatByMonthDayOptions,
      'repeat_weekofmonth_options' => $repeatWeekOfMonthOptions,

      // 'summary' => $summary,
      'title' => $summary,
      'accessclass' => $accessClass,
      'location' => $location,
      'allday' => $allday,
      'startdate' => $startdate,
      'starttime' => $starttime,
      'enddate' => $enddate,
      'endtime' => $endtime,
      'starttimestamp' => $dtstart->getDateTime()->getTimestamp(),
      'endtimestamp' => $dtend->getDateTime()->getTimestamp(),

      'description' => $description,

      'repeat' => $repeat['repeat'],

      'duration' => $duration,
      'default_duration' => $this->getConfigValue('eventduration', 180) * 60,
    ];
    if ($repeat['repeat'] != 'doesnotrepeat') {
      if (array_key_exists('weekofmonth', $repeat) === false) {
        $repeat['weekofmonth'] = 1;
      }
      $repeatParameters = [
        'repeat_month' => isset($repeat['month']) ? $repeat['month'] : 'monthday',
        'repeat_weekdays' => isset($repeat['weekdays']) ? $repeat['weekdays'] : [],
        'repeat_interval' => isset($repeat['interval']) ? $repeat['interval'] : '1',
        'repeat_end' => isset($repeat['end']) ? $repeat['end'] : 'never',
        'repeat_count' => isset($repeat['count']) ? $repeat['count'] : '10',
        'repeat_weekofmonth' => $repeat['weekofmonth'],
        'repeat_date' => isset($repeat['date']) ? $repeat['date'] : '',
        'repeat_year' => isset($repeat['year']) ? $repeat['year'] : [],
        'repeat_byyearday' => isset($repeat['byyearday']) ? $repeat['byyearday'] : [],
        'repeat_bymonthday' => isset($repeat['bymonthday']) ? $repeat['bymonthday'] : [],
        'repeat_bymonth' => isset($repeat['bymonth']) ? $repeat['bymonth'] : [],
        'repeat_byweekno' => isset($repeat['byweekno']) ? $repeat['byweekno'] : [],
      ];
    } else {
      //Some hidden init Values prevent User Errors
      //init
      $start = $dtstart->getDateTime();
      $tWeekDay = $start->format('l');
      $shortWeekDay = strtoupper(substr($tWeekDay, 0, 2));
      // $transWeekDay = $this->l->t((string)$tWeekDay);
      $tDayOfMonth = $start->format('j');
      $numMonth = $start->format('n');
      // $tMonth = $start->format('F');
      // $transMonth = $this->l->t((string)$tMonth);
      $transByWeekNo = $start->format('W');
      $transByYearDay = $start->format('z');

      $repeatParameters = [
        'repeat_weekdays' => $shortWeekDay, //$transWeekDay,
        'repeat_bymonthday' => $tDayOfMonth,
        'repeat_bymonth' => $numMonth, // $transMonth,
        'repeat_byweekno' => $transByWeekNo,
        'repeat_byyearday' => $transByYearDay,

        'repeat_month' => 'monthday',
        'repeat_interval' => 1,
        'repeat_end' => 'never',
        'repeat_count' => '10',
        'repeat_weekofmonth' => 'auto',
        'repeat_date' => '',
        'repeat_year' => 'bydate',
        'userid' => $ownerId,
      ];
    }
    $templateParameters = array_merge($templateParameters, $repeatParameters);

    $this->logError("returning template " . $template);
    return $this->templateResponse(
      $template,
      $templateParameters,
    );
  }

  /** @return Http\Response */
  private function newEvent():Http\Response
  {
    $errarr = $this->vCalendarService->validateRequest($this->parameterService);
    if ($errarr) {
      //show validate errors
      return self::grumble($this->l->t("Failed to validate event creating request."), $errarr);
    }
    $cal = $this->parameterService['calendar'];
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($this->parameterService);
    $this->logDebug($vCalendar->serialize());
    try {
      $localUri = $this->calDavService->createCalendarObject($cal, null, $vCalendar);
      $this->logError(__METHOD__ . ": created object with uri " . $localUri);
    } catch (Throwable $t) {
      $this->logException($t);
      return self::grumble($this->exceptionChainData($t));
    }
    return self::valueResponse($localUri, $this->l->t("Calendar object successfully created."));
  }

  /** @return Http\Response */
  private function editEvent():Http\Response
  {
    $errarr = $this->vCalendarService->validateRequest($this->parameterService);
    if ($errarr) {
      //show validate errors
      return self::grumble($this->l->t("Failed to validate event updating request."), $errarr);
    }
    $uri = $this->parameterService['uri'];
    $calendarId = $this->parameterService['calendarid'];
    $data = $this->calDavService->getCalendarObject($calendarId, $uri);
    if (empty($data)) {
      return self::grumble($this->l->t("Could not fetch object `%s' from calendar `%s'.", [$uri, $calendarId]));
    }
    $vCalendar = $data['calendardata'];
    $lastModifiedSubmitted = $this->parameterService['lastmodified'];
    $lastModified = $vCalendar->VEVENT->__get('LAST-MODIFIED');
    if ($lastModified && $lastModifiedSubmitted != $lastModified->getDateTime()->format('U')) {
      return self::grumble($this->l->t('Race-condition, event was modified in between.'));
    }

    $vCalendar = $this->vCalendarService->updateVCalendarFromRequest($vCalendar, $this->parameterService);

    if ($data['calendarid'] != $calendarId) {
      $this->calDavService->deleteCalendarObject($data['calendarid'], $uri);
      $this->calDavService->createCalendarObject($data['calendarid'], $uri, $vCalendar);
    } else {
      $this->calDavService->updateCalendarObject($data['calendarid'], $uri, $vCalendar);
    }
    return self::response($this->l->t("Successfully updated `%s'.", [$uri]));
  }

  /** @return Http\Response */
  private function deleteEvent():Http\Response
  {
    $uri = $this->parameterService['uri'];
    $calendarId = $this->parameterService['calendarid'];
    $this->calDavService->deleteCalendarObject($calendarId, $uri);
    return self::response($this->l->t("Successfully deleted `%s'.", [$uri]));
  }

  /**
   * @return Http\Response
   *
   * @NoAdminRequired
   */
  public function exportEvent():Http\Response
  {
    $calendarId = $this->parameterService['calendarid'];
    $uri = $this->parameterService['eventuri'];
    $data = $this->calDavService->getCalendarObject($calendarId, $uri);
    if (empty($data)) {
      return self::grumble(
        $this->l->t("Could not fetch object `%s' from calendar `%s'.", [$uri, $calendarId]),
        null, Http::STATUS_FORBIDDEN);
    }
    if ($data['calendarid'] != $calendarId) {
      return self::grumble($this->l->t("Submitted calendar id `%s' and stored id `%s' for object `%s' do not match.", [$calendarId, $data['calendarid'], $uri]));
    }
    $object = $data['calendardata'];
    $calendar = $this->calDavService->calendarById($calendarId);
    if (empty($calendar)) {
      return self::grumble(
        $this->l->t("Unable to access calendar with id `%s'.", [$calendarId]),
        null, Http::STATUS_FORBIDDEN);
    }
    $calendarUris = $this->calDavService->calendarUris($calendarId);
    $ownerId = $calendarUris['ownerid'];
    $object = $this->ocCalendarObject->cleanByAccessClass($ownerId, $object);
    $accessClass = $this->accessClass($object);
    $permissions = $calendar->getPermissions();
    switch ($accessClass) {
      case 'PUBLIC':
        break;
      case 'CONFIDENTIAL':
        $permissions &= ~Constants::PERMISSION_READ;
        break;
      case 'PRIVATE':
        $permissions &= ~Constants::PERMISSION_UPDATE;
        break;
    }

    $this->logError("Permissions: " . $permissions);

    return $this->dataDownloadResponse(
      $this->generateEvent($uri, $object, $ownerId, $permissions),
      $uri,
      'text/calendar');
  }

  /**
   * Exports an event and convert all times to UTC
   *
   * @param string $uri
   *
   * @param VCalendar $vObject
   *
   * @param string $ownerId
   *
   * @param int $permissions
   *
   * @return string
   */
  private function generateEvent(string $uri, VCalendar $vObject, string $ownerId, int $permissions):string
  {
    $return = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:Nextloud cafevdb " . $this->appVersion() . "\nX-WR-CALNAME:" . $uri . "\n";
    $return .= $this->generateEventData($vObject, $ownerId, $permissions);
    $return .= "END:VCALENDAR";
    return $this->fixLineBreaks($return);
  }

  /**
   * Serializes the VEVENT/VTODO/VJOURNAL with UTC dates.
   *
   * @param VCalendar $vObject
   *
   * @param string $ownerId
   *
   * @param int $permissions
   *
   * @return string
   */
  private function generateEventData(VCalendar $vObject, string $ownerId, int $permissions):string
  {
    if (!$vObject) {
      return false;
    }
    if ($ownerId != $this->userId() && !($permissions & Constants::PERMISSION_READ)) {
      return '';
    }
    if ($vObject->VEVENT) {
      return $vObject->VEVENT->serialize();
    }
    if ($vObject->VTODO) {
      return $vObject->VTODO->serialize();
    }
    if ($vObject->VJOURNAL) {
      return $vObject->VJOURNAL->serialize();
    }
    return '';
  }

  /**
   * @param VCalendar $vObject
   *
   * @return string
   */
  private function accessClass(VCalendar $vObject):string
  {
    if ($vObject->VEVENT && $vObject->VEVENT->CLASS) {
      return $vObject->VEVENT->CLASS->getValue();
    }
    if ($vObject->VTODO && $vObject->VTODO->CLASS) {
      return $vObject->VTODO->CLASS->getValue();
    }
    if ($vObject->VJOURNAL && $vObject->VJOURNAL->CLASS) {
      return $vObject->VJOURNAL->CLASS->getValue();
    }
    return 'PUBLIC';
  }

  /**
   * Fixes new line breaks (fixes problems with Apple iCal).
   *
   * @param string $string to fix.
   *
   * @return string
   */
  private function fixLineBreaks(string $string)
  {
    $string = str_replace("\r\n", "\n", $string);
    $string = str_replace("\r", "\n", $string);
    $string = str_replace("\n", "\r\n", $string);
    return $string;
  }
}

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
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\Constants;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ConfigCheckService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\CalDavService;
use OCA\CAFEVDB\Service\VCalendarService;

/**Serves the requests issued by the old OC v8 event popups.*/
class LegacyEventsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;

  const ERROR_TEMPLATE = "errorpage";

  /** @var \OCP\IL10N */
  private $l;

  /** @var \OCP\IURLGenerator */
  private $urlGenerator;

  /** @var RequestParameterService */
  private $parameterService;

  /** @var ProjectService */
  private $projectService;

  /** @var ConfigCheckService */
  private $configCheckService;

  /** @var CalDavService */
  private $calDavService;

  /** @var VCalendarService */
  private $vCalendarService;

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
    VCalendarService $vCalendarService,
    \OCP\IURLGenerator $urlGenerator
  ) {
    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->configCheckService = $configCheckService;
    $this->parameterService = $parameterService;
    $this->projectService = $projectService;
    $this->calDavService = $calDavService;
    $this->vCalendarService = $vCalendarService;
    $this->ocCalendarObject = $vCalendarService->legacyEventObject();
    $this->urlGenerator = $urlGenerator;

    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   */
  public function serviceSwitch($topic, $subTopic)
  {
    switch ($topic) {
    case 'forms':
      switch ($subTopic) {
      case 'new':
        return $this->newEventForm();
      case 'edit':
        return $this->editEventForm();
      default:
        break;
      }
      break;
    case 'actions':
      switch ($subTopic) {
      case 'new':
        return $this->newEvent();
      case 'edit':
        return $this->editEvent();
      case 'delete':
        return $this->deleteEvent();
      case 'export':
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

  private function newEventForm()
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
    $protectCategories = $this->parameterService['protectCategories'];
    $calendarUri  = $eventKind.'calendar';
    $calendarName = $this->getConfigValue($calendarUri, ucfirst($this->l->t($eventKind)));
    $calendarId   = $this->getConfigValue($calendarUri.'id', -1);
    $shareOwner   = $this->getConfigValue('shareowner');

    // Default title for the event
    $summary        = $this->l->t($eventKind).', '.$projectName;

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
    $calendarOptions = [];
    foreach ($calendars as $calendar) {
      [,,$userId] = explode('/', $this->calDavService->calendarPrincipalUri($calendar->getKey()));
      $calendarOptions[] = [
        'active' => 1,
        'id' => $calendar->getKey(),
        'displayname' => $calendar->getDisplayName(),
        'userid' => $userId,
      ];
    }
    usort($calendarOptions, function($a, $b) use ($calendarId) {
      if ($a['id'] == $b['id']) {
        return 0;
      } else if ($a['id'] == $calendarId) {
        return -1;
      } else if ($b['id'] == $calendarId) {
        return 1;
      }
      return strcmp($a['displayname'], $b['displayname']);
    });

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
        'csrfToken' => \OCP\Util::callRegister(),
        'urlGenerator' => $this->urlGenerator,

        'calendarid' => $calendarId,
        'calendarOwnerId' => $calendarOptions[0]['userid'],
        'calendarDisplayName' => $calendarOptions[0]['displayname'],
        'calendar_options' => $calendarOptions,

        'access' => 'owner',
        'accessclass' => 'PUBLIC',
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

        'eventuri' => 'new',
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
        'summary' => $summary,
      ],
      'blank');
  }

  private function editEventForm()
  {
    // all this mess ...
    $uri = $this->parameterService['uri'];
    $calendarId = $this->parameterService['calendarid'];
    $data = $this->calDavService->getCalendarObject($calendarId, $uri);
    if (empty($data)) {
      return self::grumble($this->l->t("Could not fetch object `%s' from calendar `%s'.", [$uri, $calendarId]));
    }
    //$this->logError(print_r($data, true));
    if ($data['calendarid'] != $calendarId) {
      return self::grumble($this->l->t("Submitted calendar id `%s' and stored id `%s' for object `%s' do not match.", [$calendarId, $data['calendarid'], $uri]));
    }
    $eventId = $data['id'];
    $object = \Sabre\VObject\Reader::read($data['calendardata']);
    $calendar = $this->calDavService->calendarById($calendarId);
    if (empty($calendar)) {
      return self::grumble($this->l->t("Unable to access calendar with id `%s'.", [$calendarId]));
    }
    [,,$ownerId] = explode('/', $this->calDavService->calendarPrincipalUri($calendarId));
    $this->logError("ownerId: " . $ownerId);
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
		$timezone = new \DateTimeZone($this->getTimezone());
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

    $protectCategories = $this->parameterService['protectCategories'];
    $categories = $vEvent->CATEGORIES;
    //$this->logError(print_r($categories, true));
    $last_modified = $vEvent->__get('LAST-MODIFIED');
    if ($last_modified) {
      $lastmodified = $last_modified->getDateTime()->format('U');
    }else{
      $lastmodified = 0;
    }

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
          $byday_days = explode(',', $rrulearr['BYDAY']);
          foreach ($byday_days as $byday_day) {
            if (strlen($byday_day) == 2) {
              $repeat['weekdays'][] = $byday_day;
            } elseif (strlen($byday_day) == 3) {
              $repeat['weekofmonth'] = substr($byday_day , 0, 1);
              $repeat['weekdays'][] = substr($byday_day , 1, 2);
            } elseif (strlen($byday_day) == 4) {
              $repeat['weekofmonth'] = substr($byday_day , 0, 2);
              $repeat['weekdays'][] = substr($byday_day , 2, 2);
            }
          }
		}
      }
      if (array_key_exists('BYMONTHDAY', $rrulearr)) {
		if (substr_count($rrulearr['BYMONTHDAY'], ',') == 0) {
          $repeat['bymonthday'][] = $rrulearr['BYMONTHDAY'];
		}else{
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
          foreach ($byyeardays  as $yearday) {
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
        }else{
          $repeat['month'] = 'monthday';
        }
        break;
      case 'YEARLY':
        $repeat['repeat'] = 'yearly';
        if (array_key_exists('BYMONTH', $rrulearr)) {
          $repeat['year'] = 'bydaymonth';
        }elseif (array_key_exists('BYWEEKNO', $rrulearr)) {
          $repeat['year'] = 'byweekno';
        }elseif (array_key_exists('BYYEARDAY', $rrulearr)) {
          $repeat['year'] = 'byyearday';
        }else {
          $repeat['year'] = 'bydate';
        }
      }
      $repeat['interval'] = $rrulearr['INTERVAL'];
      if (array_key_exists('COUNT', $rrulearr)) {
		$repeat['end'] = 'count';
		$repeat['count'] = $rrulearr['COUNT'];
      } elseif (array_key_exists('UNTIL', $rrulearr)) {
		$repeat['end'] = 'date';
		$endbydate_day = substr($rrulearr['UNTIL'], 6, 2);
		$endbydate_month = substr($rrulearr['UNTIL'], 4, 2);
		$endbydate_year = substr($rrulearr['UNTIL'], 0, 4);
		$repeat['date'] = $endbydate_day . '-' .  $endbydate_month . '-' . $endbydate_year;
      } else {
		$repeat['end'] = 'never';
      }
      if (array_key_exists('weekdays', $repeat)) {
		$repeat_weekdays_ = [];
		$days = $this->ocCalendarObject->getWeeklyOptions();
		foreach ($repeat['weekdays'] as $weekday) {
          $repeat_weekdays_[] = $days[$weekday];
		}
		$repeat['weekdays'] = $repeat_weekdays_;
      }
    } else {
      $repeat['repeat'] = 'doesnotrepeat';
    }

    // compute the list of all writable calendars of the user
    $calendars = $this->calDavService->getCalendars(true);
    $calendarOptions = [];
    foreach ($calendars as $calendar) {
      [,,$userId] = explode('/', $this->calDavService->calendarPrincipalUri($calendar->getKey()));
      $calendarOptions[] = [
        'active' => 1,
        'id' => $calendar->getKey(),
        'displayname' => $calendar->getDisplayName(),
        'userid' => $userId,
      ];
    }
    usort($calendarOptions, function($a, $b) use ($calendarId) {
      return strcmp($a['displayname'], $b['displayname']);
    });

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

    $template = '';
    if ($permissions & Constants::PERMISSION_UPDATE) {
      $template = 'legacy/calendar/part.editevent';
    } elseif ($permissions & Constants::PERMISSION_READ) {
      $template = 'legacy/calendar/part.showevent';
    } else {
      return self::grumble($this->l->t("Don't know how to react to permissions `%s'", [$permissions]));
    }

    $templateParameters = [
      'csrfToken' => \OCP\Util::callRegister(),
      'urlGenerator' => $this->urlGenerator,
      'categories' => $categories,
      'protectCategories' => $protectCategories,

      'eventuri' => $uri,
      'calendarid' => $calendarId,
      'calendarOwnerId' => $ownerId,
      'calendarDisplayName' => $calendar->getDisplayName(),
      'calendar_options' => $calendarOptions,
      'permissions' => $permissions,
      'lastmodified' => $lastmodified,
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

      'summary' => $summary,
      'accessclass' => $accessClass,
      'location' => $location,
      'allday' => $allday,
      'startdate' => $startdate,
      'starttime' => $starttime,
      'enddate' => $enddate,
      'endtime' => $endtime,
      'description' => $description,

      'repeat' => $repeat['repeat']
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
      $transWeekDay = $this->l->t((string)$tWeekDay);
      $tDayOfMonth = $start->format('j');
      $numMonth = $start->format('n');
      $tMonth = $start->format('F');
      $transMonth = $this->l->t((string)$tMonth);
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
    return new TemplateResponse(
      $this->appName(),
      $template,
      $templateParameters,
    'blank');
  }

  private function newEvent()
  {
    $errarr = $this->vCalendarService->validateRequest($this->parameterService);
    if ($errarr) {
      //show validate errors
      return self::grumble($this->l->t("Failed to validate event creating request."), $errarr);
    }
    $cal = $this->parameterService['calendar'];
    $vCalendar = $this->vCalendarService->createVCalendarFromRequest($this->parameterService);
    $this->logError($vCalendar->serialize());
    try {
      $localUri = $this->calDavService->createCalendarObject($cal, null, $vCalendar);
      $this->logError(__METHOD__ . ": created object with uri " . $localUri);
    } catch(\Throwable $t) {
      $this->logException($t);
      return self::grumble($this->exceptionChainData($t));
    }
    return self::valueResponse($localUri, $this->l->t("Calendar object successfully created."));
  }

  private function editEvent()
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
    $this->logError(print_r($data, true));
    $vCalendar = \Sabre\VObject\Reader::read($data['calendardata']);
    $lastModifiedSubmitted = $this->parameterService['lastmodified'];
    $lastModified = $vCalendar->VEVENT->__get('LAST-MODIFIED');
    if ($lastModified && $lastModifiedSubmitted != $lastModified->getDateTime()->format('U')) {
      return self::grumble($this->l->t('Race-condition, event was modified in between.'));
    }

    $vCalendar = $this->vCalendarService->updateVCalendarFromRequest($this->parameterService, $vCalendar);

    if ($data['calendarid'] != $calendarId) {
      $this->calDavService->deleteCalendarObject($data['calendarid'], $uri);
      $this->calDavService->createCalendarObject($data['calendarid'], $uri,  $vCalendar);
    } else {
      $this->calDavService->updateCalendarObject($data['calendarid'], $uri, $vCalendar);
    }
    return self::response($this->l->t("Successfully updated `%s'.", [$uri]));
  }

  private function deleteEvent()
  {
    $uri = $this->parameterService['uri'];
    $calendarId = $this->parameterService['calendarid'];
    $this->calDavService->deleteCalendarObject($calendarId, $uri);
    return self::response($this->l->t("Successfully deleted `%s'.", [$uri]));
  }

  /**
   * @NoAdminRequired
   */
  public function exportEvent()
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
    $eventId = $data['id'];
    $object = \Sabre\VObject\Reader::read($data['calendardata']);
    $calendar = $this->calDavService->calendarById($calendarId);
    if (empty($calendar)) {
      return self::grumble(
        $this->l->t("Unable to access calendar with id `%s'.", [$calendarId]),
        null, Http::STATUS_FORBIDDEN);
    }
    [,,$ownerId] = explode('/', $this->calDavService->calendarPrincipalUri($calendarId));
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

    return new DataDownloadResponse(
      $this->generateEvent($uri, $object, $ownerId, $permissions),
      $uri,
      'text/calendar');
  }

  private function notImplemented($method)
  {
    return self::grumble($this->l->t("Method %s is not yet implemented.", [$method]));
  }

  /**
   * @brief exports an event and convert all times to UTC
   * @param integer $id id of the event
   * @return string
   */
  private function generateEvent($uri, $vObject, $ownerId, $permissions)
  {
    $return = "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:Nextloud cafevdb " . $this->appVersion() . "\nX-WR-CALNAME:" . $uri . "\n";
    $return .= $this->generateEventData($vObject, $ownerId, $permissions);
    $return .= "END:VCALENDAR";
    return $this->fixLineBreaks($return);
  }

  /**
   * @brief generates the VEVENT/VTODO/VJOURNAL with UTC dates
   * @param array $event
   * @return string
   */
  private function generateEventData($vObject, $ownerId, $permissions)
  {
    if(!$vObject){
      return false;
    }
    if ($ownerId != $this->userId() && !($permissions & Constants::PERMISSION_READ)) {
        return '';
    }
    if($vObject->VEVENT){
      return $vObject->VEVENT->serialize();
    }
    if($vObject->VTODO){
      return $vObject->VTODO->serialize();
    }
    if($vObject->VJOURNAL){
      return $vObject->VJOURNAL->serialize();
    }
    return '';
  }

  private function accessClass($vObject)
  {
    if($vObject->VEVENT && $vObject->VEVENT->CLASS) {
      return $vObject->VEVENT->CLASS->getValue();
    }
    if($vObject->VTODO && $vObject->VTODO->CLASS) {
      return $vObject->VTODO->CLASS->getValue();
    }
    if($vObject->VJOURNAL && $vObject->VJOURNAL->CLASS) {
      return $vObject->VJOURNAL->CLASS->getValue();
    }
    return 'PUBLIC';
  }

  /**
   * @brief fixes new line breaks
   * (fixes problems with Apple iCal)
   * @param string $string to fix
   * @return string
   */
  private function fixLineBreaks($string) {
    $string = str_replace("\r\n", "\n", $string);
    $string = str_replace("\r", "\n", $string);
    $string = str_replace("\n", "\r\n", $string);
    return $string;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

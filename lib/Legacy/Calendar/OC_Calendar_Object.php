<?php
/**
 * Copyright (c) 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *               Copied and stripped down for my orchestra admin app.
 *
 * Copyright (c) 2011 Jakob Sack <mail@jakobsack.de>
 * Copyright (c) 2012 Bart Visscher <bartv@thisnet.nl>
 * Copyright (c) 2012 Georg Ehrke <ownclouddev at georgswebsite dot de>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */
/**
 *
 * The following SQL statement is just a help for developers and will not be
 * executed!
 *
 * CREATE TABLE clndr_objects (
 *     id INTEGER UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
 *     calendarid INTEGER UNSIGNED NOT NULL,
 *     objecttype VARCHAR(40) NOT NULL,
 *     startdate DATETIME,
 *     enddate DATETIME,
 *     repeating INT(1),
 *     summary VARCHAR(255),
 *     calendardata TEXT,
 *     uri VARCHAR(100),
 *     lastmodified INT(11)
 * );
 *
 */

/********************************************************************
 *
 * Compat Layer
 *
 * - comment everyting not needed
 * - make it non-static
 * - inject our general config stuff
 */
namespace OCA\CAFEVDB\Legacy\Calendar;

use OCA\CAFEVDB\Service\ConfigService;

/*
 *
 *******************************************************************/

// Reduced to a minimal working setup just providing VCalendar entries
// from the old Owncloud event form requests.
/**
 * This class manages our calendar objects
 */
class OC_Calendar_Object
{
  /****************************************************************
   *
   * Compat Layer
   *
   */
  use \OCA\CAFEVDB\Traits\ConfigTrait;

  public function __construct(ConfigService $configService)
  {
    $this->configService = $configService;
    $this->l = $this->l10n();
  }

  /**
   * @brief returns the DTEND of an $vevent object
   * @param object $vevent vevent object
   * @return object
   */
  public function getDTEndFromVEvent($vevent) {
    if ($vevent->DTEND) {
      $dtend = $vevent->DTEND;
    }else{
      $dtend = clone $vevent->DTSTART;
      // clone creates a shallow copy, also clone DateTime
      $dtend->setDateTime(clone $dtend->getDateTime());
      if ($vevent->DURATION) {
        $duration = strval($vevent->DURATION);
        $invert = 0;
        if ($duration[0] == '-') {
          $duration = substr($duration, 1);
          $invert = 1;
        }
        if ($duration[0] == '+') {
          $duration = substr($duration, 1);
        }
        $interval = new DateInterval($duration);
        $interval->invert = $invert;
        $dtend->getDateTime()->add($interval);
      }
    }
    return $dtend;
  }

  /**
   * @brief Remove all properties which should not be exported for the AccessClass Confidential
   * @param string $owner The UID of the owner of the object.
   * @param Sabre_VObject $vobject Sabre VObject
   * @return object
   */
  public function cleanByAccessClass($ownerId, $vobject) {

    // Do not clean your own calendar
    if($ownerId === $this->userId()) {
      return $vobject;
    }

    if(isset($vobject->VEVENT)) {
      $velement = $vobject->VEVENT;
    }
    elseif(isset($vobject->VJOURNAL)) {
      $velement = $vobject->VJOURNAL;
    }
    elseif(isset($vobject->VTODO)) {
      $velement = $vobject->VTODO;
    }

    if(isset($velement->CLASS) && $velement->CLASS->getValue() == 'CONFIDENTIAL') {
      foreach ($velement->children as &$property) {
        switch($property->name) {
          case 'CREATED':
          case 'DTSTART':
          case 'RRULE':
          case 'DURATION':
          case 'DTEND':
          case 'CLASS':
          case 'UID':
            break;
          case 'SUMMARY':
            $property->setValue($this->l->t('Busy'));
            break;
          default:
            $velement->__unset($property->name);
            unset($property);
            break;
        }
      }
    }
    return $vobject;
  }

  /**
   * @brief returns the options for the access class of an event
   * @return array - valid inputs for the access class of an event
   */
  public function getAccessClassOptions() {
    return array(
      'PUBLIC'       => (string)$this->l->t('Show full event'),
      'CONFIDENTIAL' => (string)$this->l->t('Show only busy'),
      'PRIVATE'      => (string)$this->l->t('Hide event')
    );
  }

  /**
   * @brief returns the options for the repeat rule of an repeating event
   * @return array - valid inputs for the repeat rule of an repeating event
   */
  public function getRepeatOptions() {
    return array(
      'doesnotrepeat' => (string)$this->l->t('Does not repeat'),
      'daily'         => (string)$this->l->t('Daily'),
      'weekly'        => (string)$this->l->t('Weekly'),
      'weekday'       => (string)$this->l->t('Every Weekday'),
      'biweekly'      => (string)$this->l->t('Bi-Weekly'),
      'monthly'       => (string)$this->l->t('Monthly'),
      'yearly'        => (string)$this->l->t('Yearly')
    );
  }

  /**
   * @brief returns the options for the end of an repeating event
   * @return array - valid inputs for the end of an repeating events
   */
  public function getEndOptions() {
    $l10n = $this->l;
    return array(
      'never' => (string)$this->l->t('never'),
      'count' => (string)$this->l->t('by occurrences'),
      'date'  => (string)$this->l->t('by date')
    );
  }

  /**
   * @brief returns the options for an monthly repeating event
   * @return array - valid inputs for monthly repeating events
   */
  public function getMonthOptions() {
    return array(
      'monthday' => (string)$this->l->t('by monthday'),
      'weekday'  => (string)$this->l->t('by weekday')
    );
  }

  /**
   * @brief returns the options for an weekly repeating event
   * @return array - valid inputs for weekly repeating events
   */
  public function getWeeklyOptions() {
    return array(
      'MO' => (string)$this->l->t('Monday'),
      'TU' => (string)$this->l->t('Tuesday'),
      'WE' => (string)$this->l->t('Wednesday'),
      'TH' => (string)$this->l->t('Thursday'),
      'FR' => (string)$this->l->t('Friday'),
      'SA' => (string)$this->l->t('Saturday'),
      'SU' => (string)$this->l->t('Sunday')
    );
  }

  /**
   * @brief returns the options for an monthly repeating event which occurs on specific weeks of the month
   * @return array - valid inputs for monthly repeating events
   */
  public function getWeekofMonth() {
    return array(
      'auto' => (string)$this->l->t('events week of month'),
      '1' => (string)$this->l->t('first'),
      '2' => (string)$this->l->t('second'),
      '3' => (string)$this->l->t('third'),
      '4' => (string)$this->l->t('fourth'),
      '5' => (string)$this->l->t('fifth'),
      '-1' => (string)$this->l->t('last')
    );
  }

  /**
   * @brief returns the options for an yearly repeating event which occurs on specific days of the year
   * @return array - valid inputs for yearly repeating events
   */
  public function getByYearDayOptions() {
    $return = array();
    foreach(range(1,366) as $num) {
      $return[(string) $num] = (string) $num;
    }
    return $return;
  }

  /**
   * @brief returns the options for an yearly or monthly repeating event which occurs on specific days of the month
   * @return array - valid inputs for yearly or monthly repeating events
   */
  public function getByMonthDayOptions() {
    $return = array();
    foreach(range(1,31) as $num) {
      $return[(string) $num] = (string) $num;
    }
    return $return;
  }

  /**
   * @brief returns the options for an yearly repeating event which occurs on specific month of the year
   * @return array - valid inputs for yearly repeating events
   */
  public function getByMonthOptions() {
    return array(
      '1'  => (string)$this->l->t('January'),
      '2'  => (string)$this->l->t('February'),
      '3'  => (string)$this->l->t('March'),
      '4'  => (string)$this->l->t('April'),
      '5'  => (string)$this->l->t('May'),
      '6'  => (string)$this->l->t('June'),
      '7'  => (string)$this->l->t('July'),
      '8'  => (string)$this->l->t('August'),
      '9'  => (string)$this->l->t('September'),
      '10' => (string)$this->l->t('October'),
      '11' => (string)$this->l->t('November'),
      '12' => (string)$this->l->t('December')
    );
  }

  /**
   * @brief returns the options for an yearly repeating event
   * @return array - valid inputs for yearly repeating events
   */
  public function getYearOptions() {
    return array(
      'bydate' => (string)$this->l->t('by events date'),
      'byyearday' => (string)$this->l->t('by yearday(s)'),
      'byweekno'  => (string)$this->l->t('by weeknumber(s)'),
      'bydaymonth'  => (string)$this->l->t('by day and month')
    );
  }

  /**
   * @brief returns the options for an yearly repeating event which occurs on specific week numbers of the year
   * @return array - valid inputs for yearly repeating events
   */
  public function getByWeekNoOptions() {
    return range(1, 52);
  }

  // /**
  //  * @brief validates a request
  //  * @param array $request
  //  * @return mixed (array / boolean)
  //  */
  public function validateRequest($request) {
    $errnum = 0;
    $errarr = array('summary'=>'false', 'cal'=>'false', 'from'=>'false', 'fromtime'=>'false', 'to'=>'false', 'totime'=>'false', 'endbeforestart'=>'false');
    if($request['summary'] == '') {
      $errarr['summary'] = 'true';
      $errnum++;
    }

    $fromday = substr($request['from'], 0, 2);
    $frommonth = substr($request['from'], 3, 2);
    $fromyear = substr($request['from'], 6, 4);
    if(!checkdate($frommonth, $fromday, $fromyear)) {
      $errarr['from'] = 'true';
      $errnum++;
    }
    $allday = isset($request['allday']);
    if(!$allday && $this->checkTime(urldecode($request['fromtime']))) {
      $errarr['fromtime'] = 'true';
      $errnum++;
    }

    $today = substr($request['to'], 0, 2);
    $tomonth = substr($request['to'], 3, 2);
    $toyear = substr($request['to'], 6, 4);
    if(!checkdate($tomonth, $today, $toyear)) {
      $errarr['to'] = 'true';
      $errnum++;
    }
    if($request['repeat'] != 'doesnotrepeat') {
      if(($request['interval'] !== strval(intval($request['interval']))) || intval($request['interval']) < 1) {
        $errarr['interval'] = 'true';
        $errnum++;
      }
      if(array_key_exists('repeat', $request) && !array_key_exists($request['repeat'], $this->getRepeatOptions())) {
        $errarr['repeat'] = 'true';
        $errnum++;
      }
      if(array_key_exists('advanced_month_select', $request) && !array_key_exists($request['advanced_month_select'], $this->getMonthOptions())) {
        $errarr['advanced_month_select'] = 'true';
        $errnum++;
      }
      if(array_key_exists('advanced_year_select', $request) && !array_key_exists($request['advanced_year_select'], $this->getYearOptions())) {
        $errarr['advanced_year_select'] = 'true';
        $errnum++;
      }
      if(array_key_exists('weekofmonthoptions', $request) && !array_key_exists($request['weekofmonthoptions'], $this->getWeekofMonth())) {
        $errarr['weekofmonthoptions'] = 'true';
        $errnum++;
      }
      if($request['end'] != 'never') {
        if(!array_key_exists($request['end'], $this->getEndOptions())) {
          $errarr['end'] = 'true';
          $errnum++;
        }
        if($request['end'] == 'count' && is_nan($request['byoccurrences'])) {
          $errarr['byoccurrences'] = 'true';
          $errnum++;
        }
        if($request['end'] == 'date') {
          list($bydate_day, $bydate_month, $bydate_year) = explode('-', $request['bydate']);
          if(!checkdate($bydate_month, $bydate_day, $bydate_year)) {
            $errarr['bydate'] = 'true';
            $errnum++;
          }
        }
      }
      if(array_key_exists('weeklyoptions', $request)) {
        foreach($request['weeklyoptions'] as $option) {
          if(!in_array($option, $this->getWeeklyOptions())) {
            $errarr['weeklyoptions'] = 'true';
            $errnum++;
          }
        }
      }
      if(array_key_exists('byyearday', $request)) {
        foreach($request['byyearday'] as $option) {
          if(!array_key_exists($option, $this->getByYearDayOptions())) {
            $errarr['byyearday'] = 'true';
            $errnum++;
          }
        }
      }
      if(array_key_exists('weekofmonthoptions', $request)) {
        if(is_nan((double)$request['weekofmonthoptions'])) {
          $errarr['weekofmonthoptions'] = 'true';
          $errnum++;
        }
      }
      if(array_key_exists('bymonth', $request)) {
        foreach($request['bymonth'] as $option) {
          if(!in_array($option, $this->getByMonthOptions())) {
            $errarr['bymonth'] = 'true';
            $errnum++;
          }
        }
      }
      if(array_key_exists('byweekno', $request)) {
        foreach($request['byweekno'] as $option) {
          if(!in_array($option, $this->getByWeekNoOptions())) {
            $errarr['byweekno'] = 'true';
            $errnum++;
          }
        }
      }
      if(array_key_exists('bymonthday', $request)) {
        foreach($request['bymonthday'] as $option) {
          if(!array_key_exists($option, $this->getByMonthDayOptions())) {
            $errarr['bymonthday'] = 'true';
            $errnum++;
          }
        }
      }
    }
    if(!$allday && $this->checkTime(urldecode($request['totime']))) {
      $errarr['totime'] = 'true';
      $errnum++;
    }
    if($today < $fromday && $frommonth == $tomonth && $fromyear == $toyear) {
      $errarr['endbeforestart'] = 'true';
      $errnum++;
    }
    if($today == $fromday && $frommonth > $tomonth && $fromyear == $toyear) {
      $errarr['endbeforestart'] = 'true';
      $errnum++;
    }
    if($today == $fromday && $frommonth == $tomonth && $fromyear > $toyear) {
      $errarr['endbeforestart'] = 'true';
      $errnum++;
    }
    if(!$allday && $fromday == $today && $frommonth == $tomonth && $fromyear == $toyear) {
      list($tohours, $tominutes) = explode(':', $request['totime']);
      list($fromhours, $fromminutes) = explode(':', $request['fromtime']);
      if($tohours < $fromhours) {
        $errarr['endbeforestart'] = 'true';
        $errnum++;
      }
      if($tohours == $fromhours && $tominutes < $fromminutes) {
        $errarr['endbeforestart'] = 'true';
        $errnum++;
      }
    }
    if ($errnum)
    {
      return $errarr;
    }
    return false;
  }

  /**
   * @brief validates time
   * @param string $time
   * @return boolean
   */
  protected static function checkTime($time) {
    if(strpos($time, ':') === false ) {
      return true;
    }
    list($hours, $minutes) = explode(':', $time);
    return empty($time)
      || $hours < 0 || $hours > 24
      || $minutes < 0 || $minutes > 60;
  }

  /**
   * @brief creates an VCalendar Object from the request data
   * @param array $request
   * @return object created $vcalendar
   */
  public function createVCalendarFromRequest($request) {
    $vcalendar = new \Sabre\VObject\Component\VCalendar();
    $vcalendar->PRODID = 'ownCloud Calendar';
    $vcalendar->VERSION = '2.0';

    $vevent = $vcalendar->createComponent('VEVENT');
    $vcalendar->add($vevent);

    $now = new \DateTime('now');
    $now->setTimeZone(new \DateTimeZone('UTC'));
    $vevent->CREATED = $now;

    // $uid = substr(md5(rand().time()), 0, 10);
    $uid = \Sabre\VObject\UUIDUtil::getUUID();

    $vevent->UID = $uid;
    return $this->updateVCalendarFromRequest($request, $vcalendar);
  }

  /**
   * @brief updates an VCalendar Object from the request data
   * @param array $request
   * @param object $vcalendar
   * @return object updated $vcalendar
   */
  public function updateVCalendarFromRequest($request, $vcalendar) {
    $accessclass = isset($request["accessclass"]) ? $request["accessclass"] : null;
    $summary = $request["summary"];
    $location = $request["location"];
    $categories = explode(',', $request["categories"]);
    $allday = isset($request["allday"]);
    $from = $request["from"];
    $to  = $request["to"];
    if (!$allday) {
      $fromtime = $request['fromtime'];
      $totime = $request['totime'];
    }
    $vevent = $vcalendar->VEVENT;
    $this->logInfo(get_class($vcalendar));
    $description = $request["description"];
    $repeat = $request["repeat"];
    if($repeat != 'doesnotrepeat') {
      $rrule = '';
      $interval = $request['interval'];
      $end = $request['end'];
      $byoccurrences = $request['byoccurrences'];
      switch($repeat) {
        case 'daily':
          $rrule .= 'FREQ=DAILY';
          break;
        case 'weekly':
          $rrule .= 'FREQ=WEEKLY';
          if(array_key_exists('weeklyoptions', $request)) {
            $byday = '';
            $daystrings = array_flip($this->getWeeklyOptions());
            foreach($request['weeklyoptions'] as $days) {
              if($byday == '') {
                $byday .= $daystrings[$days];
              }else{
                $byday .= ',' .$daystrings[$days];
              }
            }
            $rrule .= ';BYDAY=' . $byday;
          }
          break;
        case 'weekday':
          $rrule .= 'FREQ=WEEKLY';
          $rrule .= ';BYDAY=MO,TU,WE,TH,FR';
          break;
        case 'biweekly':
          $rrule .= 'FREQ=WEEKLY';
          $interval = $interval * 2;
          break;
        case 'monthly':
          $rrule .= 'FREQ=MONTHLY';
          if($request['advanced_month_select'] == 'monthday') {
            break;
          }elseif($request['advanced_month_select'] == 'weekday') {
            if($request['weekofmonthoptions'] == 'auto') {
              list($_day, $_month, $_year) = explode('-', $from);
              $weekofmonth = floor($_day/7);
            }else{
              $weekofmonth = $request['weekofmonthoptions'];
            }
            $days = array_flip($this->getWeeklyOptions());
            $byday = '';
            foreach($request['weeklyoptions'] as $day) {
              if($byday == '') {
                $byday .= $weekofmonth . $days[$day];
              }else{
                $byday .= ',' . $weekofmonth . $days[$day];
              }
            }
            if($byday == '') {
              $byday = 'MO,TU,WE,TH,FR,SA,SU';
            }
            $rrule .= ';BYDAY=' . $byday;
          }
          break;
        case 'yearly':
          $rrule .= 'FREQ=YEARLY';
          if($request['advanced_year_select'] == 'bydate') {
            list($_day, $_month, $_year) = explode('-', $from);
            $bymonth = date('n', mktime(0,0,0, $_month, $_day, $_year));
            $bymonthday = date('j', mktime(0,0,0, $_month, $_day, $_year));
            $rrule .= ';BYDAY=MO,TU,WE,TH,FR,SA,SU;BYMONTH=' . $bymonth . ';BYMONTHDAY=' . $bymonthday;
          }elseif($request['advanced_year_select'] == 'byyearday') {
            list($_day, $_month, $_year) = explode('-', $from);
            $byyearday = date('z', mktime(0,0,0, $_month, $_day, $_year)) + 1;
            if(array_key_exists('byyearday', $request)) {
              foreach($request['byyearday'] as $yearday) {
                $byyearday .= ',' . $yearday;
              }
            }
            $rrule .= ';BYYEARDAY=' . $byyearday;
          }elseif($request['advanced_year_select'] == 'byweekno') {
            list($_day, $_month, $_year) = explode('-', $from);
            $rrule .= ';BYDAY=' . strtoupper(substr(date('l', mktime(0,0,0, $_month, $_day, $_year)), 0, 2));
            $byweekno = '';
            foreach($request['byweekno'] as $weekno) {
              if($byweekno == '') {
                $byweekno = $weekno;
              }else{
                $byweekno .= ',' . $weekno;
              }
            }
            $rrule .= ';BYWEEKNO=' . $byweekno;
          }elseif($request['advanced_year_select'] == 'bydaymonth') {
            if(array_key_exists('weeklyoptions', $request)) {
              $days = array_flip($this->getWeeklyOptions());
              $byday = '';
              foreach($request['weeklyoptions'] as $day) {
                if($byday == '') {
                  $byday .= $days[$day];
                }else{
                  $byday .= ',' . $days[$day];
                }
              }
              $rrule .= ';BYDAY=' . $byday;
            }
            if(array_key_exists('bymonth', $request)) {
              $monthes = array_flip($this->getByMonthOptions());
              $bymonth = '';
              foreach($request['bymonth'] as $month) {
                if($bymonth == '') {
                  $bymonth .= $monthes[$month];
                }else{
                  $bymonth .= ',' . $monthes[$month];
                }
              }
              $rrule .= ';BYMONTH=' . $bymonth;

            }
            if(array_key_exists('bymonthday', $request)) {
              $bymonthday = '';
              foreach($request['bymonthday'] as $monthday) {
                if($bymonthday == '') {
                  $bymonthday .= $monthday;
                }else{
                  $bymonthday .= ',' . $monthday;
                }
              }
              $rrule .= ';BYMONTHDAY=' . $bymonthday;

            }
          }
          break;
        default:
          break;
      }
      if($interval != '') {
        $rrule .= ';INTERVAL=' . $interval;
      }
      if($end == 'count') {
        $rrule .= ';COUNT=' . $byoccurrences;
      }
      if($end == 'date') {
        list($bydate_day, $bydate_month, $bydate_year) = explode('-', $request['bydate']);
        $rrule .= ';UNTIL=' . $bydate_year . $bydate_month . $bydate_day;
      }
      $vevent->RRULE = $rrule;
      $repeat = "true";
    }else{
      $repeat = "false";
    }

    $now = new \DateTime('now');
    $now->setTimeZone(new \DateTimeZone('UTC'));
    $lastModified = $vevent->__get('LAST-MODIFIED');
    if (is_null($lastModified)) {
      $lastModified = $vevent->add('LAST-MODIFIED');
    }
    $lastModified->setValue($now);
    $vevent->DTSTAMP = $now;

    $vevent->SUMMARY = $summary;

    if($allday) {
      $start = new \DateTime($from);
      $end = new \DateTime($to.' +1 day');

      $vevent->DTSTART = $start;
      $vevent->DTEND = $end;

      $vevent->DTSTART['VALUE'] = 'DATE';
      $vevent->DTEND['VALUE'] = 'DATE';
    }else{
      //$timezone = OC_Calendar_App::getTimezone();
      //$timezone = new \DateTimeZone($timezone);
      $timezone = $this->getDateTimeZone();

      $start = new \DateTime($from.' '.$fromtime, $timezone);
      $end = new \DateTime($to.' '.$totime, $timezone);

      $vevent->DTSTART = $start;
      $vevent->DTEND = $end;
    }

    unset($vevent->DURATION);

    if ($accessclass !== null) {
      $vevent->CLASS = $accessclass;
    }
    $vevent->LOCATION = $location;
    $vevent->DESCRIPTION = $description;
    if (count($categories) > 0) {
      $vevent->CATEGORIES = $categories;
    } else {
      unset($vevent->CATEGORIES);
    }

    /*if($repeat == "true") {
      $vevent->RRULE = $repeat;
      }*/

    return $vcalendar;
  }
}

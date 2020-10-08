<?php
/**
 * Copyright (c) 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
class OC_Calendar_Object{
    /****************************************************************
     *
     * Compat Layer
     *
     */
    use \OCA\CAFEVDB\Traits\ConfigTrait;

    /** @vsar IL10N */
    private $l;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
        $this->l = $this->l10n();
    }

    /*
     *
     ***************************************************************/
                              // /**
	//  * @brief Returns all objects of a calendar
	//  * @param integer $id
	//  * @return array
	//  *
	//  * The objects are associative arrays. You'll find the original vObject in
	//  * ['calendardata']
	//  */
	// public function all($id) {
	// 	$stmt = OCP\DB::prepare( 'SELECT * FROM `*PREFIX*clndr_objects` WHERE `calendarid` = ?' );
	// 	$result = $stmt->execute(array($id));

	// 	$calendarobjects = array();
	// 	while( $row = $result->fetchRow()) {
	// 		$calendarobjects[] = $row;
	// 	}

	// 	return $calendarobjects;
	// }

	// /**
	//  * @brief Returns all objects of a calendar between $start and $end
	//  * @param integer $id
	//  * @param DateTime $start
	//  * @param DateTime $end
	//  * @return array
	//  *
	//  * The objects are associative arrays. You'll find the original vObject
	//  * in ['calendardata']
	//  */
	// public function allInPeriod($id, $start, $end) {
	// 	$stmt = OCP\DB::prepare( 'SELECT * FROM `*PREFIX*clndr_objects` WHERE `calendarid` = ? AND `objecttype`= ?'
	// 	.' AND ((`startdate` >= ? AND `enddate` <= ? AND `repeating` = 0)'
	// 	.' OR (`enddate` >= ? AND `startdate` <= ? AND `repeating` = 0)'
	// 	.' OR (`startdate` <= ? AND `repeating` = 1) )' );
	// 	$start = self::getUTCforMDB($start);
	// 	$end = self::getUTCforMDB($end);
	// 	$result = $stmt->execute(array($id,'VEVENT',
	// 				$start, $end,
	// 				$start, $end,
	// 				$end));

	// 	$calendarobjects = array();
	// 	while( $row = $result->fetchRow()) {
	// 		$calendarobjects[] = $row;
	// 	}

	// 	return $calendarobjects;
	// }

	// /**
	//  * @brief Returns an object
	//  * @param integer $id
	//  * @return associative array
	//  */
	// public function find($id) {
	// 	$stmt = OCP\DB::prepare( 'SELECT * FROM `*PREFIX*clndr_objects` WHERE `id` = ?' );
	// 	$result = $stmt->execute(array($id));

	// 	return $result->fetchRow();
	// }

	// /**
	//  * @brief finds an object by its DAV Data
	//  * @param integer $cid Calendar id
	//  * @param string $uri the uri ('filename')
	//  * @return associative array
	//  */
	// public function findWhereDAVDataIs($cid,$uri) {
	// 	$stmt = OCP\DB::prepare( 'SELECT * FROM `*PREFIX*clndr_objects` WHERE `calendarid` = ? AND `uri` = ?' );
	// 	$result = $stmt->execute(array($cid,$uri));

	// 	return $result->fetchRow();
	// }

	// /**
	//  * @brief Adds an object
	//  * @param integer $id Calendar id
	//  * @param string $data  object
	//  * @return insertid
	//  */
	// public function add($id,$data) {
	// 	$calendar = OC_Calendar_Calendar::find($id);
	// 	if ($calendar['userid'] != OCP\User::getUser()) {
	// 		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $id);
	// 		if (!$sharedCalendar || !($sharedCalendar['permissions'] & OCP\PERMISSION_CREATE)) {
	// 			throw new Exception(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You do not have the permissions to add events to this calendar.'
	// 				)
	// 			);
	// 		}
	// 	}
	// 	$object = Sabre\VObject\Reader::read($data);
	// 	list($type,$startdate,$enddate,$summary,$repeating,$uid) = self::extractData($object);

	// 	if(is_null($uid)) {
	// 		$uid = \Sabre\VObject\UUIDUtil::getUUID();
	// 		$object->UID = $uid;
	// 		$data = $object->serialize();
	// 	}

	// 	$uri = 'owncloud-'.md5($data.rand().time()).'.ics';

	// 	$stmt = OCP\DB::prepare( 'INSERT INTO `*PREFIX*clndr_objects` (`calendarid`,`objecttype`,`startdate`,`enddate`,`repeating`,`summary`,`calendardata`,`uri`,`lastmodified`) VALUES(?,?,?,?,?,?,?,?,?)' );
	// 	$stmt->execute(array($id,$type,$startdate,$enddate,$repeating,$summary,$data,$uri,time()));
	// 	$object_id = OCP\DB::insertid('*PREFIX*clndr_objects');

	// 	OC_Calendar_App::loadCategoriesFromVCalendar($object_id, $object);

	// 	OC_Calendar_Calendar::touchCalendar($id);
	// 	OCP\Util::emitHook('OC_Calendar', 'addEvent', $object_id);
	// 	return $object_id;
	// }

	// /**
	//  * @brief Adds an object with the data provided by sabredav
	//  * @param integer $id Calendar id
	//  * @param string $uri   the uri the card will have
	//  * @param string $data  object
	//  * @return insertid
	//  */
	// public function addFromDAVData($id,$uri,$data) {
	// 	$shared = false;
	// 	$calendar = OC_Calendar_Calendar::find($id);
	// 	if ($calendar['userid'] != OCP\User::getUser()) {
	// 		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $id);
	// 		if (!$sharedCalendar || !($sharedCalendar['permissions'] & OCP\PERMISSION_CREATE)) {
	// 			throw new \Sabre\DAV\Exception\Forbidden(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You do not have the permissions to add events to this calendar.'
	// 				)
	// 			);
	// 		}
	// 		$shared = true;
	// 	}
	// 	$object = \Sabre\VObject\Reader::read($data);
	// 	$vevent = self::getElement($object);

	// 	if($shared && isset($vevent->CLASS) && (string)$vevent->CLASS !== 'PUBLIC') {
	// 		throw new \Sabre\DAV\Exception\PreconditionFailed(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You cannot add non-public events to a shared calendar.'
	// 				)
	// 		);
	// 	}

	// 	list($type,$startdate,$enddate,$summary,$repeating,$uid) = self::extractData($object);

	// 	$stmt = OCP\DB::prepare( 'INSERT INTO `*PREFIX*clndr_objects` (`calendarid`,`objecttype`,`startdate`,`enddate`,`repeating`,`summary`,`calendardata`,`uri`,`lastmodified`) VALUES(?,?,?,?,?,?,?,?,?)' );
	// 	$stmt->execute(array($id,$type,$startdate,$enddate,$repeating,$summary,$data,$uri,time()));
	// 	$object_id = OCP\DB::insertid('*PREFIX*clndr_objects');

	// 	OC_Calendar_Calendar::touchCalendar($id);
	// 	OCP\Util::emitHook('OC_Calendar', 'addEvent', $object_id);
	// 	return $object_id;
	// }

	// /**
	//  * @brief edits an object
	//  * @param integer $id id of object
	//  * @param string $data  object
	//  * @return boolean
	//  */
	// public function edit($id, $data) {
	// 	$oldobject = self::find($id);
	// 	$calid = self::getCalendarid($id);

	// 	$calendar = OC_Calendar_Calendar::find($calid);
	// 	$oldvobject = \Sabre\VObject\Reader::read($oldobject['calendardata']);
	// 	if ($calendar['userid'] != OCP\User::getUser()) {
	// 		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $calid); //calid, not objectid !!!! 1111 one one one eleven
	// 		$sharedAccessClassPermissions = OC_Calendar_Object::getAccessClassPermissions($oldvobject);
	// 		$sharedObject = OCP\Share::getItemSharedWithBySource('event', $id);
	// 		$isActionAllowed = false;
	// 		if ($sharedAccessClassPermissions & OCP\PERMISSION_UPDATE) {
	// 			if (isset($sharedCalendar['permissions']) && $sharedCalendar['permissions'] & OCP\PERMISSION_UPDATE) {
	// 				$isActionAllowed = true;
	// 			} elseif(isset($sharedObject['permissions']) && $sharedObject['permissions'] & OCP\PERMISSION_UPDATE) {
	// 				$isActionAllowed = true;
	// 			}
	// 		}

	// 		if (!$isActionAllowed) {
	// 			throw new Exception(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You do not have the permissions to edit this event.'
	// 				)
	// 			);
	// 		}
	// 	}
	// 	$object = \Sabre\VObject\Reader::read($data);
	// 	OC_Calendar_App::loadCategoriesFromVCalendar($id, $object);
	// 	list($type,$startdate,$enddate,$summary,$repeating,$uid) = self::extractData($object);

	// 	$stmt = OCP\DB::prepare( 'UPDATE `*PREFIX*clndr_objects` SET `objecttype`=?,`startdate`=?,`enddate`=?,`repeating`=?,`summary`=?,`calendardata`=?,`lastmodified`= ? WHERE `id` = ?' );
	// 	$stmt->execute(array($type,$startdate,$enddate,$repeating,$summary,$data,time(),$id));

	// 	OC_Calendar_Calendar::touchCalendar($oldobject['calendarid']);
	// 	OCP\Util::emitHook('OC_Calendar', 'editEvent', $id);

	// 	return true;
	// }

	// /**
	//  * @brief edits an object with the data provided by sabredav
	//  * @param integer $id calendar id
	//  * @param string $uri   the uri of the object
	//  * @param string $data  object
	//  * @return boolean
	//  */
	// public function editFromDAVData($cid,$uri,$data) {
	// 	$oldobject = self::findWhereDAVDataIs($cid,$uri);

	// 	$calendar = OC_Calendar_Calendar::find($cid);
	// 	$oldvobject = \Sabre\VObject\Reader::read($oldobject['calendardata']);
	// 	if ($calendar['userid'] != OCP\User::getUser()) {
	// 		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $cid);
	// 		$sharedAccessClassPermissions = OC_Calendar_Object::getAccessClassPermissions($oldvobject);
	// 		if (!$sharedCalendar || !($sharedCalendar['permissions'] & OCP\PERMISSION_UPDATE) || !($sharedAccessClassPermissions & OCP\PERMISSION_UPDATE)) {
	// 			throw new \Sabre\DAV\Exception\Forbidden(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You do not have the permissions to edit this event.'
	// 				)
	// 			);
	// 		}
	// 	}
	// 	$object = \Sabre\VObject\Reader::read($data);
	// 	list($type,$startdate,$enddate,$summary,$repeating,$uid) = self::extractData($object);

	// 	$stmt = OCP\DB::prepare( 'UPDATE `*PREFIX*clndr_objects` SET `objecttype`=?,`startdate`=?,`enddate`=?,`repeating`=?,`summary`=?,`calendardata`=?,`lastmodified`= ? WHERE `id` = ?' );
	// 	$stmt->execute(array($type,$startdate,$enddate,$repeating,$summary,$data,time(),$oldobject['id']));

	// 	OC_Calendar_Calendar::touchCalendar($oldobject['calendarid']);
	// 	OCP\Util::emitHook('OC_Calendar', 'editEvent', $oldobject['id']);

	// 	return true;
	// }

	// /**
	//  * @brief deletes an object
	//  * @param integer $id id of object
	//  * @return boolean
	//  */
	// public function delete($id) {
	// 	$oldobject = self::find($id);
	// 	$calid = self::getCalendarid($id);

	// 	$calendar = OC_Calendar_Calendar::find($calid);
	// 	$oldvobject = \Sabre\VObject\Reader::read($oldobject['calendardata']);
	// 	if ($calendar['userid'] != OCP\User::getUser()) {
	// 		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar',  $calid);
	// 		$sharedAccessClassPermissions = OC_Calendar_Object::getAccessClassPermissions($oldvobject);
	// 		if (!$sharedCalendar || !($sharedCalendar['permissions'] & OCP\PERMISSION_DELETE) || !($sharedAccessClassPermissions & OCP\PERMISSION_DELETE)) {
	// 			throw new Exception(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You do not have the permissions to delete this event.'
	// 				)
	// 			);
	// 		}
	// 	}
	// 	$stmt = OCP\DB::prepare( 'DELETE FROM `*PREFIX*clndr_objects` WHERE `id` = ?' );
	// 	$stmt->execute(array($id));
	// 	OC_Calendar_Calendar::touchCalendar($oldobject['calendarid']);

	// 	OCP\Share::unshareAll('event', $id);

	// 	OCP\Util::emitHook('OC_Calendar', 'deleteEvent', $id);

	// 	return true;
	// }

	// /**
	//  * @brief deletes an  object with the data provided by sabredav
	//  * @param integer $cid calendar id
	//  * @param string $uri the uri of the object
	//  * @return boolean
	//  */
	// public function deleteFromDAVData($cid,$uri) {
	// 	$oldobject = self::findWhereDAVDataIs($cid, $uri);
	// 	$calendar = OC_Calendar_Calendar::find($cid);
	// 	if ($calendar['userid'] != OCP\User::getUser()) {
	// 		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $cid);
	// 		if (!$sharedCalendar || !($sharedCalendar['permissions'] & OCP\PERMISSION_DELETE)) {
	// 			throw new \Sabre\DAV\Exception\Forbidden(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You do not have the permissions to delete this event.'
	// 				)
	// 			);
	// 		}
	// 	}
	// 	$stmt = OCP\DB::prepare( 'DELETE FROM `*PREFIX*clndr_objects` WHERE `calendarid`= ? AND `uri`=?' );
	// 	$stmt->execute(array($cid,$uri));
	// 	OC_Calendar_Calendar::touchCalendar($cid);
	// 	OCP\Util::emitHook('OC_Calendar', 'deleteEvent', $oldobject['id']);

	// 	return true;
	// }

	// public function moveToCalendar($id, $calendarid) {
	// 	$calendar = OC_Calendar_Calendar::find($calendarid);
	// 	if ($calendar['userid'] != OCP\User::getUser()) {
	// 		$sharedCalendar = OCP\Share::getItemSharedWithBySource('calendar', $calendarid);
	// 		if (!$sharedCalendar || !($sharedCalendar['permissions'] & OCP\PERMISSION_DELETE)) {
	// 			throw new Exception(
	// 				OC_Calendar_App::$this->l->t(
	// 					'You do not have the permissions to add events to this calendar.'
	// 				)
	// 			);
	// 		}
	// 	}
	// 	$stmt = OCP\DB::prepare( 'UPDATE `*PREFIX*clndr_objects` SET `calendarid`=? WHERE `id`=?' );
	// 	$stmt->execute(array($calendarid,$id));

	// 	OC_Calendar_Calendar::touchCalendar($calendarid);
	// 	OCP\Util::emitHook('OC_Calendar', 'moveEvent', $id);

	// 	return true;
	// }

	// /**
    //  * @brief Creates a UID
    //  * @return string
    //  */
    // protected static function createUID() {
    //     return substr(md5(rand().time()),0,10);
    // }

	// /**
	//  * @brief Extracts data from a vObject-Object
	//  * @param Sabre_VObject $object
	//  * @return array
	//  *
	//  * [type, start, end, summary, repeating, uid]
	//  */
	// protected static function extractData($object) {
	// 	$return = array('',null,null,'',0,null);

	// 	// Child to use
	// 	$children = 0;
	// 	$use = null;
	// 	foreach($object->children as $property) {
	// 		if($property->name == 'VEVENT') {
	// 			$children++;
	// 			$thisone = true;

	// 			foreach($property->children as &$element) {
	// 				if($element->name == 'RECURRENCE-ID') {
	// 					$thisone = false;
	// 				}
	// 			} unset($element);

	// 			if($thisone) {
	// 				$use = $property;
	// 			}
	// 		}
	// 		elseif($property->name == 'VTODO' || $property->name == 'VJOURNAL') {
	// 			$return[0] = $property->name;
	// 			foreach($property->children as &$element) {
	// 				if($element->name == 'SUMMARY') {
	// 					$return[3] = $element->getValue();
	// 				}
	// 				elseif($element->name == 'UID') {
	// 					$return[5] = $element->getValue();
	// 				}
	// 			};

	// 			// Only one VTODO or VJOURNAL per object
	// 			// (only one UID per object but a UID is required by a VTODO =>
	// 			//    one VTODO per object)
	// 			break;
	// 		}
	// 	}

	// 	// find the data
	// 	if(!is_null($use)) {
	// 		$return[0] = $use->name;
	// 		foreach($use->children as $property) {
	// 			if($property->name == 'DTSTART') {
	// 				$return[1] = self::getUTCforMDB($property->getDateTime());
	// 			}
	// 			elseif($property->name == 'DTEND') {
	// 				$return[2] = self::getUTCforMDB($property->getDateTime());
	// 			}
	// 			elseif($property->name == 'SUMMARY') {
	// 				$return[3] = $property->getValue();
	// 			}
	// 			elseif($property->name == 'RRULE') {
	// 				$return[4] = 1;
	// 			}
	// 			elseif($property->name == 'UID') {
	// 				$return[5] = $property->getValue();
	// 			}
	// 		}
	// 		// some imported object don't have DTEND but DURATION
	// 		if(is_null($return[2])) {
	// 			$return[2] = self::getDTEndFromVEvent($use);
	// 		}
	// 	}

	// 	// More than one child means reoccuring!
	// 	if($children > 1) {
	// 		$return[4] = 1;
	// 	}
	// 	return $return;
	// }

	// /**
	//  * @brief DateTime to UTC string
	//  * @param DateTime $datetime The date to convert
	//  * @returns date as YYYY-MM-DD hh:mm
	//  *
	//  * This function creates a date string that can be used by MDB2.
	//  * Furthermore it converts the time to UTC.
	//  */
	// public function getUTCforMDB($datetime) {
	// 	return date('Y-m-d H:i', $datetime->format('U'));
	// }

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

	// /**
	//  * Get the contained element VEVENT, VJOURNAL, VTODO
	//  *
	//  * @param Sabre_VObject $vobject
	//  * @return Sabre_VObject|null
	//  */
	// public function getElement($vobject) {
	// 	if(isset($vobject->VEVENT)) {
	// 		return $vobject->VEVENT;
	// 	}
	// 	elseif(isset($vobject->VJOURNAL)) {
	// 		return $vobject->VJOURNAL;
	// 	}
	// 	elseif(isset($vobject->VTODO)) {
	// 		return $vobject->VTODO;
	// 	}
	// }

	// /**
	//  * @brief Get the permissions determined by the access class of an event/todo/journal
	//  * @param Sabre_VObject $vobject Sabre VObject
	//  * @return (int) $permissions - CRUDS permissions
	//  * @see OCP\Share
	//  */
	// public function getAccessClassPermissions($vobject) {
	// 	$velement = self::getElement($vobject);

	// 	$accessclass = $velement->CLASS;

	// 	return OC_Calendar_App::getAccessClassPermissions($accessclass);
	// }

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

		$uid = substr(md5(rand().time()), 0, 10);
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
            $timezone = $this->getDateTimeZone()->getTimeZone();

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

	// /**
	//  * @brief returns the owner of an object
	//  * @param integer $id
	//  * @return string
	//  */
	// public function getowner($id) {
	// 	if ($id == 0) return null;
	// 	$event = $this->find($id);
	// 	$cal = OC_Calendar_Calendar::find($event['calendarid']);
	// 	if($cal === false || is_array($cal) === false){
	// 		return null;
	// 	}
	// 	if(array_key_exists('userid', $cal)){
	// 		return $cal['userid'];
	// 	}else{
	// 		return null;
	// 	}
	// }

	// /**
	//  * @brief returns the calendarid of an object
	//  * @param integer $id
	//  * @return integer
	//  */
	// public function getCalendarid($id) {
	// 	$event = $this->find($id);
	// 	return $event['calendarid'];
	// }

	// /**
	//  * @brief checks if an object is repeating
	//  * @param integer $id
	//  * @return boolean
	//  */
	// public function isrepeating($id) {
	// 	$event = $this->find($id);
	// 	return ($event['repeating'] == 1)?true:false;
	// }

	// /**
	//  * @brief converts the start_dt and end_dt to a new timezone
	//  * @param object $dtstart
	//  * @param object $dtend
	//  * @param boolean $allday
	//  * @param string $tz
	//  * @return array
	//  */
	// public function generateStartEndDate($dtstart, $dtend, $allday, $tz) {
	// 	$start_dt = $dtstart->getDateTime();
	// 	$end_dt = $dtend->getDateTime();
	// 	$return = array();
	// 	if($allday) {
	// 		$return['start'] = $start_dt->format('Y-m-d');
	// 		$end_dt->modify('-1 minute');
	// 		while($start_dt >= $end_dt) {
	// 			$end_dt->modify('+1 day');
	// 		}
	// 		$return['end'] = $end_dt->format('Y-m-d');
	// 	}else{
	// 		if(!$dtstart->isFloating()) {
	// 			$start_dt->setTimezone(new \DateTimeZone($tz));
	// 			$end_dt->setTimezone(new \DateTimeZone($tz));
	// 		}
	// 		$return['start'] = $start_dt->format('Y-m-d H:i:s');
	// 		$return['end'] = $end_dt->format('Y-m-d H:i:s');
	// 	}
	// 	return $return;
	// }
}

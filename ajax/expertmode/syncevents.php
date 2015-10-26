<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2015 Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace CAFEVDB
{

  // Check if we are a user
  \OCP\JSON::checkLoggedIn();
  \OCP\JSON::checkAppEnabled('cafevdb');
  \OCP\JSON::checkAppEnabled('calendar');

  // Loop over all calendars the calendar user shares with the orchestra
  // group.
  $shareowner = Config::getSetting(
    'shareowner', Config::getValue('dbuser').'shareowner');
  $sharegroup = Config::getAppValue('usergroup', '');

  // Possibly fix sharing issues with the default calendars.
  foreach (explode(',',Config::DFLT_CALS) as $key) {
    $calKey = $key.'calendar';
    $id     = Config::getValue($calKey.'id');
    $name   = Config::getSetting($calKey, L::t($key));
    $newId = Events::checkSharedCalendar($name, $id);
    if ($newId !== false && $newId != $id) {
      Config::setValue($calKey.'id', $newId);
    }
  }


  // Determine all calendars shared with the group
  $sharedCals = ConfigCheck::sudo($shareowner, function() use ($shareowner) {
      $result1 = \OCP\Share::getItemsShared('calendar');
      $result2 = \OCP\Share::getItemsSharedWith('calendar');
      $result = array_merge($result1, $result2);
      return $result;
    });

  Config::init();
  $handle = mySQL::connect(Config::$pmeopts);

  $calIds = '';
  $evtCnt = 0;
  $newCnt = 0;
  foreach ($sharedCals as $share) {
    if ($share['share_with'] != $sharegroup) {
      continue;
    }
    $calId = $share['item_source'];

    $calIds .= $calId.' ';
    // Do the sync
    $events = \OC_Calendar_Object::all($calId);
    foreach ($events as $event) {
      if (Events::syncEvent($event['id'], $handle)) {
        $newCnt++;
      }
      $evtCnt++;
    }
  }

  mySQL::close($handle);

  \OC_JSON::success(
    array(
      "data" => array(
        "message" => L::t("Done. %d events synchronized, %d new.",
                          array($evtCnt, $newCnt)))));

  return true;

}

?>

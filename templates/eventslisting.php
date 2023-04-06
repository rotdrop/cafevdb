<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2023 Claus-Justus Heine
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

use OCA\CAFEVDB\Common\Util;

$evtButtons = [
  'Calendar' => [
    'tag' => 'calendar',
    'title' => $toolTips['projectevents:event:calendar'],
  ],
  'Edit' => [
    'tag' => 'edit',
    'title' => $toolTips['projectevents:event:edit'],
  ],
  'Copy' => [
    'tag' => 'clone',
    'title' => $toolTips['projectevents:event:clone'],
  ],
  'Delete' => [
    'tag' => 'delete',
    'title' => $toolTips['projectevents:event:delete'],
  ],
  'Detach' => [
    'tag' => 'detach',
    'title' => $toolTips['projectevents:event:detach'],
  ],
];

?>
<div class="size-holder event-list-container">
<?php

$n = 0;
foreach ($eventMatrix as $key => $eventGroup) {
  $class = [ 'listing', ];
  $dpyName = $eventGroup['name'];
  $events  = $eventGroup['events'];
  if (!empty($events)) {
    // nothing
  } elseif ($key >= 0) {
    $dpyName .= ' (' . $l->t('no events') . ')';
    $class[] = 'empty';
  } else {
    continue;
  }
  $classes = implode(' ', $class);
?>
  <h4 class="heading <?php p($classes); ?>"><?php p($dpyName); ?></h4>
  <div class="table-container">
    <table class="<?php p($classes); ?>">
      <tbody>
<?php
  foreach ($eventGroup['events'] as $event) {
    $calId  = $event['calendarid'];
    $evtUri  = $event['uri'];
    $recurrenceId = $event['recurrenceId'];
    $seriesUid = $event['seriesUid'];

    $flatIdentifier = implode(':', [ $calId, $evtUri, $recurrenceId ]);
    $inputValue = json_encode([ 'calendarId' => $calId, 'uri' => $evtUri, 'recurrenceId' => $recurrenceId, 'seriesUid' => $seriesUid ]);

    $brief  = htmlspecialchars(stripslashes($event['summary']));
    $location = htmlspecialchars(stripslashes($event['location']));
    $description = htmlspecialchars(nl2br(stripslashes($event['description'])));

    $datestring = $eventsService->briefEventDate($event, $timezone, $locale);
    $longDate = $eventsService->longEventDate($event, $timezone, $locale);

    $description = $longDate
      . (!empty($brief) ? '<br/>' . $brief  : '')
      . (!empty($location) ? '<br/>' . $location  : '')
      . (!empty($description) ? '<br/>' . $description : '');
?>
        <tr class="<?php p($cssClass); ?> step-<?php p($n); ?>"
            data-calendar-id="<?php p($calId); ?>"
            data-event-uri="<?php p($evtUri); ?>"
            data-recurrence-id="<?php p($recurrenceId); ?>"
            data-series-uid="<?php p($seriesUid); ?>"
        >
          <td class="eventbuttons">
            <input type="hidden" id="calendarid-<?php p($evtUri); ?>" name="calendarId[<?php p($evtUri); ?>]" value="<?php p($calId); ?>"/>
<?php
    foreach ($evtButtons as $btn => $values) {
      $tag   = $values['tag'];
      $title = $values['title'];
      $name  = $tag."[$evtUri]";
?>
            <input class="<?php p($tag); ?> event-action"
                   id="<?php p($tag); ?>-<?php p($flatIdentifier); ?>"
                   type="button"
                   name="<?php p($tag); ?>"
                   title="<?php p($title); ?>"
                   value='<?php p($inputValue); ?>'
            />
<?php
    }
    $title = $toolTips['projectevents:event:select'];
    $checked = isset($selected[$flatIdentifier]) ? 'checked="checked"' : '';
    $emailCheckId = 'email-check-' . $flatIdentifier;
?>
          </td>
          <td class="eventemail">
            <label class="email-check" for="<?php p($emailCheckId); ?>"  title="<?php p($title); ?>">
            <input class="email-check" title="" id="<?php p($emailCheckId); ?>" type="checkbox" name="eventSelect[]" value='<?php p($inputValue); ?>' <?php p($checked); ?>/>
            <div class="email-check" /></label>
          </td>
          <td class="eventdata brief tooltip-top tooltip-wide" id="brief-<?php p($evtUri); ?>" title="<?php p($description); ?>"><?php p($brief); ?></td>
          <td class="eventdata date tooltip-top tooltip-wide" id="data-<?php p($evtUri); ?>" title="<?php p($description); ?>"><?php p($datestring); ?></td>
        </tr>
<?php
    $n = ($n + 1) & 1;
  }
?>
    </tbody>
  </table>
</div>
<?php
}
?>
</div>

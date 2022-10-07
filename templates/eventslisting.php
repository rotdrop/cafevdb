<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

?>
<div class="size-holder event-list-container">
<?php

$evtButtons = [
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

$n = 0;
foreach ($eventMatrix as $key => $eventGroup) {
  $class = [ 'listing', ];
  $dpyName = $eventGroup['name'];
  $events  = $eventGroup['events'];
  if (!empty($events)) {
    // nothing
  } else if ($key >= 0) {
    $dpyName .= ' (' . $l->t('no events') . ')';
    $class[] = 'empty';
  } else {
    continue;
  }
  echo '<h4 class="heading ' . implode(' ', $class). '">' . $dpyName . '</h4>';
  echo '<div class="table-container">
  <table class="' . implode(' ', $class) . '">
    <tbody>
';
  foreach ($eventGroup['events'] as $event) {
    $evtUri  = $event['uri'];
    $calId  = $event['calendarid'];
    $brief  = htmlspecialchars(stripslashes($event['summary']));
    $location = htmlspecialchars(stripslashes($event['location']));
    $description = htmlspecialchars(nl2br(stripslashes($event['description'])));

    $datestring = $eventsService->briefEventDate($event, $timezone, $locale);
    $longDate = $eventsService->longEventDate($event, $timezone, $locale);

    $description = $longDate
      . (!empty($brief) ? '<br/>' . $brief  : '')
      . (!empty($location) ? '<br/>' . $location  : '')
      . (!empty($description) ? '<br/>' . $description : '');

    echo <<<__EOT__
      <tr class="$cssClass step-$n">
        <td class="eventbuttons">
          <input type="hidden" id="calendarid-$evtUri" name="CalendarId[$evtUri]" value="$calId"/>
__EOT__;
    foreach ($evtButtons as $btn => $values) {
      $tag   = $values['tag'];
      $title = $values['title'];
      $name  = $tag."[$evtUri]";
      echo <<<__EOT__
          <input class="$tag event-action"
                 id="$tag-$evtUri"
                 type="button"
                 name="$tag"
                 title="$title"
                 value="$evtUri"
                 data-calendar-id="$calId"
          />
__EOT__;
    }
    $title = $toolTips['projectevents:event:select'];
    $checked = isset($selected[$evtUri]) ? 'checked="checked"' : '';
    $emailValue = Util::htmlEscape(json_encode([ 'uri' => $evtUri, 'calendarId' => $calId ]));
    echo <<<__EOT__
        </td>
        <td class="eventemail">
          <label class="email-check" for="email-check-$evtUri"  title="$title" >
          <input class="email-check" title="" id="email-check-$evtUri" type="checkbox" name="eventSelect[]" value="$emailValue" $checked />
          <div class="email-check" /></label>
        </td>
        <td class="eventdata brief tooltip-top tooltip-wide" id="brief-$evtUri" title="$description">$brief</td>
        <td class="eventdata date tooltip-top tooltip-wide" id="data-$evtUri" title="$description">$datestring</td>
      </tr>
__EOT__;
    $n = ($n + 1) & 1;
  }
  echo '    </tbody>
  </table>
</div>
';
}
?>
</div>

<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
?>
<table id="table" class="nostyle listing size-holder">
<?php

$evtButtons = [
  'Edit' => [
    'tag' => 'edit',
    'title' => $toolTips['projectevents-edit']
  ],
  'Delete' => [
    'tag' => 'delete',
    'title' =>$toolTips['projectevents-delete']
  ],
  'Detach' => [
    'tag' => 'detach',
    'title' =>$toolTips['projectevents-detach']
  ],
];

$n = 0;
foreach ($eventMatrix as $key => $eventGroup) {
  $dpyName = $eventGroup['name'];
  $events   = $eventGroup['events'];
  if (!empty($events)) {
    echo "<tr><th colspan=\"4\">$dpyName</th></tr>";
  } else if ($key >= 0) {
    $noEvents = empty($events) ? $l->t('no events') : '';
    echo "<tr><th colspan=\"4\">$dpyName ($noEvents)</th></tr>";
  }
  foreach ($eventGroup['events'] as $event) {
    $evtUri  = $event['uri'];
    $calId  = $event['calendarid'];
    $brief  = htmlspecialchars(stripslashes($event['summary']));
    $description = htmlspecialchars(nl2br("\n".stripslashes($event['description'])));

    $datestring = $eventsService->briefEventDate($event, $timezone, $locale);
    $longDate = $eventsService->longEventDate($event, $timezone, $locale);

    $description = $longDate.'<br/>'.$brief.$description;

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
    $title = $toolTips['projectevents-selectevent'];
    $checked = isset($selected[$evtUri]) ? 'checked="checked"' : '';
    echo <<<__EOT__
      </td>
      <td class="eventemail">
        <label class="email-check" for="email-check-$evtUri"  title="$title" >
        <input class="email-check" title="" id="email-check-$evtUri" type="checkbox" name="eventSelect[]" value="$evtUri" $checked />
        <div class="email-check" /></label>
      </td>
      <td class="eventdata brief tooltip-top tooltip-wide" id="brief-$evtUri" title="$description">$brief</td>
      <td class="eventdata date tooltip-top tooltip-wide" id="data-$evtUri" title="$description">$datestring</td>
    </tr>
__EOT__;
    $n = ($n + 1) & 1;
  }
}
?>
</table>

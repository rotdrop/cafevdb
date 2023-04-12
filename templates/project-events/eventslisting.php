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
use OCA\CAFEVDB\Service\EventsService;
use OCA\CAFEVDB\Controller\ProjectEventsController;

?>
<div class="size-holder event-list-container">
<?php

$relationMatrix = [];
$eventSeries = [];
$eventRelations = [];
foreach ($eventMatrix as $key => $eventGroup) {
  foreach ($eventGroup['events'] as $event) {
    if (!empty($event['seriesUid'])) {
      $eventRelations[$event['seriesUid']] = ($eventRelations[$event['seriesUid']] ?? 0) + 1;
      if (empty($relationMatrix[$event['seriesUid']])) {
        $relationMatrix[$event['seriesUid']] = [];
      }
      $relationMatrix[$event['seriesUid']][$event['uid']] = true;
    }
    if (!empty($event['recurrenceId'])) {
      $eventSeries[$event['uid']] = ($eventSeries[$event['uid']] ?? 0) + 1;
    }
  }
}
$eventSeries = array_flip(array_keys($eventSeries));
$eventRelations = array_flip(array_keys($eventRelations));
$haveEventSeries = count($eventSeries) > 0;
$haveCrossSeriesRelations = array_reduce($relationMatrix, fn(bool $crossRelations, array $uids) => $crossRelations || count($uids) > 1, false);

$n = 0;
foreach ($eventMatrix as $key => $eventGroup) {
  $class = [ 'listing', ];
  $dpyName = $eventGroup['name'];
  $remoteUrl = $eventGroup['remoteUrl'];
  $events  = $eventGroup['events'];
  if (empty($events)) {
    if ($key >= 0) {
      $dpyName .= ' (' . $l->t('no events') . ')';
      $class[] = 'empty';
    } else {
      continue;
    }
  }
  $classes = implode(' ', $class);
?>
  <h4 class="heading <?php p($classes); ?>"><?php p($dpyName); ?></h4>
  <div class="table-container">
    <table class="<?php p($classes); ?>">
      <tbody>
<?php
  foreach ($events as $event) {
    $calId  = $event['calendarid'];
    $evtUri = $event['uri'];
    $evtUid = $event['uid'];
    $recurrenceId = $event['recurrenceId'];
    $seriesUid = $event['seriesUid'] ?? '';

    $flatIdentifier = EventsService::makeFlatIdentifier($event);
    $inputValue = ProjectEventsController::makeInputValue($event);

    $isRepeating = isset($eventSeries[$evtUid]);
    $hasCrossSeriesRelations = count($relationMatrix[$seriesUid] ?? []) > 1;

    $actionScope = $isRepeating ? 'series' : 'single';

    $rowCssClass = $cssClass;
    if ($isRepeating) {
      $rowCssClass .= ' event-is-repeating';
    } else {
      $rowCssClass .= ' event-is-not-repeating';
    }
    if ($hasCrossSeriesRelations) {
      $rowCssClass .= ' event-has-cross-series-relations';
    } else {
      $rowCssClass .= ' event-has-no-cross-series-relations';
    }
    $rowCssClass .= ' event-action-scope-' . $actionScope;

    $brief  = htmlspecialchars(stripslashes($event['summary']));
    $location = htmlspecialchars(stripslashes($event['location']));
    $description = htmlspecialchars(nl2br(stripslashes($event['description'])));

    $dateString = $eventsService->briefEventDate($event, $timezone, $locale);
    $longDate = $eventsService->longEventDate($event, $timezone, $locale);

    $description = $longDate
      . (!empty($brief) ? '<br/>' . $brief  : '')
      . (!empty($location) ? '<br/>' . $location  : '')
      . (!empty($description) ? '<br/>' . $description : '');
?>
        <tr class="<?php p($rowCssClass); ?> step-<?php p($n); ?>"
            data-calendar-id="<?php p($calId); ?>"
            data-uri="<?php p($evtUri); ?>"
            data-recurrence-id="<?php p($recurrenceId); ?>"
            data-series-uid="<?php p($seriesUid); ?>"
            data-action-scope="<?php p($actionScope); ?>"
        >
          <td class="eventbuttons">
            <input type="hidden" id="calendarid-<?php p($evtUri); ?>" name="calendarId[<?php p($evtUri); ?>]" value="<?php p($calId); ?>"/>
<?php
    $title = $toolTips['projectevents:event:select'];
    $checked = isset($selected[$flatIdentifier]) ? 'checked="checked"' : '';
    $emailCheckId = 'email-check-' . $flatIdentifier;
    echo $this->inc('project-events/event-actions-menu', [
      'flatIdentifier' => $flatIdentifier,
      'inputValue' => $inputValue,
      'selected' => $selected,
      'dateString' => $dateString,
      'seriesUid' => $seriesUid,
      'isRepeating' => $isRepeating,
      'hasCrossSeriesRelations' => $hasCrossSeriesRelations,
      'actionScope' => $actionScope,
      'remoteUrl' => $remoteUrl,
      'event' => $event,
    ]);
?>
          </td>
          <td class="eventemail">
            <label class="email-check" for="<?php p($emailCheckId); ?>"  title="<?php p($title); ?>">
            <input class="email-check" title="" id="<?php p($emailCheckId); ?>" type="checkbox" name="eventSelect[]" value='<?php p($inputValue); ?>' <?php p($checked); ?>/>
            <div class="email-check" /></label>
          </td>
          <td class="event-uid tooltip-auto event-uid-<?php p($eventSeries[$evtUid] ?? ''); ?><?php $haveEventSeries || p(' really hidden'); ?>"
              title="<?php echo $toolTips['projectevents:event:event-uid']; ?>"
          >
            <span class="uid-index"><?php isset($eventSeries[$evtUid]) && p(chr(ord('A') + $eventSeries[$evtUid])); ?></span>
            <span class="really hidden"><?php p($evtUid); ?></span>
          </td>
          <td class="event-series-uid tooltip-auto event-series-uid-<?php p($eventRelations[$seriesUid] ?? ''); ?><?php $haveCrossSeriesRelations || p(' really hidden'); ?>"
              title="<?php echo $toolTips['projectevents:event:event-series-uid']; ?>"
          >
            <span class="series-uid-index"><?php isset($eventRelations[$seriesUid]) && p(mb_chr(mb_ord('α') + $eventRelations[$seriesUid])); ?></span>
            <span class="really hidden"><?php p($seriesUid); ?></span>
          </td>
          <td class="eventdata brief tooltip-top tooltip-wide"
              id="brief-<?php p($flatIdentifier); ?>"
              title="<?php p($description); ?>"
          >
            <?php p($brief); ?>
          </td>
          <td class="eventdata date tooltip-top tooltip-wide"
              id="data-<?php p($flatIdentifier); ?>"
              title="<?php p($description); ?>"><?php p($dateString); ?>
          </td>
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

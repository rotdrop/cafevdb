<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2023 Claus-Justus Heine
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

namespace OCA\CAFEVDB;

use DateTimeImmutable;

$selectId = 'select-check-' . $flatIdentifier;
$actionScopeId = 'scope-radio-' . $flatIdentifier;

$calendarLink = [];
$remoteEventUrl = $remoteUrl . '/' . $event['uri'];
$calendarLink['single'] = $urlGenerator->linkToRoute('calendar.view.indexview.timerange.edit', [
  'view' => 'timeGridWeek',
  'timeRange' => $event['start']->format('Y-m-d'),
  'mode' => 'sidebar',
  'objectId' =>  base64_encode($remoteEventUrl),
  'recurrenceId' => empty($event['recurrenceId']) ? $event['start']->getTimestamp() : $event['recurrenceId'],
]);
$calendarLink['series'] = $urlGenerator->linkToRoute('calendar.view.indexview.timerange.edit', [
  'view' => 'timeGridWeek',
  'timeRange' => $event['seriesStart']->format('Y-m-d'),
  'mode' => 'sidebar',
  'objectId' =>  base64_encode($remoteEventUrl),
  'recurrenceId' => $event['seriesStart']->getTimestamp(),
]);
$calendarTarget = md5($urlGenerator->linkToRoute('calendar.view.indexdirect.edit', [ 'objectId' =>  $appName ]));

$actionItems = [
  'calendar-app:single' => [
    'label' => $l->t('edit in calendar app'),
    'css' => [
      'scope-related-invisible scope-series-invisible',
    ],
    'href' => $calendarLink['single'],
    'target' => $calendarTarget,
  ],
  'calendar-app:series' => [
    'label' => $l->t('edit in calendar app'),
    'css' => [
      'scope-related-disabled scope-single-invisible',
    ],
    'href' => $calendarLink['series'],
    'target' => $calendarTarget,
  ],
  'edit' => [
    'label' => $l->t('edit in simple editor'),
    'css' => [
      'scope-related-disabled',
      'repeating-scope-single-disabled',
    ],
  ],
  'clone' => [
    'label' => $l->t('duplicate'),
    'css' => [
      'scope-related-disabled',
      'repeating-scope-single-disabled',
    ],
  ],
  'delete' => [
    'label' => $l->t('delete'),
  ],
  'detach' => [
    'label' => $l->t('detach from project'),
  ],
];

?>

<span class="event-actions dropdown-container dropdown-no-hover">
  <button class="menu-title action-menu-toggle"
          title="<?php echo $toolTips['projectevents:event']; ?>"
  >
    &bull;&bull;&bull;
  </button>
  <nav class="event-actions-dropdown dropdown-content dropdown-align-left">
    <ul>
      <li class="event-date"
          data-operation="none"
      >
        <a href="#">
          <span class="context-menu-title"><?php p($dateString); ?></span>
        </a>
      </li>
      <li class="menu-item-separator" data-operation="none"><hr/></li>
      <li class="event-action tooltip-right event-action-scope dropdown-item dropdown-no-close only-multi-scope"
          data-operation="scope"
          title="<?php echo $toolTips['projectevents:event:scope']; ?>"
      >
        <div class="scope-container flex-container flex-start flex-column">
          <div class="fit-width">
            <input id="scope-radio-single-<?php p($flatIdentifier); ?>"
                   class="scope-radio radio"
                   type="radio"
                   name="scope[<?php p($flatIdentifier); ?>]"
                   value="single"
                   <?php ($actionScope == 'single') && p('checked'); ?>
            />
            <label class="scope-radio tooltip-right" for="scope-radio-single-<?php p($flatIdentifier); ?>"
                   title="<?php echo $toolTips['projectevents:event:scope:single']; ?>"
            >
              <?php p($l->t('act only on this event')); ?>
            </label>
          </div>
          <div class="only-repeating fit-width">
            <input id="scope-radio-series-<?php p($flatIdentifier); ?>"
                   class="scope-radio radio"
                   type="radio"
                   name="scope[<?php p($flatIdentifier); ?>]"
                   value="series"
                   <?php ($actionScope == 'series') && p('checked'); ?>
            />
            <label class="scope-radio tooltip-right" for="scope-radio-series-<?php p($flatIdentifier); ?>"
                   title="<?php echo $toolTips['projectevents:event:scope:series']; ?>"
            >
              <?php p($l->t('act on the event series')); ?>
            </label>
          </div>
          <div class="only-only-cross-series-relations fit-width">
            <input id="scope-radio-related-<?php p($flatIdentifier); ?>"
                   class="scope-radio radio"
                   type="radio"
                   name="scope[<?php p($flatIdentifier); ?>]"
                   value="related"
                   <?php ($actionScope == 'related') && p('checked'); ?>
            />
            <label class="scope-radio tooltip-right" for="scope-radio-related-<?php p($flatIdentifier); ?>"
                   title="<?php echo $toolTips['projectevents:event:scope:related']; ?>"
            >
              <?php p($l->t('act on all related events')); ?>
            </label>
          </div>
        </div>
      </li>
      <li class="menu-item-separator only-multi-scope" data-operation="none"><hr/></li>
      <li class="event-action tooltip-right event-action-select dropdown-item dropdown-no-close"
          data-operation="select"
          title="<?php echo $toolTips['projectevents:event:select']; ?>"
      >
        <input id="<?php p($selectId); ?>"
               class="email-check checkbox"
               type="checkbox"
               name="eventSelect[]"
               value='<?php p($inputValue); ?>'
               <?php isset($selected[$flatIdentifier]) && p('checked'); ?>
        />
        <label class="select-check" for="<?php p($selectId); ?>">
          <span class="label-checked"><?php p($l->t('selected')); ?></span>
          <span class="label-unchecked"><?php p($l->t('select')); ?></span>
        </label>
      </li>
      <?php
      foreach ($actionItems as $tag => $itemInfo) {
        list($tag, $subTag,) = array_pad(explode(':', $tag), 2, null);
        $label = $itemInfo['label'];
        $css = !empty($itemInfo['css']) ? ' ' . implode(' ', $itemInfo['css']) : '';
        $href = $itemInfo['href'] ?? '#';
        $target = empty($itemInfo['target']) ? '' : ' target="' . $itemInfo['target'] . '"';
      ?>
      <li class="event-action tooltip-right event-action-<?php p($tag); ?><?php p($css); ?> <?php p($tag); ?><?php $subTag && p($tag . '-' . $subTag); ?>"
          data-operation="<?php p($tag); ?>"
          title="<?php echo $toolTips['projectevents:event:' . $tag . ($subTag ? ':' . $subTag : '')]; ?>"
      >
        <a href="<?php p($href); ?>"<?php p($target); ?> class="flex-container flex-center">
          <span class="menu-item-label"><?php p($label); ?></span>
        </a>
      </li>
      <?php } ?>
    </ul>
  </nav>
</span>

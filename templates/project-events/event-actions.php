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

$selectId = 'select-check-' . $flatIdentifier;
$actionScopeId = 'scope-radio-' . $flatIdentifier;

$actionItems = [
  'calendar-app' => [
    'label' => $l->t('edit in calendar app'),
    'css' => [
      'scope-related-disabled',
    ],
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

<span class="event-actions dropdown-container dropdown-no-hover"
      data-calendar-id=""
>
  <button class="menu-title action-menu-toggle">&bull;&bull;&bull;</button>
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
      <li class="event-action tooltip-auto event-action-scope dropdown-item dropdown-no-close only-repeating"
          data-operation="scope"
          title="<?php echo $toolTips['projectevents:event:scope']; ?>"
      >
        <div class="scope-container flex-container flex-start flex-column">
          <input id="scope-radio-single-<?php p($flatIdentifier); ?>"
                 class="scope-radio radio"
                 type="radio"
                 name="scope[<?php p($flatIdentifier); ?>]"
                 value="single"
                 <?php ($actionScope == 'single') && p('checked'); ?>
          />
          <label class="scope-radio" for="scope-radio-single-<?php p($flatIdentifier); ?>">
            <?php p($l->t('act only on this event')); ?>
          </label>
          <input id="scope-radio-series-<?php p($flatIdentifier); ?>"
                 class="scope-radio radio"
                 type="radio"
                 name="scope[<?php p($flatIdentifier); ?>]"
                 value="series"
                 <?php ($actionScope == 'series') && p('checked'); ?>
          />
          <label class="scope-radio" for="scope-radio-series-<?php p($flatIdentifier); ?>">
            <?php p($l->t('act on the event series')); ?>
          </label>
          <input id="scope-radio-related-<?php p($flatIdentifier); ?>"
                 class="scope-radio radio only-cross-series-relations"
                 type="radio"
                 name="scope[<?php p($flatIdentifier); ?>]"
                 value="related"
                 <?php ($actionScope == 'related') && p('checked'); ?>
          />
          <label class="scope-radio only-cross-series-relations" for="scope-radio-related-<?php p($flatIdentifier); ?>">
            <?php p($l->t('act on all related events')); ?>
          </label>
        </div>
      </li>
      <li class="menu-item-separator only-repeating" data-operation="none"><hr/></li>
      <li class="event-action tooltip-auto event-action-select dropdown-item dropdown-no-close"
          data-operation="select"
          title="<?php echo $toolTips['projectevents:event:select']; ?>"
      >
        <input id="<?php p($selectId); ?>"
               class="email-check checkbox"
               type="checkbox"
               name="eventSelect[]"
               value='<?php p($inputValue); ?>'
               <?php $selected && p('checked'); ?>
        />
        <label class="select-check" for="<?php p($selectId); ?>">
          <span class="label-checked"><?php p($l->t('selected')); ?></span>
          <span class="label-unchecked"><?php p($l->t('select')); ?></span>
        </label>
      </li>
      <?php foreach ($actionItems as $tag => $itemInfo) {
        $label = $itemInfo['label'];
        $css = !empty($itemInfo['css']) ? ' ' . implode(' ', $itemInfo['css']) : '';
      ?>
      <li class="event-action tooltip-auto event-action-<?php p($tag); ?><?php p($css); ?>"
          data-operation="<?php p($tag); ?>"
          title="<?php echo $toolTips['projectevents:event:' . $tag]; ?>"
      >
        <a href="#" class="flex-container flex-center">
          <span class="menu-item-label"><?php p($label); ?></span>
        </a>
      </li>
      <?php } ?>
    </ul>
  </nav>
</span>

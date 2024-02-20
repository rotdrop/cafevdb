<?php
/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
 * @license GNU AGPL version 3 or any later version
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
 * You should have received a copy of the GNU Affero General Public
 * License alogng with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @param array $cssClasses
 * @param array $menuData
 * @param string $toolTipPrefix
 * @param Closure $menuItemTemplate
 * @param string $direction 'left' or 'right'
 * @param string $dropDirection 'up' or 'down'
 */

$cssClasses = array_merge($cssClasses ?? [], ['actions', 'menu-actions']);
$toolTipPrefix = $toolTipPrefix ?? 'action-menu';
$menuItemTemplate = $menuItemTemplate ?? 'fragments/action-menu/dummy-item';
?>
<span class="<?php p(implode(' ', $cssClasses)); ?> dropdown-container dropdown-no-hover tooltip-right"
      <?php foreach (($menuData ?? []) as $key => $value) { ?>
      data-<?php p($key); ?>="<?php p($value); ?>"
      <?php } ?>
>
  <button class="menu-title action-menu-toggle tooltip-auto"
          title="<?php echo $toolTips[$toolTipPrefix]; ?>"
  >...</button>
  <nav class="dropdown-content dropdown-align-<?php p($direction); ?> dropdown-drop<?php p($dropDirection); ?>">
    <ul>
      <?php echo $this->inc($menuItemTemplate, $_); ?>
    </ul>
  </nav>
</span>

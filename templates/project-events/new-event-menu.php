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

use OCA\CAFEVDB\Service\ConfigService;

$calendarUris = array_column(ConfigService::CALENDARS, 'uri');

?>

<span class="new-event dropdown-container dropdown-no-hover">
  <button class="menu-title image-button button action-menu-toggle"
          title="<?php echo $toolTips['projectevents:all:new']; ?>"
  >
    <?php p($l->t('New Event')); ?>
  </button>
  <nav class="new-event-dropdown dropdown-content dropdown-align-left">
    <ul>
      <?php foreach ($calendarUris as $uri) {
        $label = $l->t(ucfirst($uri));
      ?>
      <li class="new-event menu-item tooltip-right new-event-<?php p($uri); ?>"
          data-operation="<?php p($uri); ?>"
          title="<?php echo $toolTips['projectevents:all:new:' . $uri]; ?>"
      >
        <a href="#" class="flex-container flex-center">
          <span class="menu-item-label"><?php p($label); ?></span>
        </a>
      </li>
      <?php } ?>
    </ul>
  </nav>
</span>

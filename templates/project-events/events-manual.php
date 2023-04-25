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

?>

<span class="project-event-manual dropdown-container">
  <button class="menu-title image-button button action-menu-toggle"
          title="<?php echo $toolTips['projectevents:manual']; ?>"
  >
    <?php p($l->t('Manual')); ?>
  </button>
  <nav class="project-event-manual-dropdown help-dropdown dropdown-content dropdown-align-right">
    <ul>
      <li data-id="manual_window"
          data-manual-page="projects:project-events"
          data-namespace="<?php p($wikinamespace); ?>">
        <a href="#">
          <img alt="" src="">
          <?php p($l->t('Manual (other tab)')) ?>
        </a>
      </li>
      <li data-id="manual_dialog"
          data-dialog-title="<?php p($l->t('Project Events')); ?>"
          data-manual-page="projects:project-events"
          data-namespace="<?php p($wikinamespace); ?>">
        <a href="#">
          <img alt="" src="">
          <?php p($l->t('Manual (popup)')) ?>
        </a>
      </li>
    </ul>
  </nav>
</span>

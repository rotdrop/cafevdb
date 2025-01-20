<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Templates;

?>
<div  id="<?php p($cssPrefix) ?>-config-check-header" class="<?php p($cssPrefix) ?>-config-check">
  <?php $l->t('It may be that you simply have to log-off and log-in again because your login-session has timed out. Otherwise:') ?>
  <p>
  <?php $l->t('Several basic configuraton options are missing. Please follow the
instructions below. If this is a new installation then you will
probably also have to adjust several other app-settings. The settings
can be accessed through the configuration menu in the top-right corner.
You need to have the role of a group-administrator to do.') ?>
</div>

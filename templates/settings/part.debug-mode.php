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

/**
 * @param string $expertMode, 'on' switches on.
 * @param \ArrayAccess $toolTips
 * @param string $toolTipsPos
 * @param int $debugMode
 */

namespace OCA\CAFEVDB;

use OCA\CAFEVDB\Service\ConfigService;

$debugModes = [
  ConfigService::DEBUG_GENERAL => $l->t('General Information'),
  ConfigService::DEBUG_QUERY => $l->t('SQL Queries'),
  ConfigService::DEBUG_CSP => $l->t('CSP Violations'),
  ConfigService::DEBUG_L10N => $l->t('L10N'),
  ConfigService::DEBUG_REQUEST => $l->t('HTTP Requests'),
  ConfigService::DEBUG_TOOLTIPS => $l->t('Missing Context Help'),
  ConfigService::DEBUG_EMAILFORM => $l->t('Mass Email Form'),
  ConfigService::DEBUG_GEOCODING => $l->t('GeoCoding'),
];

?>

<select <?php echo ($expertMode != 'on' ? 'disabled' : '') ?>
            id="app-settings-debugmode"
            multiple
            name="debugmode"
            data-placeholder="<?php echo $l->t('Enable Debug Mode'); ?>"
            class="debug-mode debugmode chosen-dropup tooltip-<?php p($toolTipsPos); ?>"
            title="<?php echo $toolTips['debug-mode']; ?>">
  <?php
  foreach ($debugModes as $key => $value) {
    echo '<option value="'.$key.'" '.(($debugMode & $key) != 0 ? 'selected="selected"' : '').'>'.$value.'</option>'."\n";
  }
  ?>
</select>

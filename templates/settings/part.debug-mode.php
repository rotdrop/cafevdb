<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  ConfigService::DEBUG_REQUEST => $l->t('HTTP Requests'),
  ConfigService::DEBUG_TOOLTIPS => $l->t('Missing Context Help'),
  ConfigService::DEBUG_EMAILFORM => $l->t('Mass Email Form'),
];

?>

<select <?php echo ($expertMode != 'on' ? 'disabled="disabled"' : '') ?>
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

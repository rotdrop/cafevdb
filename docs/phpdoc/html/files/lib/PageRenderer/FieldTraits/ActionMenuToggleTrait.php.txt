<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer\FieldTraits;

use Closure;

use OCA\CAFEVDB\Database\Legacy\PME\PHPMyEdit;

/**
 * Generate a placeholder button as mount-point for a Vue action menu. This is
 * meant to hook into the 'custom_navigation' entry of PME.
 */
trait ActionMenuToggleTrait
{
  /**
   * @var string
   *
   * Material design icons horizontal dots.
   */
  protected const DOTS_SVG = '<svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24" class="material-design-icon__svg">
  <path d="M16,12A2,2 0 0,1 18,10A2,2 0 0,1 20,12A2,2 0 0,1 18,14A2,2 0 0,1 16,12M10,12A2,2 0 0,1 12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12M4,12A2,2 0 0,1 6,10A2,2 0 0,1 8,12A2,2 0 0,1 6,14A2,2 0 0,1 4,12Z"></path>
</svg>';

  /**
   * Generate an action menu placeholder.
   *
   * @param array $data The data which will be attached to the placeholder element.
   *
   * @return string
   */
  protected function generateActionMenuToggle(array $data):string
  {
    $data = htmlspecialchars(json_encode($data));
    return '<span class="vue-action-menu-placeholder ' . static::TEMPLATE . ' flex-container flex-center" data-action-menu=\'' . $data . '\'>
  <button class="trigger vue-mount-point flex-container flex-center flex-justify-center" style="margin:0;padding:0;">
    <span class="button-icon flex-container flex-center flex-justify-center" style="width:34px;height:34px">' . self::DOTS_SVG . '</span>
  </button>
</span>';
  }

  /**
   * @param array $opts Reference to the legacy PME options array.
   *
   * @param Closure $generateData If given a function which generates the data
   * to be attached to the menu toggle. If given and the closure returns null
   * then no menu button will be generated.
   *
   * @return array Return just the argument $opts, after modifying it.
   */
  protected function installActionMenuToggle(array &$opts, ?Closure $generateData = null)
  {
    if (empty($opts['navigation'])) {
      $opts['navigation'] = self::PME_NAVIGATION_NO_MULTI;
    }
    $opts['navigation'] .= 'C';
    $opts['options'] .= 'M';
    $opts['display']['custom_navigation'] = function(array $record, array $groupByRecord, array $row, PHPMyEdit $pme) use ($generateData):string {
      $data = $generateData
        ? $generateData($record, $groupByRecord, $row, $pme)
        : compact('record', 'groupByRecord', 'row');
      if ($data === null) {
        return '';
      }
      return $this->generateActionMenuToggle($data);
    };

    return $opts;
  }
}

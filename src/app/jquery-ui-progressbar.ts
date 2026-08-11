/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * @file
 *
 * Collect some jQuery tweaks in this file.
 */

import $ from './jquery.ts';

import 'jquery-ui/ui/widgets/progressbar';

type Progressbar = JQueryUI.Progressbar & JQueryUI.WidgetCommonProperties;

$.widget('ui.progressbar', $.ui.progressbar, {
  _create(this: Progressbar) {
    console.debug('OVERRIDE PROGRESSBAR');
    this._super();
  },
  _refreshValue(this: Progressbar) {
    this._super();
    const percentage = this._percentage();
    this.valueDiv.width(percentage.toFixed(6) + '%');
  },
});

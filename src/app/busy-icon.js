/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import './jquery-cafevdb-tooltips.js';
import globalState from './globalstate.js';
import { emit } from '@rotdrop/async-nextcloud-event-bus';
import { SET_BUSY_FLAG } from '../event-bus-events.js';

const busyIcon = function(on) {
  if (!globalState.vueMode) {
    const reloadButton = document.getElementById('reloadbutton');
    const images = [
      reloadButton?.querySelector('img.number-0'),
      reloadButton?.querySelector('img.number-1'),
    ];
    if (!images[0] || !images[1]) {
      return;
    }
    if (on) {
      images[0].style.display = 'none';
      images[1].style.display = 'block';
    } else {
      images[0].style.display = 'block';
      images[1].style.display = 'none';
    }
  }
  console.debug('SET BUSY FLAG TO', !!on);
  emit(SET_BUSY_FLAG, { value: !!on });
};

export default busyIcon;

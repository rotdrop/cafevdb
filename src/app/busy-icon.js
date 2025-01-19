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
import { emit } from '@nextcloud/event-bus';
import { PUSH_BUSY_STATE, POP_BUSY_STATE } from '../event-bus.ts';

const busyIcon = function(on) {
  const reloadButton = document.getElementById('reloadbutton');
  const images = [
    reloadButton?.querySelector('img.number-0'),
    reloadButton?.querySelector('img.number-1'),
  ];
  if (!images[0] || !images[1]) {
    return;
  }
  if (on) {
    console.debug('INCREASE BUSY STATE');
    emit(PUSH_BUSY_STATE, {});
    images[0].style.display = 'none';
    images[1].style.display = 'block';
    // $('#reloadbutton img.number-0').hide();
    // $('#reloadbutton img.number-1').show();
  } else {
    images[0].style.display = 'block';
    images[1].style.display = 'none';
    // $('#reloadbutton img.number-1').hide();
    // $('#reloadbutton img.number-0').show();
    emit(POP_BUSY_STATE, {});
    console.debug('DECREASE BUSY STATE');
  }
};

export default busyIcon;

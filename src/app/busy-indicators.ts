/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import pageBusyIcon from './busy-icon.ts';
import modalizer from './modalizer.ts';
import { tableDialogLoadIndicator } from './pme.ts';

/**
 * Switch busy-indicators on or off.
 *
 * @param state The target state.
 *
 * @param [$pmeContainer] PME dialog container, additionally enable
 * its local loading indicator.
 *
 * @param [modal] Whether to lock the UI, defaults to true.
 */
const setBusyIndicators = (state: boolean, $pmeContainer?: JQuery, modal: boolean = true) => {
  if (state) {
    if (modal) {
      modalizer(true);
    }
    if ($pmeContainer) {
      tableDialogLoadIndicator($pmeContainer, true);
    }
    pageBusyIcon(true);
  } else {
    pageBusyIcon(false);
    if ($pmeContainer) {
      tableDialogLoadIndicator($pmeContainer, false);
    }
    if (modal) {
      modalizer(false);
    }
  }
};

export default setBusyIndicators;

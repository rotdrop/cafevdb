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

import { appName } from './config.js';

// app events
export const APP_SETTINGS_POPUP = appName + ':app-settings-popup';
export const TOGGLE_TOOLTIPS = appName + ':toggle-tooltips';
export const PROJECT_POPUP = appName + ':project-popup';
export const PROJECT_INSTRUMENTATION_NUMBERS_POPUP = appName + ':project-instrumentation-numbers-popup';
export const PROJECT_PARTICIPANT_FIELDS_POPUP = appName + ':project-participant-fields-popup';
export const PROJECT_EVENTS_POPUP = appName + ':project-events-popup';
export const PUSH_BUSY_STATE = appName + ':push-busy-state';
export const POP_BUSY_STATE = appName + ':pop-busy-state';
export const GLOBAL_STATE = appName + ':global-state';
export const PME_STATE = appName + ':pme-state';

export const SET_FINANCE_MODE = appName + ':finance-mode';
export const SET_EXPERT_MODE = appName + ':expert-mode';
export const SET_SHOW_DISABLED = appName + ':show-disabled';
export const SET_DEBUG_MODES = appName + ':debug-modes';

// global events
export const TOGGLE_NAVIGATION = 'toggle-navigation';

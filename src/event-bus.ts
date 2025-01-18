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

export const appEventName = (tag: string) => appName + ':' + tag;

// app events
export const APP_SETTINGS_POPUP = appEventName(':app-settings-popup');
export const TOGGLE_TOOLTIPS = appEventName(':toggle-tooltips');
export const PROJECT_POPUP = appEventName(':project-popup');
export const PROJECT_INSTRUMENTATION_NUMBERS_POPUP = appEventName(':project-instrumentation-numbers-popup');
export const PROJECT_PARTICIPANT_FIELDS_POPUP = appEventName(':project-participant-fields-popup');
export const PROJECT_EVENTS_POPUP = appEventName(':project-events-popup');
export const PUSH_BUSY_STATE = appEventName(':push-busy-state');
export const POP_BUSY_STATE = appEventName(':pop-busy-state');
export const GLOBAL_STATE = appEventName(':global-state');
export const PME_STATE = appEventName(':pme-state');

export const SET_FINANCE_MODE = appEventName(':finance-mode');
export const SET_EXPERT_MODE = appEventName(':expert-mode');
export const SET_SHOW_DISABLED = appEventName(':show-disabled');
export const SET_DEBUG_MODES = appEventName(':debug-modes');
export const SET_TOOLTIPS_MODE = appEventName(':tooltips-mode');
export const SET_PAGE_ROWS = appEventName(':page-rows');
export const SET_DESELECT_INVISIBLE = appEventName(':page-rows');

// global events
export const TOGGLE_NAVIGATION = 'toggle-navigation';

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

import { appPrefix } from './config.ts';

export const appEventName = <T extends string>(tag: T) => appPrefix(tag, '::');

// app events
export const APP_SETTINGS_POPUP = appEventName('app-settings-popup');
export const CALENDAR_EVENT_ADD = appEventName('calendar-event-add');
export const CALENDAR_EVENT_EDIT = appEventName('calendar-event-edit');
export const HISTORY_GO_REQUEST = appEventName('history-go-request');
export const POP_BUSY_STATE = appEventName('pop-busy-state');
export const PROJECT_ACTIONS = appEventName('project-actions');
export const PROJECT_EMAIL_POPUP = appEventName('project-email-popup');
export const PROJECT_EVENTS_POPUP = appEventName('project-events-popup');
export const PROJECT_INSTRUMENTATION_NUMBERS_POPUP = appEventName('project-instrumentation-numbers-popup');
export const PROJECT_PARTICIPANT_FIELDS_POPUP = appEventName('project-participant-fields-popup');
export const PROJECT_POPUP = appEventName('project-popup');
export const PUSH_BUSY_STATE = appEventName('push-busy-state');
export const SET_BUSY_FLAG = appEventName('set-busy-flag');
export const TOGGLE_TOOLTIPS = appEventName('toggle-tooltips');
export const WIKI_POPUP = appEventName('wiki-popup');

export const SET_DEBUG_MODES = appEventName('set-debug-modes');
export const SET_DESELECT_INVISIBLE = appEventName('set-deselect-invsible');
export const SET_DIRECT_CHANGE = appEventName('set-direct-change');
export const SET_EXPERT_MODE = appEventName('set-expert-mode');
export const SET_FINANCE_MODE = appEventName('set-finance-mode');
export const SET_INITIAL_FILTER_VISIBILITY = appEventName('set-initial-filter-visibility');
export const SET_PAGE_ROWS = appEventName('set-page-rows');
export const SET_RESTORE_HISTORY = appEventName('set-restore-history');
export const SET_SHOW_DISABLED = appEventName('set-show-disabled');
export const SET_TOOLTIPS_MODE = appEventName('set-tooltips-mode');

export const LEGACY_AJAX_ERROR = appEventName('legacy-ajax-error');
export const LEGACY_PAGE_CLEANUP = appEventName('legacy-page-cleanup');
export const LEGACY_PAGE_FINALIZE = appEventName('legacy-page-finalize');
export const LEGACY_PAGE_LOAD = appEventName('legacy-page-load');
export const LEGACY_PME_UPDATE = appEventName('legacy-pme-update');
export const LEGACY_POST_META_DATA = appEventName('legacy-post-meta-data');

export const GET_VUE_COMPONENT = appEventName('get-vue-component');

// global events
export const TOGGLE_NAVIGATION = 'toggle-navigation';

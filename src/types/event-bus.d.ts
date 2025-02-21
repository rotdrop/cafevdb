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

import type { AsyncNextcloudEvents } from '@rotdrop/async-nextcloud-event-bus';
import { jqXHR } from '@types/jquery/misc.d.ts';

import {
  APP_SETTINGS_POPUP,
  CALENDAR_EVENT_ADD,
  CALENDAR_EVENT_EDIT,
  EMAIL_POPUP,
  GET_VUE_COMPONENT,
  HISTORY_GO_REQUEST,
  LEGACY_AJAX_ERROR,
  LEGACY_PAGE_CLEANUP,
  LEGACY_PAGE_FINALIZE,
  LEGACY_PAGE_LOAD,
  LEGACY_PME_UPDATE,
  LEGACY_SANITIZE_POST_DATA,
  POP_BUSY_STATE,
  PROJECT_ACTIONS,
  PROJECT_EVENTS_POPUP,
  PUSH_BUSY_STATE,
  SET_BUSY_FLAG,
  SET_DEBUG_MODES,
  SET_DESELECT_INVISIBLE,
  SET_DIRECT_CHANGE,
  SET_EXPERT_MODE,
  SET_FINANCE_MODE,
  SET_INITIAL_FILTER_VISIBILITY,
  SET_PAGE_ROWS,
  SET_RESTORE_HISTORY,
  SET_SHOW_DISABLED,
  SET_TOOLTIPS_MODE,
  TOGGLE_TOOLTIPS,
  WIKI_POPUP,
} from '../event-bus-events.ts';

import type { ComponentProps, PropsData } from '../mountable-component-names.ts';

declare module '@rotdrop/async-nextcloud-event-bus' {

  export type TemplatePostData = {
    template: string,
    projectId?: number,
    projectName?: string,
    musicianId?: number,
    [key: string|number]: any,
  }

  type Callbacks = {
    done?(): unknown,
    fail?(): unknown,
    always?(): unknown,
  };
  type SetterArgs<T = any> = {
    value: T,
    callbacks: Callbacks,
    showMessage?: (messages: string|string[]) => void,
    $control?: jQuery,
  };
  type BoolSetterArgs = SetterArgs<boolean>

  export interface EventArgs {
    // mapping of 'event name' => 'event type'
    [APP_SETTINGS_POPUP]: Callbacks,
    [CALENDAR_EVENT_ADD]: { mode: 'simple'|'sidebar', allDay: boolean, dtstart: number, dtend: number, context?: Record<string, any> },
    [CALENDAR_EVENT_EDIT]: { mode: 'simple'|'sidebar', objectId: string, recurrenceId: string, context?: Record<string, any> },
    [GET_VUE_COMPONENT]: { name: keyof ComponentProps, propsData: PropsData<keyof ComponentProps> }, // { name: keyof ComponentProps, propsData: ComponentProps[typeof name] },
    [HISTORY_GO_REQUEST]: { level: number },
    [LEGACY_AJAX_ERROR]: { xhr: jqXHR, message: string, html?: string },
    [LEGACY_PAGE_CLEANUP]: undefined,
    [LEGACY_PAGE_FINALIZE]: undefined,
    [LEGACY_PAGE_LOAD]: { post: TemplatePostData, template: string|null, projectId: number|null, projectName: string|undefined, keepHistory: boolean, },
    [LEGACY_PME_UPDATE]: { post: TemplatePostData, htmlBody: string, action: 'push'|'replace', },
    [LEGACY_SANITIZE_POST_DATA]: { post: TemplatePostData },
    [POP_BUSY_STATE]: undefined,
    [PROJECT_ACTIONS]: { projectId: number, open: boolean, x?: number, y?: number },
    [PROJECT_EMAIL_POPUP]: { projectId: number, projectName?: string, reopen?: boolean },
    [PROJECT_EVENTS_POPUP]: { projectId: number, projectName?: string, reopen?: boolean },
    [PUSH_BUSY_STATE]: undefined,
    [SET_BUSY_FLAG]: { value: boolean },
    [WIKI_POPUP]: { wikiPage: string, popupTitle: string },

    [SET_DEBUG_MODES]: SetterArgs<{ value: number }[]>,
    [SET_DESELECT_INVISIBLE]: BoolSetterArgs,
    [SET_DIRECT_CHANGE]: BoolSetterArgs,
    [SET_EXPERT_MODE]: BoolSetterArgs,
    [SET_FINANCE_MODE]: BoolSetterArgs,
    [SET_INITIAL_FILTER_VISIBILITY]: BoolSetterArgs,
    [SET_PAGE_ROWS]: SetterArgs<number>,
    [SET_RESTORE_HISTORY]: BoolSetterArgs,
    [SET_SHOW_DISABLED]: BoolSetterArgs,
    [SET_TOOLTIPS_MODE]: BoolSetterArgs,

    [TOGGLE_TOOLTIPS]: { enabled: boolean },
  }

  type KeysOfValue<T, TCondition> = {
    [K in keyof T]: T[K] extends TCondition
    ? K
    : never;
  }[keyof T]

  export type SetterEventKeys = KeysOfValue<EventArgs, SetterArgs>;
  export type SetterEvents = Pick<EventArgs, SetterEventKeys>;
  export type SetterEventValue<EventName extends SetterEventKeys> = SetterEvents[EventName]['value'];

  export interface AsyncNextcloudEvents extends EventArgs {
  }
}

// export {}

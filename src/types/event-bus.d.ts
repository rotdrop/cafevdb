/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

// import type { AsyncNextcloudEvents } from '@rotdrop/async-nextcloud-event-bus';
import { jqXHR } from '@types/jquery/misc.d.ts';
import { messages } from '../app/notification.ts';
import type { SearchArguments as SearchEntitiesArguments, search as searchEntities } from '../services/entity-repository.ts';

import {
  ADD_CONTACTS_TO_PROJECT,
  APP_SETTINGS_POPUP,
  EMAIL_POPUP,
  GET_VUE_COMPONENT,
  GLOBAL_STATE_INITIALIZED,
  HISTORY_GO_REQUEST,
  LEGACY_AJAX_ERROR,
  LEGACY_PAGE_CLEANUP,
  LEGACY_PAGE_FINALIZE,
  LEGACY_PAGE_LOAD,
  LEGACY_HISTORY_UPDATE,
  LEGACY_HISTORY_PATCH,
  LEGACY_RECORD_POPUP,
  LEGACY_SANITIZE_POST_DATA,
  LEGACY_UPDATE_EVENTS_SELECTION,
  PAGE_TEMPLATE_ACTION_MENU,
  POP_BUSY_STATE,
  PROJECT_EVENTS_LISTING,
  PROJECT_INSTRUMENTATION_NUMBERS_POPUP,
  PROJECT_PARTICIPANT_FIELDS_POPUP,
  PUSH_BUSY_STATE,
  SEARCH_DATABASE_ENTITIES,
  SET_BUSY_FLAG,
  SET_DEBUG_MODES,
  SET_DEBUG_QUERY_SQL_FILTER,
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

import type { Callbacks as AppSettingsCallbacks } from '../app/app-settings.ts';
import type { GlobalState } from '../app/globalstate.ts';
import type { EntityMap } from '../../build/ts-types/php-modules/Database/Doctrine/ORM/EntityMetadata.ts';
import type { LegacyPageActionsMenu } from './components';

declare module '@rotdrop/async-nextcloud-event-bus' {

  export type TemplatePostData = {
    template?: string,
    projectId?: number,
    projectName?: string,
    musicianId?: number,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    [key: PropertyKey]: any,
  }

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  type SetterArgs<T = any, E extends HTMLElement = HTMLElement> = {
    value: T,
    showMessage?: typeof messages,
    $control?: JQuery<E>,
  };
  type BoolSetterArgs = SetterArgs<boolean, HTMLInputElement>

  export interface EventArgs {
    // mapping of 'event name' => { arg: 'event type', res: result type }
    [ADD_CONTACTS_TO_PROJECT]: { projectName: string },
    [APP_SETTINGS_POPUP]: AppSettingsCallbacks,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    [EMAIL_POPUP]: { projectId?: number, projectName?: string, reopen?: boolean, post?: Record<string, any> },
    [GET_VUE_COMPONENT]: {
      name: keyof ComponentProps,
      propsData: PropsData<keyof ComponentProps>,
    }, // { name: keyof ComponentProps, propsData: ComponentProps[typeof name] },
    [GLOBAL_STATE_INITIALIZED]: GlobalState,
    [HISTORY_GO_REQUEST]: { level: number },
    [LEGACY_AJAX_ERROR]: { xhr: jqXHR, message: string, html?: string },
    [LEGACY_PAGE_CLEANUP]: undefined,
    [LEGACY_PAGE_FINALIZE]: undefined,
    [LEGACY_PAGE_LOAD]: { post: TemplatePostData, template?: string|null, projectId?: number|null, projectName?: string|undefined, keepHistory: boolean, },
    [LEGACY_HISTORY_UPDATE]: { post: TemplatePostData, htmlBody: string, action: 'push'|'replace', },
    [LEGACY_HISTORY_PATCH]: { patch: TemplatePostData, action: 'push'|'replace', },
    [LEGACY_RECORD_POPUP]: { entityId: number, projectId?: number, projectName?: string, template: string },
    [LEGACY_SANITIZE_POST_DATA]: { post: TemplatePostData },
    [LEGACY_UPDATE_EVENTS_SELECTION]: { origin?: string, projectId: number, projectName?: string, selection: string[] },
    [PAGE_TEMPLATE_ACTION_MENU]: { template: string, entityId: number, open: boolean, x?: number, y?: number },
    [POP_BUSY_STATE]: undefined,
    [PROJECT_EVENTS_LISTING]: { projectName: string },
    [PROJECT_INSTRUMENTATION_NUMBERS_POPUP]: { projectId: number, projectName: string },
    [PROJECT_PARTICIPANT_FIELDS_POPUP]: { projectId: number, projectName: string },
    [PUSH_BUSY_STATE]: undefined,
    [SET_BUSY_FLAG]: { value: boolean },
    [WIKI_POPUP]: { wikiPage: string, popupTitle: string },

    [SEARCH_DATABASE_ENTITIES]: SearchEntitiesArguments<keyof EntityMap, number, null|number, number>,
    [SET_DEBUG_MODES]: SetterArgs<{ value: number }[], HTMLSelectElement>,
    [SET_DEBUG_QUERY_SQL_FILTER]: SetterArgs<string, HTMLSelectElement>,
    [SET_DESELECT_INVISIBLE]: BoolSetterArgs,
    [SET_DIRECT_CHANGE]: BoolSetterArgs,
    [SET_EXPERT_MODE]: BoolSetterArgs,
    [SET_FINANCE_MODE]: BoolSetterArgs,
    [SET_INITIAL_FILTER_VISIBILITY]: BoolSetterArgs,
    [SET_PAGE_ROWS]: SetterArgs<number, HTMLSelectElement>,
    [SET_RESTORE_HISTORY]: BoolSetterArgs,
    [SET_SHOW_DISABLED]: BoolSetterArgs,
    [SET_TOOLTIPS_MODE]: BoolSetterArgs,

    [TOGGLE_TOOLTIPS]: { enabled: boolean },
  }

  export interface EventResults {
    /* Vue action menus opened in legacy pages */
    [GET_VUE_COMPONENT]: LegacyPageActionsMenu,
    /** The current value of the counter. */
    [PUSH_BUSY_STATE]: number,
    /** The current value of the counter. */
    [POP_BUSY_STATE]: number,
    /** The prior state of the flag. */
    [SET_BUSY_FLAG]: boolean,
    /** Entity search result */
    [SEARCH_DATABASE_ENTITIES]: Awaited<ReturnType<typeof searchEntities<keyof EntityMap> > >,
    /* Sanitizing post data */
    [LEGACY_SANITIZE_POST_DATA]: TemplatePostData,
  }

  type KeysOfValue<T, TCondition> = {
    [K in keyof T]: T[K] extends TCondition
    ? K
    : never;
  }[keyof T]

  export type SetterEventKeys = KeysOfValue<EventArgs, SetterArgs>;
  export type SetterEvents = Pick<EventArgs, SetterEventKeys>;
  export type SetterEventValue<EventName extends SetterEventKeys> = SetterEvents[EventName]['value'];

  type IsUndefined<T> = [T] extends [undefined] ? true : false

  export type Events = {
    [K in keyof EventArgs]: {
      arg: EventArgs[K],
      res: K extends keyof EventResults ? EventResults[K] : unknown,
    }
  };

  export interface AsyncNextcloudEvents extends Events {
  }
}

// export {}

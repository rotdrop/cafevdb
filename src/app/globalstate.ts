/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2025, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/** @file just provide the globalState object */

import type { PHPMyEditState } from './pme-state.ts';
import type { EventBus } from '@rotdrop/async-nextcloud-event-bus';
import { showMessage } from '@nextcloud/dialogs';
import type { EnumPersonalSettingsKey } from '../../build/ts-types/php-modules/Controller.ts';

type Toastify = ReturnType<typeof showMessage>;

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type ReadyCallback = () => Promise<any>;

export interface GlobalState {
  appName: string,
  PHPMyEdit: PHPMyEditState,
  vueMode?: boolean,
  eventBus?: EventBus,
  orchestra: string,
  wikiNameSpace: string,
  toolTipsEnabled: boolean,
  userPermissions: number,
  uploadMaxFileSize?: number,
  [EnumPersonalSettingsKey.FINANCE_MODE]: boolean,
  [EnumPersonalSettingsKey.DEBUG_MODE]: number,
  [EnumPersonalSettingsKey.DEBUG_QUERY_SQL_FILTER]: string,
  [EnumPersonalSettingsKey.EXPERT_MODE]: boolean,
  [EnumPersonalSettingsKey.RESTORE_HISTORY]: boolean,

  currencyCode: string,
  currencySymbol: string,
  locale: string,
  cloudLanguage: string,
  serverRoot: string,

  initialized?: boolean,

  wysiwygEditor?: string,

  creditsTimer?: NodeJS.Timeout,

  Notification?: { toasts: Toastify[] },

  oldWidth?: number,
  oldHeight?: number,

  nonce: null|string,
  initialNonce: null|string,

  language: string,

  windowResizeTimeout?: NodeJS.Timeout,

  readyCallbacks: ReadyCallback[],

  subscribe: Record<string, boolean>,

  sharedFolder: string,
}

declare global {
  // eslint-disable-next-line no-var
  var CAFEVDB: GlobalState;
}

/**
 * Typescript does not complain although the assignment is
 * "stupid". Note that we use the global scope here in order to
 * separate the legacy code from the Vue-wrapper. The Vue-wrapper
 * needs access to the global state variable, but we do not want to
 * pull in all the legacy code into the wrapper asset. We therefore
 * attach the global state to the window object and do a minimal
 * initialization here.
 */
// @ts-expect-error 2322
const globalState: GlobalState = globalThis.CAFEVDB = (globalThis.CAFEVDB as GlobalState|undefined) ?? { PHPMyEdit: {} };

export default globalState;

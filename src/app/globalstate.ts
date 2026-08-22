/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { showMessage } from '@nextcloud/dialogs';
import type { EnumPersonalSettingsKey } from '../../build/ts-types/php-modules/Controller.ts';
import type { CAFEVDBInitialState } from '../../build/ts-types/php-modules/Controller/DTO.ts';
import type { PHPMyEditState } from './pme-state.ts';

type Toastify = ReturnType<typeof showMessage>;

export interface GlobalState extends CAFEVDBInitialState {
  appName: string;
  PHPMyEdit: PHPMyEditState;
  orchestra: string;
  wikiNameSpace: string;
  toolTipsEnabled: boolean;
  userPermissions: number;
  uploadMaxFileSize: number;
  [EnumPersonalSettingsKey.FINANCE_MODE]: boolean;
  [EnumPersonalSettingsKey.DEBUG_MODE]: number;
  [EnumPersonalSettingsKey.DEBUG_QUERY_SQL_FILTER]: string;
  [EnumPersonalSettingsKey.EXPERT_MODE]: boolean;
  [EnumPersonalSettingsKey.RESTORE_HISTORY]: boolean;

  currencyCode: string;
  currencySymbol: string;
  locale: string;
  cloudLanguage: string;
  serverRoot: string;

  initialized?: boolean;

  wysiwygEditor: string;

  creditsTimer?: NodeJS.Timeout;

  Notification?: { toasts: Toastify[] };

  oldWidth?: number;
  oldHeight?: number;

  nonce: null|string;

  language: string;

  windowResizeTimeout?: NodeJS.Timeout;

  sharedFolder: string;
}

export const legacyGlobalState: GlobalState = { initialized: false, PHPMyEdit: { initialized: false } } as GlobalState;

let globalStateObject: GlobalState = legacyGlobalState;

const globalState = new Proxy(legacyGlobalState, {
  get(_target, property) {
    return Reflect.get(globalStateObject, property);
  },
  set(_target, property, value) {
    return Reflect.set(globalStateObject, property, value);
  },
});

export const setGlobalStateObject = (state: GlobalState) => {
  globalStateObject = state;
  for (const key of Object.keys(legacyGlobalState.PHPMyEdit) as (keyof PHPMyEditState)[]) {
    delete legacyGlobalState.PHPMyEdit[key];
  }
  for (const key of Object.keys(legacyGlobalState) as (keyof GlobalState)[]) {
    delete legacyGlobalState[key];
  }
  console.debug('LEGACY GLOBAL STATE REPLACED', {
    state,
    legacyGlobalState,
  });
};

export default globalState;

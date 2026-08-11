/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**
 * @file
 *
 * General PME table stuff, popup-handling.
 */

import type { PMEInitialState } from '../../build/ts-types/php-modules/Controller/DTO.ts';
import type { EnumTemplate } from '../../build/ts-types/php-modules/PageRenderer.ts';
import type { TemplateRenderer } from './template-renderer.ts';

import { GLOBAL_STATE_INITIALIZED } from '../event-bus-events.ts';
import { emit as asyncEmit } from '../services/async-event-bus.ts';
import { appName, initialState } from './config.ts';
import globalState from './globalstate.ts';

export type PageTemplateValue = `${EnumTemplate}`;

export type TableDialogOptions<S extends PageTemplateValue = PageTemplateValue> = {
  ambientContainerSelector: string;
  dialogHolderCSSId: string;
  reloadName: string;
  reloadValue: string;
  reloadMode?: 'discard';
  initialViewOperation: boolean;
  initialName: string;
  initialValue: string;
  modified: boolean;
  templateRenderer: TemplateRenderer<S>;
  template: S;
  modalDialog?: boolean;
  projectId?: number;
  projectName?: string;
};

export type RejectTuple = {
  xhr: JQuery.jqXHR;
  status: string;
  errorThrown: string;
};

export type TriggerData = {
  resolve?: (result: 'reloaded'|'cancelled'|'deleted'|'invalid') => void;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  reject?: (arg: RejectTuple) => Promise<any>;
  setup?: boolean;
  postOpen?: ($dialogHolder: JQuery) => void;
};

export type TableDialogCallbackData<S extends PageTemplateValue = PageTemplateValue> = {
  reason?: 'dialogOpen'|'dialogClose'|'layoutChange'|'tabChange'|'unknown'|'formSubmit';
  htmlResponse?: string;
  closedBy?: string;
  triggerData?: TriggerData;
  tableDialogOptions?: TableDialogOptions<S>;
};

export type TableLoadCallback<T extends PageTemplateValue = PageTemplateValue> = {
  callback(
    template: T,
    selector: string,
    parameters: TableDialogCallbackData,
    resizeCB: () => void,
  ): void;
  context?: unknown;
};

export interface PHPMyEditState extends PMEInitialState {
  directChange: boolean;
  filterSelectPlaceholder: string;
  filterSelectNoResult: string;
  selectChosen: boolean;
  filterSelectChosenTitle: string;
  inputSelectPlaceholder: string;
  inputSelectNoResult: string;
  inputSelectChosenTitle: string;
  pmePrefix: string;
  singleDeselectOffset: number;
  defaultSelector: string;

  /* actual volatile state variables */
  tableLoadCallbacks: Record<string, TableLoadCallback>;
  openDialogs: Record<string, boolean>;

  stopped: boolean;

  pageRowsDefault: number;
  deselectInvisibleMiscRecs: boolean;
  showDisabled: boolean;
  initialFilterVisibility: boolean;

  initialized?: boolean;

  emit: boolean;

  restoreHistory?: boolean;
  dialogCSSId: string;
}

const PHPMyEditDefault = {
  directChange: false,
  filterSelectPlaceholder: 'Select a filter Option',
  filterSelectNoResult: 'No values match',
  selectChosen: true,
  filterSelectChosenTitle: 'Select from the pull-down menu. Double-click will submit the form.',
  inputSelectPlaceholder: 'Select an option',
  inputSelectNoResult: 'No values match',
  inputSelectChosenTitle: 'Select from the pull-down menu.',
  pmePrefix: 'pme',
  singleDeselectOffset: 18,
  defaultSelector: `#${appName}-page-body`, // for delegate handlers, survives pseudo-submit

  /* actual volatile state variables */
  tableLoadCallbacks: {},
  openDialogs: {},

  stopped: false,
};

/****************************************************************************
 *
 * Mix-in PHP setup parameters.
 *
 */

const oldInitialized = globalState.initialized && globalState.PHPMyEdit.initialized;

const PHPMyEdit: PHPMyEditState = Object.assign(
  globalState.PHPMyEdit,
  {

    ...PHPMyEditDefault,
    ...initialState.PHPMyEdit,
    ...globalState.PHPMyEdit, // safe-guard against accidental multipled execution
    initialized: true,
  },
);

PHPMyEdit.dialogCSSId = PHPMyEdit.pmePrefix + '-table-dialog';

if (!oldInitialized && globalState.initialized && globalState.PHPMyEdit.initialized) {
  asyncEmit(GLOBAL_STATE_INITIALIZED, globalState);
}

const pmeDefaultSelector = PHPMyEdit.defaultSelector;
const pmePrefix = PHPMyEdit.pmePrefix;
const PMEPrefix = pmePrefix.toUpperCase();
const pmeOpenDialogs = PHPMyEdit.openDialogs;

export {
  appName,
  pmeDefaultSelector as defaultSelector,
  globalState,
  pmeOpenDialogs as openDialogs,
  PHPMyEdit,
  pmePrefix as prefix,
  PMEPrefix as ucPrefix,
};

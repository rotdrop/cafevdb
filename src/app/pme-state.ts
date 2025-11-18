/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import globalState from './globalstate.ts';
import { initialState, appName } from './config.ts';

export type TableDialogOptions = {
  ambientContainerSelector: string;
  dialogHolderCSSId: string;
  reloadName: string;
  reloadValue: string;
  reloadMode?: 'discard';
  initialViewOperation: boolean;
  initialName: string;
  initialValue: string;
  modified: boolean;
  templateRenderer: string;
  template?: string;
  modalDialog?: boolean;
  projectId?: number;
  projectName?: string;
  table?: string;
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
  postOpen?: ($dialogHolder: JQuery) => void
};

export type TableDialogCallbackData = {
  reason?: 'dialogOpen'|'dialogClose'|'layoutChange'|'tabChange'|'unknown'|'formSubmit';
  htmlResponse?: string;
  closedBy?: string;
  triggerData?: TriggerData;
  tableDialogOptions?: TableDialogOptions;
};

export type TableLoadCallback = {
  callback(
    selector: string,
    parameters: TableDialogCallbackData,
    resizeCB: () => void,
    ...rest: unknown[]
  ):void,
  parameters: unknown[],
  context?: unknown,
}

export interface PHPMyEditState {
  directChange: boolean,
  filterSelectPlaceholder: string,
  filterSelectNoResult: string,
  selectChosen: boolean,
  filterSelectChosenTitle: string,
  inputSelectPlaceholder: string,
  inputSelectNoResult: string,
  inputSelectChosenTitle: string,
  pmePrefix: string,
  singleDeselectOffset: number,
  defaultSelector: string,

  /* actual volatile state variables */
  tableLoadCallbacks: Record<string, TableLoadCallback>,
  openDialogs: Record<string, boolean>,

  stopped: boolean,

  pageRenderer: {
    masterFieldSuffix: string,
    valuesTableSep: string,
    joinKeySep: string,
    compKeySep: string,
    joinFieldNameSeparator: string,
  },

  pageRowsDefault: number,
  deselectInvisibleMiscRecs: boolean,
  showDisabled: boolean,
  initialFilterVisibility: boolean,

  initialized?: boolean,

  emit: boolean,

  restoreHistory?: boolean,
  dialogCSSId: string,
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
  defaultSelector: '#' + appName + '-page-body', // for delegate handlers, survives pseudo-submit

  /* actual volatile state variables */
  tableLoadCallbacks: {},
  openDialogs: {},

  stopped: false,

  pageRenderer: {
    masterFieldSuffix: '__master_key',
    valuesTableSep: '@',
    joinKeySep: ':',
    compKeySep: '-',
    joinFieldNameSeparator: ':',
  },
};

/****************************************************************************
 *
 * Mix-in PHP setup parameters.
 *
 */

const PHPMyEdit: PHPMyEditState = Object.assign(
  globalState.PHPMyEdit,
  Object.assign(
    {},
    PHPMyEditDefault,
    initialState.PHPMyEdit,
    globalState.PHPMyEdit, // safe-guard against accidental multipled execution
    { initialized: true },
  ));

PHPMyEdit.dialogCSSId = PHPMyEdit.pmePrefix + '-table-dialog';

const pmeDefaultSelector = PHPMyEdit.defaultSelector;
const pmePrefix = PHPMyEdit.pmePrefix;
const PMEPrefix = pmePrefix.toUpperCase();
const pmeOpenDialogs = PHPMyEdit.openDialogs;
const pmePageRenderer = PHPMyEdit.pageRenderer;

export {
  globalState,
  appName,
  PHPMyEdit,
  pmeDefaultSelector as defaultSelector,
  pmePrefix as prefix,
  PMEPrefix as ucPrefix,
  pmeOpenDialogs as openDialogs,
  pmePageRenderer as pageRenderer,
};

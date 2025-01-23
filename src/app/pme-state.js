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

import globalState from './globalstate.js';
import { initialState, appName } from './config.js';
import { emit } from '@rotdrop/async-nextcloud-event-bus';
import * as BusEvents from '../event-bus-events.js';

const PHPMyEditDefault = {
  directChange: false,
  filterSelectPlaceholder: 'Select a filter Option',
  filterSelectNoResult: 'No values match',
  selectChosen: true,
  filterSelectChosenTitle: 'Select from the pull-down menu. Double-click will submit the form.',
  inputSelectPlaceholder: 'Select an option',
  inputSelectNoResult: 'No values match',
  inputSelectChosenTitle: 'Select from the pull-down menu.',
  chosenPixelWidth: [],
  pmePrefix: 'pme',
  singleDeselectOffset: 18,
  defaultSelector: '#' + appName + '-page-body', // for delegate handlers, survives pseudo-submit
  defaultInnerSelector: 'inner', // to override delegate handlers, survices pseudo-submit

  /* actual volatile state variables */
  tableLoadCallbacks: [],
  openDialogs: {},

  stopped: false,

  pageRenderer: {
    masterFieldSuffix: '__master_key',
    valuesTableSep: '@',
    joinKeySep: ':',
    compKeySep: '-',
    joinFieldNameSeparator: ':',
  },

  emit: false,
};

/****************************************************************************
 *
 * Mix-in PHP setup parameters.
 *
 */

const PHPMyEdit = globalState.PHPMyEdit = globalState.PHPMyEdit || Object.assign(PHPMyEditDefault, initialState.PHPMyEdit);
PHPMyEdit.dialogCSSId = PHPMyEdit.pmePrefix + '-table-dialog';
if (!PHPMyEdit.emit) {
  PHPMyEdit.emit = true;
  emit(BusEvents.PME_STATE, {
    state: PHPMyEdit,
  });
}
console.info('PHPMyEdit Initial State', { ...globalState.PHPMyEdit }, { ...initialState.PHPMyEdit });

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

/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * @file
 *
 * General PME table stuff, popup-handling.
 */

import { initialState, appName } from './config.js';
import { globalState } from './globals.js';

const PHPMyEdit = {
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
};

PHPMyEdit.dialogCSSId = PHPMyEdit.pmePrefix + '-table-dialog';

/****************************************************************************
 *
 * Mix-in PHP setup parameters.
 *
 */

globalState.PHPMyEdit = $.extend(PHPMyEdit, initialState.PHPMyEdit);

const pmeDefaultSelector = PHPMyEdit.defaultSelector;
const pmePrefix = PHPMyEdit.pmePrefix;
const PMEPrefix = pmePrefix.toUpperCase();
const pmeOpenDialogs = PHPMyEdit.openDialogs;

export {
  globalState,
  appName,
  PHPMyEdit,
  pmeDefaultSelector as defaultSelector,
  pmePrefix as prefix,
  PMEPrefix as ucPrefix,
  pmeOpenDialogs as openDialogs,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

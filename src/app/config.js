/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

const appInfo = require('appinfo/info.xml');
const appName = appInfo.info.id[0];

const initialState = {
  appName,
  CAFEVDB: {},
  PHPMyEdit: {},
};

try {
  const state = OCP.InitialState.loadState(appName, 'CAFEVDB');
  initialState.CAFEVDB = state;
  console.log('CAFEVDB INITIAL STATE', initialState.CAFEVDB);
  if (appName !== initialState.CAFEVDB.appName) {
    throw new Error('appName / CAFEVDB.appName are different: ' + appName + ' / ' + initialState.CAFEVDB.appName);
  }
} catch (error) {
  console.info('Failed to load initial state for CAFEVDB', error);
}
try {
  const state = OCP.InitialState.loadState(appName, 'PHPMyEdit');
  initialState.PHPMyEdit = state;
  console.log('PHPMyEdit INITIAL STATE', initialState.PHPMyEdit);
} catch (error) {
  console.info('Failed to load initial state for PHPMyEdit', error);
}

const PHPMyEdit = initialState.PHPMyEdit;
const CAFEVDB = initialState.CAFEFDB;
const webRoot = OC.appswebroots[appName] + '/';

export {
  initialState,
  CAFEVDB,
  PHPMyEdit,
  appName,
  appInfo,
  webRoot,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import * as ncAuth from '@nextcloud/auth';
import { getRootUrl as getCloudRootUrl, getAppRootUrl } from '@nextcloud/router';
import appInitialState from '../toolkit/services/InitialStateService.js';
import { appName, appPrefix, appNameTag } from '../config.ts';

const initialState = {
  appName,
  CAFEVDB: {},
  PHPMyEdit: {},
};

try {
  const state = appInitialState('CAFEVDB');
  initialState.CAFEVDB = state;
  console.debug('CAFEVDB INITIAL STATE', initialState.CAFEVDB);
  if (appName !== initialState.CAFEVDB.appName) {
    throw new Error('appName / CAFEVDB.appName are different: ' + appName + ' / ' + initialState.CAFEVDB.appName);
  }
} catch (error) {
  console.error('Failed to load initial state for CAFEVDB', error);
}
try {
  const state = appInitialState('PHPMyEdit');
  initialState.PHPMyEdit = state;
  console.debug('PHPMyEdit INITIAL STATE', initialState.PHPMyEdit);
} catch (error) {
  console.error('Failed to load initial state for PHPMyEdit', error);
}

const PHPMyEdit = initialState.PHPMyEdit;
const CAFEVDB = initialState.CAFEFDB;
const cloudWebRoot = getCloudRootUrl();
const webRoot = getAppRootUrl(appName) + '/';
const cloudUser = ncAuth.getCurrentUser();

export {
  initialState,
  CAFEVDB,
  PHPMyEdit,
  appName,
  webRoot,
  cloudWebRoot,
  cloudUser,
  appPrefix,
  appNameTag,
};

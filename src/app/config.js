/* Orchestra member, musicion and project management application.
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
const initialState = {
  appName: __APP_NAME__
};

try {
  initialState.CAFEVDB = OCP.InitialState.loadState(__APP_NAME__, 'CAFEVDB');
  console.log('CAFEVDB INITIAL STATE', initialState.CAFEVDB);
  if (__APP_NAME__ !== initialState.CAFEVDB.appName) {
    throw new Error('__APP_NAME__ / CAFEVDB.appName are different: ' + __APP_NAME__ +  ' / ' + initialState.CAFEVDB.appName);
  }
} catch (error) {
  console.info('Failed to load initial state for CAFEVDB');
  initialState.CAFEVDB = {};
}
try {
  initialState.PHPMYEDIT = OCP.InitialState.loadState(__APP_NAME__, 'PHPMYEDIT');
  console.log('PHPMYEDIT INITIAL STATE', initialState.PHPMYEDIT);
} catch (error) {
  console.info('Failed to load initial state for PHPMYEDIT');
  initialState.PHPMYEDIT = {};
}

// @TODO remove
window.CAFEVDB = window.CAFEVDB || {};
window.CAFEVDB.initialState = initialState.CAFEVDB;
window.PHPMYEDIT = window.PHPMYEDIT || {};
window.PHPMYEDIT.initialState = initialState.PHPMYEDIT;

export { initialState };

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

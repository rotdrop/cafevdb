/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

const appInfo = require('appinfo/info.xml');
const appName = appInfo.info.id[0];
const appVersion = appInfo.info.version[0];

/**
 * Prefix the given id with the app-name, joined by '-'.
 *
 * @param {string} id TBD.
 *
 * @param {string} join The join character, defaults to '-'.
 *
 * @returns {string}
 */
function appPrefix(id, join) { return appName + (join || '-') + id; }

const appNameTag = 'app-' + appName;

export default appInfo;
export {
  appName,
  appVersion,
  appInfo,
  appPrefix,
  appNameTag,
};

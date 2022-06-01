/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import generateAppUrl from './generate-url.js';

/**
 * Generate an URL for the settings-controllers.
 *
 * @param {string} url TBD.
 *
 * @param {object} urlParams TBD.
 *
 * @param {object} urlOptions TBD.
 *
 * @returns {string}
 */
function generateSettingsUrl(url, urlParams, urlOptions) {
  return generateAppUrl('settings/' + url, urlParams, urlOptions);
}

/**
 * Generate a setter-URL for the personal-settings-controller.
 *
 * @param {string} url TBD.
 *
 * @param {object} urlParams TBD.
 *
 * @param {object} urlOptions TBD.
 *
 * @returns {string}
 */
function setPersonalUrl(url, urlParams, urlOptions) {
  return generateSettingsUrl('personal/set/' + url, urlParams, urlOptions);
}

/**
 * Generate a setter-URL for the app-settings-controller.
 *
 * @param {string} url TBD.
 *
 * @param {object} urlParams TBD.
 *
 * @param {object} urlOptions TBD.
 *
 * @returns {string}
 */
function setAppUrl(url, urlParams, urlOptions) {
  return generateSettingsUrl('app/set/' + url, urlParams, urlOptions);
}

/**
 * Generate a getter-URL for the settings-controllers.
 *
 * @param {string} url TBD.
 *
 * @param {object} urlParams TBD.
 *
 * @param {object} urlOptions TBD.
 *
 * @returns {string}
 */
function getUrl(url, urlParams, urlOptions) {
  return generateSettingsUrl('get/' + url, urlParams, urlOptions);
}

/**
 * Generate a getter-URL for the app-settings-controller.
 *
 * @param {string} url TBD.
 *
 * @param {object} urlParams TBD.
 *
 * @param {object} urlOptions TBD.
 *
 * @returns {string}
 */
function getAppUrl(url, urlParams, urlOptions) {
  return generateSettingsUrl('get/app' + url, urlParams, urlOptions);
}

/**
 * Generate a getter-URL for the personal-settings-controller.
 *
 * @param {string} url TBD.
 *
 * @param {object} urlParams TBD.
 *
 * @param {object} urlOptions TBD.
 *
 * @returns {string}
 */
function getPersonalUrl(url, urlParams, urlOptions) {
  return generateSettingsUrl('get/personal' + url, urlParams, urlOptions);
}

export {
  generateSettingsUrl as generateUrl,
  setPersonalUrl,
  setAppUrl,
  getUrl,
  getAppUrl,
  getPersonalUrl,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

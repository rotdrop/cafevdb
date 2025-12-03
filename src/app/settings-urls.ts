/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { EnumPersonalSettingsKey } from '../../build/ts-types/php-modules/Controller.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import type { UrlOptions } from '@nextcloud/router';

/**
 * Generate an URL for the settings-controllers.
 *
 * @param url TBD.
 *
 * @param [urlParams] TBD.
 *
 * @param [urlOptions] TBD.
 */
const generateSettingsUrl = (
  url: string,
  urlParams?: Record<string, string|number|boolean|null>,
  urlOptions?: UrlOptions,
) => generateAppUrl('settings/' + url, urlParams, urlOptions);

/**
 * Generate a setter-URL for the personal-settings-controller.
 *
 * @param url TBD.
 *
 * @param [urlParams] TBD.
 *
 * @param [urlOptions] TBD.
 */
const setPersonalUrl = (
  url: EnumPersonalSettingsKey|`${EnumPersonalSettingsKey}`,
  urlParams?: Record<string, string|number|boolean|null>,
  urlOptions?: UrlOptions,
) => generateSettingsUrl('personal/set/' + url, urlParams, urlOptions);

/**
 * Generate a setter-URL for the app-settings-controller.
 *
 * @param url TBD.
 *
 * @param [urlParams] TBD.
 *
 * @param [urlOptions] TBD.
 */
const setAppUrl = (
  url: string,
  urlParams?: Record<string, string|number|boolean|null>,
  urlOptions?: UrlOptions,
) => generateSettingsUrl('app/set/' + url, urlParams, urlOptions);

/**
 * Generate a getter-URL for the settings-controllers.
 *
 * @param url TBD.
 *
 * @param [urlParams] TBD.
 *
 * @param [urlOptions] TBD.
 */
const getUrl = (
  url: string,
  urlParams?: Record<string, string|number|boolean|null>,
  urlOptions?: UrlOptions,
) => generateSettingsUrl('get/' + url, urlParams, urlOptions);

export {
  generateSettingsUrl as generateUrl,
  setPersonalUrl,
  setAppUrl,
  getUrl,
};

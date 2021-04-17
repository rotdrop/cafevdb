/**
 * Orchestra member, musicion and project management application.
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

import { appName } from './config.js';
import * as ncRouter from '@nextcloud/router';

/**
 * Generate an absolute URL for this app.
 *
 * @param {string} url The locate URL without app-prefix.
 *
 * @param {Object} urlParams Object holding url-parameters if url
 * contains parameters. "Excess" parameters will be appended as query
 * parameters to the URL.
 *
 * @param {Object} urlOptions Object with processing options
 * ```
 * {
 *   escape: BOOL,
 *   noRewrite: BOOL,
 * }
 * ```
 *
 * @returns {string}
 */
const generateUrl = function(url, urlParams, urlOptions) {
  // const str = '/image/{joinTable}/{ownerId}';
  let generated = ncRouter.generateUrl('/apps/' + appName + '/' + url, urlParams, urlOptions);
  const queryParams = { ...urlParams };
  for (const urlParam of url.matchAll(/{([^{}]*)}/g)) {
    delete queryParams[urlParam[1]];
  }
  const queryArray = [];
  for (const [key, value] of Object.entries(queryParams)) {
    queryArray.push(key + '=' + encodeURIComponent(value.toString()));
  }
  if (queryArray.length > 0) {
    generated += '?' + queryArray.join('&');
  }
  return generated;
};

export default generateUrl;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

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
 * File download support via iframe using jquery-file-download.
 */

import { appName, webRoot, $ } from './globals.js';
import generateUrl from './generate-url.js';
import generateId from './generate-id.js';
import * as Ajax from './ajax.js';

require('jquery-file-download');

/**
 * Place a download request by posting to the given Ajax URL.
 *
 * @param {String} url Relative download url, will be first fed in to
 * generateUrl().
 *
 * @param {Array} post Optional. Additional post-data.
 *
 * @param {Object} options Optional. Success and error callbacks
 * .done(), .fail(), .errorMessage().
 *
 */
const download = function(url, post, options) {
  const defaultOptions = {
    done(url) { console.info('DONE downloading', url); },
    fail(data) {},
    errorMessage(data, url) {
      return t(appName, 'Unable to download data from "{url}": ', { url });
    },
  };
  options = options || {};
  options = $.extend({}, defaultOptions, options);

  if (post === undefined) {
    post = [];
  }
  const cookieValue = generateId();
  const cookieName = appName + '_' + url.replace('/', '_') + '_' + 'download';
  post.push({ name: 'DownloadCookieName', value: cookieName });
  post.push({ name: 'DownloadCookieValue', value: cookieValue });
  post.push({ name: 'requesttoken', value: OC.requestToken });

  console.info('DOWNLOAD POST', post);

  $.fileDownload(
    generateUrl(url), {
      httpMethod: 'POST',
      data: post,
      cookieName,
      cookieValue,
      cookiePath: webRoot,
    })
    .fail(function(responseHtml, url) {
      // responseHtml may be wrapped into a pre-tag
      const pre = '<pre>';
      const erp = '</pre>';
      let response;
      if (responseHtml.substring(0, pre.length) === pre
          && responseHtml.substring(responseHtml.length - erp.length) === erp) {
        response = responseHtml.substring(pre.length, responseHtml.length - erp.length);
      } else {
        response = responseHtml;
      }

      let data;
      try {
        data = JSON.parse(response);
        data.parsed = true;
      } catch (e) {
        data = {
          error: Ajax.httpStatus[500],
          status: 500,
          message: responseHtml,
          parsed: false,
        };
      }

      data.error = Ajax.httpStatus[data.status];

      data.message = options.errorMessage(data, url) + data.message;

      Ajax.handleError(data, data.error, data.status /* ? */, data.fail);

    })
    .done(options.done);
};

export default download;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

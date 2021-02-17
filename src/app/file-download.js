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
  if (typeof options === 'string') { // error message
    const errorMessage = options;
    options = {
      errorMessage(data, url) { return errorMessage; }
    };
  }
  options = $.extend({}, defaultOptions, options);

  if (post === undefined) {
    post = [];
  }
  const cookieValue = generateId();
  const cookieName = appName + '_' + url.replace(/\W+/g, '_') + '_' + 'download';
  const cookiePost = [];
  cookiePost.push({ name: 'DownloadCookieName', value: cookieName });
  cookiePost.push({ name: 'DownloadCookieValue', value: cookieValue });
  cookiePost.push({ name: 'requesttoken', value: OC.requestToken });

  if (Array.isArray(post)) {
    post = post.concat(cookiePost)
  } else if (typeof post === 'string') {
    post += '&' + $.param(cookiePost, false);
  } else if (typeof post === 'object') {
    for (const param of cookiePost) {
      post[param.name] = param.value;
    }
  }

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

      let data = {};
      try {
        data = JSON.parse(response);
        data.parsed = true;
        console.info('JSON DATA', data);
      } catch (e) {
        console.info('ERROR', e);
        try {
          data.info = $(response).find('main').html();
          if (!data.info) {
            throw Error('no html');
          }
          data.status = 500;
          data.error = Ajax.httpStatus[data.status];
          data.message = t(
            appName,
            'HTTP error response to AJAX call: {code} / {error}',
            { code: data.status, error: data.error });
          data.parsed = true;
        } catch (e) {
          console.info('ERROR', e);
          data = {
            error: Ajax.httpStatus[500],
            status: 500,
            message: responseHtml,
            parsed: false,
          };
        }
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

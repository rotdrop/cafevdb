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
      errorMessage(data, url) { return errorMessage; },
    };
  }
  options = $.extend({}, defaultOptions, options);

  if (post === undefined) {
    post = [];
  } else if (!Array.isArray(post) && typeof post === 'object') {
    const newPost = [];
    for (const [name, value] of Object.entries(post)) {
      newPost.push({ name, value });
    }
    post = newPost;
  }
  const cookiePost = [];
  // eslint-disable-next-line no-constant-condition
  if (false) {
    const cookieValue = generateId();
    const cookieName = appName + '_' + url.replace(/\W+/g, '_') + '_' + 'download';
    cookiePost.push({ name: 'DownloadCookieName', value: cookieName });
    cookiePost.push({ name: 'DownloadCookieValue', value: cookieValue });
    cookiePost.push({ name: 'requesttoken', value: OC.requestToken });
  }

  if (Array.isArray(post)) {
    post = post.concat(cookiePost);
  } else if (typeof post === 'string') {
    post += '&' + $.param(cookiePost, false);
  } else if (typeof post === 'object') {
    for (const param of cookiePost) {
      post[param.name] = param.value;
    }
  }

  // eslint-disable-next-line no-constant-condition
  if (true) {
    const downloadUrl = generateUrl(url);
    $.ajax({
      url: downloadUrl,
      method: 'POST',
      cache: false,
      data: post,
      dataType: 'binary', // vital, otherwise jQuery annoyingly tries to parse the response
      xhr: function() {
        const xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
          if (xhr.readyState === 2) {
            if (xhr.status === 200) {
              xhr.responseType = 'blob';
            } else {
              xhr.responseType = 'text';
            }
          }
        };
        return xhr;
      },
    })
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown, options.fail);
      })
      .done(function(data, textStatus, xhr) {
        let fileName = 'download';
        const contentDisposition = xhr.getResponseHeader('Content-Disposition');
        if (contentDisposition && contentDisposition.indexOf('attachment') !== -1) {
          const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
          const matches = filenameRegex.exec(contentDisposition);
          if (matches != null && matches[1]) {
            fileName = matches[1].replace(/['"]/g, '');
          }
        }
        let contentType = xhr.getResponseHeader('Content-Type');
        if (contentType) {
          contentType = contentType.split(';')[0];
        } else {
          contentType ='application/octetstream';
        }
        console.info('DATA', data);
        // Convert the Byte Data to BLOB object.
        const blob = new Blob([data], { type: contentType });

        // Check the Browser type and download the File.
        const isIE = false || !!document.documentMode;
        if (isIE) {
          window.navigator.msSaveBlob(blob, fileName);
        } else {
          // eslint-disable-next-line node/no-unsupported-features/node-builtins
          const url = window.URL || window.webkitURL;
          const link = url.createObjectURL(blob);
          const a = $('<a />');
          a.attr('download', fileName);
          a.attr('href', link);
          $('body').append(a);
          a[0].click();
          $('body').remove(a);
          options.done(downloadUrl, data);
        }
      });

  } else {
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
        } catch (e) {
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
            data = {
              error: Ajax.httpStatus[500],
              status: 500,
              message: responseHtml,
              parsed: false,
            };
          }
        }

        if (!data.status) {
          // this is an error, after all ...
          data.status = Ajax.httpStatus.BAD_REQUEST;
        }

        if (!data.error) {
          data.error = Ajax.httpStatus[data.status];
        }

        data.message = options.errorMessage(data, url) + ' ' + data.message;

        Ajax.handleError(data, data.error, data.status /* ? */, options.fail);

      })
      .done(function(url) {
        options.done(url);
      });
  }
};

export default download;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

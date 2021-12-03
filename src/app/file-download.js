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

import { appName, $ } from './globals.js';
import generateUrl from './generate-url.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';
import * as ncRouter from '@nextcloud/router';
import { parse as parseContentDisposition } from 'content-disposition';

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
    always() {},
    errorMessage(url, data) {
      return t(appName, 'Unable to download data from "{url}": ', { url });
    },
  };
  options = options || {};
  if (typeof options === 'string') { // error message
    const errorMessage = options;
    options = {
      errorMessage(url, data) {
        return errorMessage;
      },
    };
  }
  options = $.extend({}, defaultOptions, options);
  const fail = options.fail;
  options.fail = function(data) {
    Notification.showTemporary(options.errorMessage(url, data));
    fail(data);
  };

  const method = post ? 'POST' : 'GET';
  post = post || [];
  if (!Array.isArray(post) && typeof post === 'object') {
    const newPost = [];
    for (const [name, value] of Object.entries(post)) {
      newPost.push({ name, value });
    }
    post = newPost;
  }

  const downloadUrl = (url.startsWith(ncRouter.generateUrl(''))
                       || url.startsWith(ncRouter.generateRemoteUrl('')))
    ? url
    : generateUrl(url);
  $.ajax({
    url: downloadUrl,
    method,
    cache: false,
    data: post,
    dataType: 'binary', // vital, otherwise jQuery annoyingly tries to parse the response
    xhr() {
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
      options.always();
    })
    .done(function(data, textStatus, xhr) {
      let fileName = 'download';
      const contentDisposition = xhr.getResponseHeader('Content-Disposition');
      if (contentDisposition) {
        const contentMeta = parseContentDisposition(contentDisposition);
        fileName = contentMeta.parameters.filename || fileName;
        console.info('CONTEN', contentMeta);
      }
      let contentType = xhr.getResponseHeader('Content-Type');
      if (contentType) {
        contentType = contentType.split(';')[0];
      } else {
        contentType = 'application/octetstream';
      }

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
        console.info('DOWNLOAD A', a);
        $('body').remove(a);
      }
      options.done(downloadUrl, data);
      options.always();
    });

};

export default download;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

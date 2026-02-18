/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2023, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.ts';
import { appName } from '../config.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import * as Ajax from './ajax.ts';
import * as Notification from './notification.ts';
import * as ncRouter from '@nextcloud/router';
import { parse as parseContentDisposition } from 'content-disposition';
import setBusyIndicators from './busy-indicators.ts';
import { translate as t } from '@nextcloud/l10n';

// still needed for jquery
require('../legacy/nextcloud/jquery/requesttoken.js');

const defaultOptions = {
  done(url: string, _down: Record<string, unknown>) { console.info('DONE downloading', url); },
  fail(_data: Record<string, unknown>) {},
  always() {
    setBusyIndicators(false, undefined, false);
  },
  setup() {
    setBusyIndicators(true, undefined, false);
  },
  errorMessage(url: string, data: Record<string, unknown> & { message?: string[] }) {
    const message = (data.message || [t(appName, 'unknown error')]).join(' | ');
    console.info('ERROR', url, data, message);
    return t(appName, 'Unable to download data from "{url}": {message}', { url, message });
  },
};

type Options = typeof defaultOptions;

/**
 * Place a download request by posting to the given Ajax URL.
 *
 * @param url Relative download url, will be first fed in to
 * generateAppUrl().
 *
 * @param [post] Optional. Additional post-data.
 *
 * @param [parameters] Success and error callbacks .done(), .fail(),
   .errorMessage().
 */
const download = (
  url: string,
  post?: JQuery.PlainObject|JQuery.NameValuePair[],
  parameters?: string|Partial<Options>,
) => {
  if (typeof parameters === 'string') { // error message
    const errorMessage = parameters;
    parameters = {
      errorMessage(_url: string, _data: Record<string, unknown>) {
        return errorMessage;
      },
    };
  }
  const options: Options = { ...defaultOptions, ...(parameters ?? {}) };
  const fail = options.fail;
  options.fail = function(data) {
    Notification.showTemporary(options.errorMessage(url, data));
    fail(data);
  };
  if (options.setup === defaultOptions.setup && options.always !== defaultOptions.always) {
    const always = options.always;
    options.always = function() {
      defaultOptions.always();
      always();
    };
  }

  const method = post ? 'POST' : 'GET';
  post = post ?? [];
  // if (false && !Array.isArray(post) && typeof post === 'object') {
  //   const newPost = [];
  //   for (const [name, value] of Object.entries(post)) {
  //     newPost.push({ name, value });
  //   }
  //   post = newPost;
  // }

  const downloadUrl = (url.startsWith(ncRouter.generateUrl(''))
                       || url.startsWith(ncRouter.generateRemoteUrl('')))
    ? url
    : generateAppUrl(url);

  options.setup();

  return $.ajax({
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
    .done(function(data, _textStatus, xhr) {
      let fileName = 'download';
      const contentDisposition = xhr.getResponseHeader('Content-Disposition');
      if (contentDisposition) {
        const contentMeta = parseContentDisposition(contentDisposition);
        fileName = contentMeta.parameters.filename || fileName;
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
      // @ts-expect-error 2551
      const isIE = false || !!document.documentMode;
      if (isIE) {
        // @ts-expect-error 2339
        window.navigator.msSaveBlob(blob, fileName);
      } else {
        // eslint-disable-next-line n/no-unsupported-features/node-builtins
        const url = window.URL || window.webkitURL;
        const link = url.createObjectURL(blob);
        const $a = $('<a />');
        $a.attr('download', fileName);
        $a.attr('href', link);
        $('body').append($a);
        $a[0].click();
        console.info('DOWNLOAD A', $a);
        $a.remove();
      }
      options.done(downloadUrl, data);
      options.always();
    });

};

export default download;

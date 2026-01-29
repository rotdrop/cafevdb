/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 - 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import globalState from './globalstate.ts';
import * as Dialogs from './dialogs.ts';
// @ts-expect-error 2595 confused by multiple version probablyx
import { isPlainObject } from 'is-plain-object';
import { getRootUrl as getCloudRootUrl } from '@nextcloud/router';
import { getLanguage, translate as t } from '@nextcloud/l10n';
import { emit as asyncEmit, hasSubscriptions } from '../services/async-event-bus.ts';
import { LEGACY_AJAX_ERROR } from '../event-bus-events.ts';
import l10nHttpStatus from '@http-util/status-i18n';
import { StatusCodes as HttpStatusCodes } from 'http-status-codes';
import { type NextcloudExceptionLogEntry, isNextcloudExceptionLogEntry } from '../types/ajax/php-exception-response.ts';
import type Keyable from '../types/keyable.d.ts';
import type { IException } from '@nextcloud/app-logreader/src/interfaces/ILogEntry.ts';
import { hasProperty } from '../types/type-traits.ts';

const cloudWebRoot = getCloudRootUrl() || '/';

const httpStatusText = (code: number, lang?: string) => l10nHttpStatus(code, lang || globalState.cloudLanguage || getLanguage() || 'en_US') ?? t(appName, 'unknown');

const stringifyTrace = (trace: NextcloudExceptionLogEntry['exception']['Trace']) => {
  const traceAsString: string[] = [];
  let count = 1;
  for (const traceLine of trace) {
    traceAsString.push(`${count++}. ${traceLine.file} - ${t(appName, 'line')} ${traceLine.line}: ${traceLine.class}${traceLine.type}${traceLine.function}()`);
  }
  return traceAsString.join('\n');
};

export type AjaxFailData<T = unknown> = Partial<T> & Partial<NextcloudExceptionLogEntry> & {
  error: string;
  status: string;
  caption?: string;
  messages: string[];
  xhr: JQuery.jqXHR;
  info?: string;
  html?: string;
  parsed: boolean;
  confirmation?: {
    question: string,
    override: string,
    title?: string,
  };
  closeDetailsLabel?: string;
};

const isAjaxFailData = <T>(arg: unknown): arg is AjaxFailData<T> =>
  !!arg && typeof arg === 'object' && (arg as Keyable).parsed !== undefined && (arg as Keyable).error !== undefined && (arg as Keyable).status !== undefined && (arg as Keyable).messages !== undefined;

type Callbacks<T = unknown> = {
  cleanup(data: AjaxFailData<T>): void;
  preProcess(data: AjaxFailData<T>): void;
};

/**
 * Generate some diagnostic output, mostly needed during application
 * development. This is intended to be called from the fail()
 * callback.
 *
 * @param xhr TBD.
 *
 * @param textStatus TBD.
 *
 * @param errorThrown TBD.
 *
 * @param [userCallbacks] An object with hook-functions:
 *
 * ```
 * {
 *   cleanup: function(data) { ... },
 *   preProcess: function(data) { ... }
 * ```
 *
 * The userCallbacks as well as the callback object itself is optional and
 * defaults to "do nothing". The argument is the data possibly
 * submitted by the server, as computed by ajaFailData().
 */
const ajaxHandleError = async <T = unknown>(
  xhr: JQuery.jqXHR,
  textStatus: string,
  errorThrown: string,
  userCallbacks?: Callbacks<T>['cleanup']|Partial<Callbacks<T> >,
) => {
  const defaultCallbacks: Callbacks<T> = {
    cleanup(_data) {},
    preProcess: (_data): _data is AjaxFailData & T => false,
  };
  if (userCallbacks instanceof Function) {
    userCallbacks = {
      cleanup: userCallbacks,
    };
  }
  const callbacks = { ...defaultCallbacks, ...userCallbacks };

  const failData = ajaxFailData(xhr, textStatus, errorThrown);
  callbacks.preProcess(failData);

  let decodedStatus: string;
  switch (textStatus) {
    case 'cancelled':
      decodedStatus = t(appName, 'Operation cancelled by user.');
      break;
    case 'abort':
      decodedStatus = t(appName, 'Aborted');
      break;
    case 'notmodified':
    case 'nocontent':
    case 'error':
    case 'timeout':
    case 'parsererror':
    case 'success': // this should not happen here
    default:
      decodedStatus = httpStatusText(xhr.status);
      break;
  }

  const caption = t(appName, 'Error');
  let info = '<span class="bold toastify http-status error">' + decodedStatus + '</span>';
  // console.info(xhr.status, info, errorThrown, textStatus);

  switch (xhr.status) {
    case HttpStatusCodes.OK:
    case HttpStatusCodes.BAD_REQUEST:
    case HttpStatusCodes.NOT_FOUND:
    case HttpStatusCodes.CONFLICT:
    case HttpStatusCodes.INTERNAL_SERVER_ERROR: {

      if (hasSubscriptions(LEGACY_AJAX_ERROR)) {
        let message = failData.messages?.join(' ') || '';
        let html = failData.html || '';
        if (!html && message.startsWith('<')) {
          html = message;
          message = '';
          if (failData.caption) {
            message = failData.caption;
          } else {
            const $caption = $(html).find('.caption');
            if ($caption.length > 0) {
              message = $caption.text();
            }
          }
        }
        const eventData = {
          xhr,
          message,
          html,
          closeDetailsLabel: failData.closeDetailsLabel,
        };
        await asyncEmit(LEGACY_AJAX_ERROR, eventData);
        console.info('RUNNING CLEANUP HOOKS', callbacks);
        callbacks.cleanup(failData);
      } else {
        // no Vue code available, must use legacy code ...
        if (failData.error && decodedStatus !== t(appName, failData.error)) {
          info += ': '
            + '<span class="bold error toastify name">'
            + t(appName, failData.error)
            + '</span>';
        }
        if (failData.messages) {
          const classes = appName + (failData.exception ? ' exception' : '');
          for (const msg of failData.messages) {
            info += '<div class="' + classes + ' error toastify">' + msg + '</div>';
          }
        }
        if (isNextcloudExceptionLogEntry(failData)) {
          // exception log entry
          let exceptionData: IException|undefined = failData.exception;
          const exception = exceptionData.Exception;
          const trace = stringifyTrace(exceptionData.Trace);
          info += '<div class="exception error name"><pre>' + exception + '</pre></div>'
            + '<div class="exception error trace"><pre>' + trace + '</pre></div>';
          while ((exceptionData = exceptionData.Previous)) {
            const message = exceptionData.Message;
            const exception = exceptionData.Exception;
            const trace = stringifyTrace(exceptionData.Trace);
            info += '<div class="exception error toastify"><span class="prefix">' + t(appName, 'Previous') + ': </span><span class="message">' + message + '</span></div>';
            info += '<div class="exception error name"><pre>' + exception + '</pre></div>'
              + '<div class="exception error trace"><pre>' + trace + '</pre></div>';
          }
        }
        if (failData.info) {
          info += '<div class="' + appName + ' error-page">' + failData.info + '</div>';
        }
        Dialogs.alert({
          content: info,
          title: caption,
          callback() { callbacks.cleanup(failData); },
          allowHtml: true,
          modal: true,
          dialogClasses: 'maximize-width',
        });
      }
      break;
    }
    case HttpStatusCodes.PRECONDITION_FAILED:
      // a simple page reload may help
      callbacks.cleanup = () => {
        window.location.reload();
      };
      if (failData.error && httpStatusText(xhr.status) !== t(appName, failData.error)) {
        info += ': '
          + '<span class="bold error toastify name">'
          + t(appName, failData.error)
          + '</span>';
      }
      if (failData.messages) {
        for (const msg of failData.messages) {
          info += '<div class="' + appName + ' error toastify">' + msg + '</div>';
        }
      }
      info += `<div class="error general">
This can happen when your device has been put to sleep (close the lid,
switch off your phone or tablet) for a longer time. In this case a
certain security token could not be refreshed regularly which may
produce the error your see. A simple page reload may help. This is
done automatically when cloud click "ok" or close this dialog window.
</div>`;
      Dialogs.alert({
        content: info,
        title: caption,
        callback() { callbacks.cleanup(failData); },
        allowHtml: true,
        modal: true,
        dialogClasses: 'maximize-width',
      });
      break;
    case HttpStatusCodes.UNAUTHORIZED: {
      // no point in continuing, direct the user to the login page
      callbacks.cleanup = () => {
        window.location.replace(cloudWebRoot);
      };

      let generalHint = t(appName, 'Something went wrong.');
      generalHint += '<br/>'
        + t(appName, 'If it should be the case that you are already '
          + 'logged in for a long time without interacting '
          + 'with the web-app, then the reason for this '
          + 'error is probably a simple timeout.');
      generalHint += '<br/>'
        + t(appName, 'I any case it may help to logoff and logon again, as a '
          + 'temporary work-around. You will be redirected to the '
          + 'log-in page when you close this window.');
      info += '<div class="error general">' + generalHint + '</div>';
      Dialogs.alert({
        content: info,
        title: caption,
        callback() { callbacks.cleanup(failData); },
        allowHtml: true,
        modal: true,
        dialogClasses: 'maximize-width',
      });
      break;
    }
  }

  // console.info(info);
  return failData;
};

/**
 * Generate some diagnostic output, mostly needed during
 * application development. This is intended to be called from the
 * done() callback after a successful AJAX call.
 *
 * This also acts as a type guard, it is the responsibility of the
 * consuming code to make sure that the "has property" test is
 * sufficient.
 *
 * @param data The data passed to the callback to $.post()
 *
 * @param required List of required fields in data.data.
 *
 * @param [errorCB] TBD.
 */
const ajaxValidateResponse = <T, R extends readonly (keyof T)[]>(
  data: string|Partial<T>,
  required: R,
  errorCB: (arg: string|Partial<T>) => void = () => {},
): data is T => {
  const dialogCallback = () => {
    errorCB(data);
  };
  // error handling
  if (typeof data === 'undefined' || !data) {
    Dialogs.alert(
      t(appName, 'Unrecoverable unknown internal error, '
        + 'no further information available, sorry.'),
      t(appName, 'Internal Error'), dialogCallback, true);
    return false;
  }
  let missing = '';
  for (const key of required) {
    if (typeof data === 'object' && data && typeof data[key] === 'undefined') {
      const keyString = typeof key === 'symbol' ? key.toString() : '' + (key as string|number);
      missing += t(
        appName, 'Field {RequiredField} not present in AJAX response.',
        { RequiredField: keyString }) + '<br>';
    }
  }
  if (missing.length > 0) {
    let info = '';
    if (hasProperty('messages', data) && Array.isArray(data.messages)) {
      info += data.messages.join(' ') + ' ';
    }
    info += t(appName, 'Missing data');
    // Add missing fields only if no exception or setup-error was
    // caught as in this case no regular data-fields have been
    // constructed
    info += '<div class="missing error">' + missing + '</div>';

    let caption = t(appName, 'Error');
    if (!isPlainObject(data)) {
      switch (typeof data) {
        case 'string':
          info += t(
            appName,
            'The submitted data "{stringValue}" seems to be a plain string, '
              + 'but we need an object where the data is provided through above listed properties.',
            { stringValue: data.substring(0, 32) + '...' });
          caption = t(appName, 'Error: plain string received');
          break;
        default:
          info += t(
            appName,
            'The submitted data is not a plain object, '
              + 'and does not provide the properties listed above.',
            { stringValue: ('' + data).substring(0, 32) + '...' });
          caption = t(appName, 'Error: not a plain object');
          break;
      }
    } else {
      // Display additional debug info if any
      Dialogs.debugPopup(data);
    }

    Dialogs.alert(info, caption, dialogCallback, true, true);
    return false;
  }
  return true;
};

/**
 * Fetch data from an error response.
 *
 * @param xhr jqXHR, see fail() method of jQuery ajax.
 *
 * @param textStatus from jQuery, see fail() method of jQuery ajax.
 *
 * @param errorThrown see fail() method of jQuery ajax.
 */
const ajaxFailData = <T = object>(
  xhr: JQuery.jqXHR|AjaxFailData<T>,
  textStatus: string,
  errorThrown: string,
) => {
  console.debug('AJAX FAIL DATA ARGS', {
    xhr,
    textStatus,
    errorThrown,
  });
  if (isAjaxFailData<T>(xhr)) {
    return xhr;
  }
  if (textStatus === 'error' && xhr.status !== undefined) {
    textStatus = httpStatusText(+xhr.status);
    // @ts-expect-error 2345
    xhr.statusText = textStatus;
  }
  const data: AjaxFailData = {
    error: errorThrown,
    status: textStatus,
    messages: [t(appName, 'Unknown JSON error response to AJAX call: {status} / {error}', { status: textStatus, error: errorThrown })],
    xhr,
    parsed: false,
  };
  const ct = xhr.getResponseHeader('content-type') || '';
  if (!xhr.responseJSON && xhr.responseText && ct.indexOf('json') > -1) {
    // this can happen during file-download as then jquery is forced
    // to 'binary' mode and responseJSON is not set.
    try {
      xhr.responseJSON = JSON.parse(xhr.responseText);
    } catch (e) {
      console.error('Unable to parse as JSON', { ct, text: xhr.responseText });
    }
  }
  if (xhr.responseJSON) {
    Object.assign(data, xhr.responseJSON);
    data.parsed = true;
  } else {
    if (ct.indexOf('html') > -1) {
      console.debug('html response', xhr, xhr.status, textStatus, errorThrown);
      data.messages = [
        t(
          appName, 'HTTP error response to AJAX call: {code} / {text}',
          { code: xhr.status, text: xhr.statusText },
        ),
      ];
      data.info = $(xhr.responseText).find('main').html();
      data.html = data.info;
      data.parsed = true;
    } else {
      console.log('unknown response');
    }
  }
  console.debug('AJAX FAIL DATA RETURN', { data });
  return data;
};

/**
 * Generate some diagnostic output, mostly needed during application
 * development.
 *
 * @param xhr jqXHR, see fail() method of jQuery ajax. For convenience
   any already constructed AjaxFailData object is left unchanged and
   may also be passed.
 *
 * @param textStatus from jQuery, see fail() method of jQuery ajax.
 *
 * @param errorThrown see fail() method of jQuery ajax.
 */
const ajaxFailMessage = (xhr: JQuery.jqXHR|AjaxFailData, textStatus: string, errorThrown: string) =>
  ajaxFailData(xhr, textStatus, errorThrown).messages.join(' ');

const ajaxFailMessages = (xhr: JQuery.jqXHR|AjaxFailData, textStatus: string, errorThrown: string) =>
  ajaxFailData(xhr, textStatus, errorThrown).messages;

$(Dialogs.attachDialogHandlers);

export {
  ajaxHandleError as handleError,
  ajaxValidateResponse as validateResponse,
  ajaxFailData as failData,
  ajaxFailMessage as failMessage,
  ajaxFailMessages as failMessages,
};

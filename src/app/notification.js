/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 - 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import globalState from './globalstate.js';
import {
  showMessage,
  TOAST_PERMANENT_TIMEOUT,
  TOAST_DEFAULT_TIMEOUT,
  TOAST_UNDO_TIMEOUT,
} from '@nextcloud/dialogs';

const Notification = globalState.Notification;
if (Notification === undefined) {
  globalState.Notification = {
    toasts: [],
  };
}

const toasts = globalState.Notification.toasts;

const hide = function(callback) {
  for (const toast of toasts) {
    toast.hideToast();
  }
  toasts.length = 0;
  if (callback) {
    callback.call();
  }
};

const escapeHTML = function(text) {
  return text.toString()
    .split('&').join('&amp;')
    .split('<').join('&lt;')
    .split('>').join('&gt;')
    .split('"').join('&quot;')
    .split('\'').join('&#039;');
};

const tweakTimeout = function(options) {
  if (options !== undefined && options.timeout !== undefined && options.timeout < 1000) {
    options.timeout *= 1000;
  }
};

const show = function(text, options) {
  console.info(text);
  tweakTimeout(options);
  options.timeout = options.timeout || TOAST_PERMANENT_TIMEOUT;
  const toast = showMessage(escapeHTML(text), options);
  toasts.push(toast);
  return toast;
};

const showHtml = function(text, options) {
  console.info(text);
  options.isHTML = true;
  tweakTimeout(options);
  options.timeout = options.timeout || TOAST_PERMANENT_TIMEOUT;
  const toast = showMessage(text, options);
  toasts.push(toast);
  return toast;
};

const showTemporary = function(text, options) {
  console.info(text);
  tweakTimeout(options);
  options.timeout = options.timeout || TOAST_DEFAULT_TIMEOUT;
  const toast = OC.Notification.showTemporary(text, options);
  toasts.push(toast);
  return toast;
};

/**
 * Display the given messages as "toasts".
 *
 * @param {Array} messages TBD.
 *
 * @param {object} options In particular the "timeout" property is
 * interesting.
 *
 * @returns {Array} The message array for chaining.
 */
function messages(messages, options) {
  const defaultOptions = {
    timeout: TOAST_UNDO_TIMEOUT,
  };
  options = { ...defaultOptions, ...options };
  if (messages !== undefined) {
    if (!Array.isArray(messages)) {
      messages = [messages];
    }
    for (const message of messages) {
      if ('' + message.trim() !== '') {
        showHtml(message, options);
      }
    }
  }
  return messages || [];
}

export {
  hide,
  show,
  showHtml,
  showTemporary,
  messages,
};

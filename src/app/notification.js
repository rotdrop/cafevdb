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

import { globalState } from './globals.js';

const Notification = globalState.Notification;
if (Notification === undefined) {
  globalState.Notification = {
    rows: [],
  };
}

const rows = globalState.Notification.rows;

const hide = function($row, callback) {
  if (_.isFunction($row)) {
    // first arg is the callback
    callback = $row;
    $row = undefined;
  }
  if (!$row) {
    for (const row of rows) {
      OC.Notification.hide(row);
    }
    rows.length = 0;
    if (callback) {
      callback.call();
    }
  } else {
    OC.Notification.hide($row, callback);
  }
};

const show = function(text, options) {
  const row = OC.Notification.show(text, options);
  rows.push(row);
  return row;
};

const showHtml = function(text, options) {
  const row = OC.Notification.showHtml(text, options);
  rows.push(row);
  return row;
};

const showTemporary = function(text, options) {
  const row = OC.Notification.showTemporary(text, options);
  rows.push(row);
  return row;
};

function messages(messages, options) {
  const defaultOptions = {
    timeout : 10,
  };
  options = $.extend({}, defaultOptions, options);
  if (options.timeout !== undefined) {
    options.timeout *= 1000;
  }
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

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

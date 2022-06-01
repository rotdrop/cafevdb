/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import { setAppUrl } from './settings-urls.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';

/**
 * AJAX call with a simple value
 *
 * @param {jQuery} element TBD.
 *
 * @param {string} eventType Something like 'blue', 'change' etc.
 *
 * @param {jQuery} msgElement TBD.
 *
 * @param {object} userCallbacks If a function: success callback. If
 *    an object: partial object with keys 'setup', 'success', 'fail',
 *    'cleanup', 'getValue', each pointing to a function performing
 *    the respective task. The callback 'getValue' computes the value
 *    to be communication via Ajax to the server as payload { value:
 *    VALUE }.
 */
const simpleSetValueHandler = function(element, eventType, msgElement, userCallbacks) {
  const defaultCallbacks = {
    post(name, value) {
      return $.post(setAppUrl(name), { value });
    },
    setup(/* empty */) {},
    success($self, data, value, msgElement) {},
    fail(xhr, textStatus, errorThrown) {
      Ajax.handleError(xhr, textStatus, errorThrown);
    },
    cleanup() {},
    getValue($self, msgElement) {
      return {
        name: $self.attr('name'),
        value: $self.is(':checkbox') ? $self.is(':checked') : $self.val(),
      };
    },
  };
  const callbacks = $.extend({}, defaultCallbacks);
  if (typeof userCallbacks === 'function') {
    callbacks.success = userCallbacks;
  } else if (typeof userCallbacks === 'object') {
    $.extend(callbacks, userCallbacks);
  }
  if (!msgElement) {
    msgElement = $();
  }
  element.on(eventType, function(event) {
    const self = this;
    const $self = $(self);
    msgElement.hide();
    $('.statusmessage').hide();
    let name;
    let value;
    if ((value = callbacks.getValue.call(self, $self, msgElement)) !== undefined) {
      name = value.name;
      value = value.value;
    }
    console.debug('value', value);
    if (value === undefined) {
      callbacks.cleanup.apply(self);
    } else {
      callbacks.setup.apply(self);
      callbacks
        .post(name, value)
        .fail(function(xhr, textStatus, errorThrown) {
          let message = Ajax.failMessage(xhr, textStatus, errorThrown);
          message = Notification.messages(message, { timeout: 15 });
          msgElement.html(message).show();
          callbacks.fail.call(self, xhr, textStatus, errorThrown);
          callbacks.cleanup.apply(self);
        })
        .done(function(data) {
          if (data.message) {
            data.message = Notification.messages(data.message, { timeout: 15 });
            msgElement.html(data.message.join('; ')).show();
          }
          callbacks.success.call(self, $self, data, value, msgElement);
          callbacks.cleanup.apply(self);
        });
    }
    return false;
  });
};

/**
 * AJAX call without submitting a value.
 *
 * @param {jQuery} element TBD.
 *
 * @param {string} eventType Something like 'blue', 'change' etc.
 *
 * @param {jQuery} msgElement TBD.
 *
 * @param {object} userCallbacks TBD.
 */
const simpleSetHandler = function(element, eventType, msgElement, userCallbacks) {
  const defaultCallbacks = {
    post(name) {
      return $.post(setAppUrl(name));
    },
    setup(/* empty */) {},
    success($self, data, msgElement) {},
    fail(xhr, textStatus, errorThrown) {
      Ajax.handleError(xhr, textStatus, errorThrown);
    },
    cleanup() {},
  };
  msgElement = msgElement || $();
  const callbacks = $.extend({}, defaultCallbacks);
  if (typeof userCallbacks === 'function') {
    callbacks.success = userCallbacks;
  } else if (typeof userCallbacks === 'object') {
    $.extend(callbacks, userCallbacks);
  }
  // console.debug('simpleSetHandler', element, eventType);
  element.on(eventType, function(event) {
    const $self = $(this);
    msgElement.hide();
    const name = $self.attr('name');
    callbacks.setup();
    callbacks.post(name)
      .fail(function(xhr, textStatus, errorThrown) {
        msgElement.html(Ajax.failMessage(xhr, textStatus, errorThrown)).show();
        callbacks.fail(xhr, textStatus, errorThrown);
        callbacks.cleanup();
      })
      .done(function(data) {
        if (data.message) {
          data.message = Notification.messages(data.message, { timeout: 15 });
          msgElement.html(data.message.join('; ')).show();
        }
        callbacks.success($self, data, msgElement);
        callbacks.cleanup();
      });
    return false;
  });
};

export {
  simpleSetHandler,
  simpleSetValueHandler,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

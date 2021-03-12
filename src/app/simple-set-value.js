/* Orchestra member, musicion and project management application.
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

import { $ } from './globals.js';
import setAppUrl from './settings-urls.js';
import * as Ajax from './ajax.js';
import * as Notification from './notification.js';

/**
 * AJAX call with a simple value
 *
 * @param {jQuery} element TBD.
 *
 * @param {String} eventType Something like 'blue', 'change' etc.
 *
 * @param {jQuery} msgElement TBD.
 *
 * @param {Object} userCallbacks If a function: success callback. If
 * an object: partial object with keys 'setup', 'success', 'fail',
 * 'cleanup', each pointing to a function performing the respective task.
 *
 * @param {Function} getValue If given a callback which computes the
 * value to be communication via Ajax to the server as payload { value: VALUE }.
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
    const $self = $(this);
    msgElement.hide();
    $('.statusmessage').hide();
    let name;
    let value;
    if ((value = callbacks.getValue($self, msgElement)) !== undefined) {
      name = value.name;
      value = value.value;
    }
    console.debug('value', value);
    if (value === undefined) {
      callbacks.cleanup();
    } else {
      callbacks.setup();
      callbacks
        .post(name, value)
        .fail(function(xhr, textStatus, errorThrown) {
          msgElement.html(Ajax.failMessage(xhr, textStatus, errorThrown)).show();
          callbacks.fail(xhr, textStatus, errorThrown);
          callbacks.cleanup();
        })
        .done(function(data) {
          Notification.hide(function() {
            if (data.message) {
              if (!Array.isArray(data.message)) {
                data.message = [data.message];
              }
              for (const msg of data.message) {
                Notification.show(msg, { timeout: 15 });
              }
              msgElement.html(data.message.join('; ')).show();
            }
            callbacks.success($self, data, value, msgElement);
            callbacks.cleanup();
          });
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
 * @param {String} eventType Something like 'blue', 'change' etc.
 *
 * @param {jQuery} msgElement TBD.
 */
const simpleSetHandler = function(element, eventType, msgElement) {
  // console.debug('simpleSetHandler', element, eventType);
  element.on(eventType, function(event) {
    const $self = $(this);
    msgElement.hide();
    const name = $self.attr('name');
    $.post(setAppUrl(name))
      .fail(function(xhr, status, errorThrown) {
        Ajax.handleError(xhr, status, errorThrown);
        msgElement.html(Ajax.failMessage(xhr, status, errorThrown)).show();
      })
      .done(function(data) {
        msgElement.html(data.message).show();
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

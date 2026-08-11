/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import * as Ajax from './ajax.ts';
import $ from './jquery.ts';
import * as Notification from './notification.ts';
import { setAppUrl } from './settings-urls.ts';

export type EventType = JQuery.BlurEvent['type']
 | JQuery.ClickEvent['type']
 | JQuery.ChangeEvent['type']
;

export type DefaultValueType<T extends HTMLElement = HTMLElement> = undefined|(T extends HTMLInputElement ? string : null|boolean|number|string|string[]|Record<string, unknown>);

export interface GetValueResult<T extends HTMLElement = HTMLElement, Value = DefaultValueType<T>> {
  name: string;
  value: Value;
}

export interface UserCallbacks<Element extends HTMLElement = HTMLElement, Value = DefaultValueType<Element>, Data = Record<string, Value>> {
  /**
   * Setup function called before placing the AJAX call. If false is
   * returned the AJAX call will not be placed and the cleanup
   * function is called.
   */
  setup: (this: Element) => false|void;
  success: (this: Element, $element: JQuery<Element>, data: Data, value: Value, $msg: JQuery) => void;
  fail: (this: Element, xhr: JQuery.jqXHR, textStatus: string, errorThrown: string) => void;
  cleanup: (this: Element) => void;
  getValue: (this: Element, $element: JQuery<Element>, $msg: JQuery) => undefined|GetValueResult<Element, Value>;
}

/**
 * AJAX call with a simple value
 *
 * @param $element TBD.
 *
 * @param eventType Something like 'blur', 'change' etc.
 *
 * @param [$msgElement] TBD.
 *
 * @param [userCallbacks] If a function: success callback. If
 *    an object: partial object with keys 'setup', 'success', 'fail',
 *    'cleanup', 'getValue', each pointing to a function performing
 *    the respective task.
 */
const simpleSetValueHandler = <Element extends HTMLElement = HTMLElement, Value = DefaultValueType<Element>, Data = Record<string, Value>>(
  $element: JQuery<Element>,
  eventType: EventType,
  $msgElement?: JQuery,
  userCallbacks?: Partial<UserCallbacks<Element, Value, Data>>|Pick<UserCallbacks<Element, Value, Data>, 'success'>,
) => {
  const defaultCallbacks: UserCallbacks<Element, Value, Data> = {
    setup() {},
    success(_$self, _data, _value, _$msgElement) {},
    fail(xhr, textStatus, errorThrown) {
      Ajax.handleError(xhr, textStatus, errorThrown);
    },
    cleanup() {},
    // @ts-expect-error 2322 TO COMPLICATED
    getValue($self, _$msgElement) {
      return {
        name: $self.attr('name')!,
        value: $self.is(':checkbox') ? $self.is(':checked') : $self.val() as string,
      };
    },
  };
  const callbacks = { ...defaultCallbacks };
  if (typeof userCallbacks === 'function') {
    callbacks.success = userCallbacks;
  } else if (typeof userCallbacks === 'object') {
    Object.assign(callbacks, userCallbacks);
  }
  if (!$msgElement) {
    $msgElement = $();
  }
  $element.on(eventType, function() {
    const $this = $(this);
    $msgElement.hide();
    $('.statusmessage').hide();
    const valueData = callbacks.getValue.call(this, $this, $msgElement);
    console.debug('value', { valueData });
    if (valueData?.value === undefined) {
      callbacks.cleanup.apply(this);
    } else {
      if (callbacks.setup.apply(this) === false) {
        callbacks.cleanup.apply(this);
      }
      $.post(setAppUrl(valueData.name), { value: valueData.value })
        .fail((xhr, textStatus, errorThrown) => {
          let messages = Ajax.failMessages(xhr, textStatus, errorThrown);
          messages = Notification.messages(messages, { timeout: 15 });
          $msgElement.html(messages.join('; ')).show();
          callbacks.fail.call($this[0], xhr, textStatus, errorThrown);
          callbacks.cleanup.apply($this[0]);
        })
        .done((data) => {
          if (data.messages) {
            data.messages = Notification.messages(data.messages, { timeout: 15 });
            $msgElement.html(data.messages.join('; ')).show();
          }
          callbacks.success.call($this[0], $this, data, valueData.value, $msgElement);
          callbacks.cleanup.apply($this[0]);
        });
    }
    return false;
  });
};

export interface SimpleSetCallbacks<Element extends HTMLElement = HTMLElement, Data = Record<string, unknown>> extends Omit<UserCallbacks<Element, unknown, Data>, 'getValue'|'success'> {
  success: (this: Element, $element: JQuery<Element>, data: Data, $msg: JQuery) => void;
}

/**
 * AJAX call without submitting a value.
 *
 * @param $element TBD.
 *
 * @param eventType Something like 'blue', 'change' etc.
 *
 * @param [$msgElement] TBD.
 *
 * @param [userCallbacks] TBD.
 */
const simpleSetHandler = <Element extends HTMLElement = HTMLElement>(
  $element: JQuery<Element>,
  eventType: EventType,
  $msgElement?: JQuery,
  userCallbacks?: Partial<SimpleSetCallbacks<Element>>|SimpleSetCallbacks<Element>['success'],
) => {
  const defaultCallbacks: SimpleSetCallbacks<Element> = {
    setup() {},
    success(_$self, _data, _$msgElement) {},
    fail(xhr, textStatus, errorThrown) {
      Ajax.handleError(xhr, textStatus, errorThrown);
    },
    cleanup() {},
  };

  $msgElement = $msgElement || $();
  const callbacks = { ...defaultCallbacks };
  if (typeof userCallbacks === 'function') {
    callbacks.success = userCallbacks;
  } else if (typeof userCallbacks === 'object') {
    Object.assign(callbacks, userCallbacks);
  }
  // console.debug('simpleSetHandler', element, eventType);
  $element.on(eventType, function() {
    const $self = $(this);
    $msgElement.hide();
    if (callbacks.setup.call(this) === false) {
      callbacks.cleanup.call(this);
    }
    const name = $self.attr('name')!;
    console.info('NAME', { name, $self });
    $.post(setAppUrl(name))
      .fail(function(xhr, textStatus, errorThrown) {
        $msgElement.html(Ajax.failMessage(xhr, textStatus, errorThrown)).show();
        callbacks.fail.call($self[0], xhr, textStatus, errorThrown);
        callbacks.cleanup.call($self[0]);
      })
      .done(function(data) {
        if (data.messages.length > 0) {
          Notification.messages(data.messages, { timeout: 15 });
          $msgElement.html(data.messages.join(' ')).show();
        }
        callbacks.success.call($self[0], $self, data, $msgElement);
        callbacks.cleanup.call($self[0]);
      });
    return false;
  });
};

export {
  simpleSetHandler,
  simpleSetValueHandler,
};

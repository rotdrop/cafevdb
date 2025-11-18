/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from '../config.ts';
import $ from './jquery.ts';
import { joinLiterals } from '../toolkit/util/string-literals.ts';

const delay = 50000; // ms
const nameSpacedEvent = <T extends string>(eventName: T) => joinLiterals('')(eventName, '.', appName, 'TextAreaResize');
const events = joinLiterals(' ')(nameSpacedEvent('mouseup'), nameSpacedEvent('mousemove'));

console.debug('RESIZE EVENTS', { events });

// TriggeredEvent<HTMLElement, undefined, any, any>'

const handler = function(this: HTMLTextAreaElement) {
  // eslint-disable-next-line @typescript-eslint/no-this-alias
  const textarea = this;
  const $textarea = $(textarea);
  const data = $textarea.data();
  if (data.oldWidth === undefined) {
    $textarea.data('oldWidth', textarea.style.width);
    $textarea.data('oldHeight', textarea.style.height);
  }
  if (textarea.style.width !== data.oldWidth || textarea.style.height !== data.oldHeight) {
    if (data.resizeTimeout) {
      clearTimeout(data.resizeTimeout);
    }
    $textarea.data(
      'resizeTimeout',
      setTimeout(function() {
        console.debug('TEXTAREA TRIGGER RESIZE', { $textarea, data });
        $textarea.trigger('resize');
      }, delay),
    );
    $textarea.data('oldWidth', textarea.style.width);
    $textarea.data('oldHeight', textarea.style.height);
  }
};

/**
 * Unfortunately, the textare element does not fire a resize
 * event. This function emulates one.
 *
 * @param $container jQuery or container for event
 * delegation.
 *
 * @param textareaSelector selector or jQuery
 */
const textareaResize = ($container: JQuery<HTMLElement>, textareaSelector?: string) => {
  if (typeof textareaSelector === 'undefined') {
    // Variant with one argument, argument must be textarea.
    $container.off(events).on(events, handler);
  } else {
    $container.off(events, textareaSelector).on(events, textareaSelector, handler);
  }
};

export default textareaResize;

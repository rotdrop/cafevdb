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
import $ from './jquery.js';

const delay = 50; // ms
const events = ['mouseup', 'mousemove'].map(eventName => eventName + '.' + appName + 'TextAreaResize').join(' ');

console.debug('RESIZE EVENTS', { events });

const handler = function(event) {
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
 * @param {object} container selector or jQuery of container for event
 * delegation.
 *
 * @param {object} textareaSelector selector or jQuery
 */
const textareaResize = (container, textareaSelector) => {
  if (typeof textareaSelector === 'undefined') {
    // Variant with one argument, argument must be textarea.
    $(container).off(events).on(events, handler);
  } else {
    $(container).off(events, textareaSelector).on(events, textareaSelector, handler);
  }
};

export default textareaResize;

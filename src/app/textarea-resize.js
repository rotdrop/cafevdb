/**
 * Orchestra member, musicion and project management application.
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

/**
 * Unfortunately, the textare element does not fire a resize
 * event. This function emulates one.
 *
 * @param {object} container selector or jQuery of container for event
 * delegation.
 *
 * @param {object} textarea selector or jQuery
 *
 * @param {number} delay Optional, defaults to 50. If true, fire the event
 * immediately, if set, then this is a delay in ms.
 */
function textareaResize(container, textarea, delay) {
  if (typeof textarea === 'undefined' && typeof delay === 'undefined') {
    // Variant with one argument, argument must be textarea.
    textarea = container;
    delay = textarea;
    container = null;
  } else if (delay === 'undefined' && $.isNumeric(textarea)) {
    // Variant with two argument, argument must be textarea.
    textarea = container;
    delay = textarea;
    container = null;
  }

  // otherwise first two arguments are container and textarea.
  if (typeof delay === 'undefined') {
    delay = 50; // ms
  }

  const handler = function(event) {
    const textarea = this;
    const $this = $(textarea);
    if (textarea.oldwidth === null) {
      textarea.oldwidth = textarea.style.width;
    }
    if (textarea.oldheight === null) {
      textarea.oldheight = textarea.style.height;
    }
    if (textarea.style.width !== textarea.oldwidth || textarea.style.height !== textarea.oldheight) {
      if (delay > 0) {
        if (textarea.resize_timeout) {
          clearTimeout(textarea.resize_timeout);
        }
        textarea.resizeTimeout = setTimeout(function() {
          $this.resize();
        }, delay);
      } else {
        $this.resize();
      }
      textarea.oldwidth = textarea.style.width;
      textarea.oldheight = textarea.style.height;
    }
    return true;
  };
  const events = 'mouseup mousemove';
  if (container) {
    $(container).off(events, textarea).on(events, textarea, handler);
  } else {
    $(textarea).off(events).on(events, handler);
  }
}

export default textareaResize;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

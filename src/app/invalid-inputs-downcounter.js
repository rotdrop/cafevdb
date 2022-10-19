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

import jQuery from './jquery.js';
import { appName } from './app-info.js';

const $ = jQuery;

let $invalidInputDownCounter;
let invalidInputTimer;

/**
 * Overlay the global reload button with a down-count.
 *
 * @param {jQuery} $input An input element to overlay with a
 * counter. If not given, the global reload-button will be overlayed.
 *
 * @param {number} timeout Text vanishes after this many milleseonds.
 *
 * @param {Function} atZero Call this function when the down-count
 * has finished.
 */
function invalidInputDowncount($input, timeout, atZero) {
  let counter = timeout / 1000;
  if (counter > 0) {
    const $reloadButton = $('#reloadbutton');
    if (invalidInputTimer) {
      clearTimeout(invalidInputTimer);
    }
    if (!$invalidInputDownCounter) {
      const title = t(appName, 'Invalid inputs counter.');
      $invalidInputDownCounter = $(`<span class="invalid-inputs-counter tooltip-auto" title="${title}">${counter} s</span>`);
      $invalidInputDownCounter.css({
        position: 'absolute',
        margin: 'auto',
        'font-weight': 'bold',
        color: 'blue',
        'font-size': '150%',
        'z-index': 1,
      });
    } else {
      $invalidInputDownCounter.html(`${counter} s`);
    }
    let $elementCounter;
    if ($input instanceof jQuery) {
      const title = t(appName, 'Invalid inputs counter.');
      $elementCounter = $input.prev();
      if (!$elementCounter.is('.invalid-inputs-counter')) {
        $elementCounter = $(`<span class="invalid-inputs-counter tooltip-auto" title="${title}">${counter} s</span>`);
        $elementCounter.css({
          position: 'absolute',
          display: 'inline-block',
          margin: 'auto',
          'font-weight': 'bold',
          color: 'blue',
          'font-size': '150%',
          'z-index': 1,
          width: 0,
          height: 0,
          overflow: 'visible',
          whitspace: 'nowrap',
        });
        const float = $input.css('float');
        if (float) {
          $elementCounter.css('float', float);
          $elementCounter.css('position', 'relative');
        }
        $input.before($elementCounter);
      } else {
        $elementCounter.html(`${counter} s`);
      }
    }
    if ($reloadButton.find('.invalid-inputs-counter').length === 0) {
      $reloadButton.append($invalidInputDownCounter);
    }
    const displayCounter = () => {
      const text = `${counter} s`;
      $invalidInputDownCounter.html(text);
      if ($elementCounter) {
        $elementCounter.html(text);
      }
      if (--counter >= 0) {
        invalidInputTimer = setTimeout(displayCounter, 1000);
      } else {
        $invalidInputDownCounter.remove();
        if ($elementCounter) {
          $elementCounter.remove();
        }
        invalidInputTimer = null;
        if (typeof atZero === 'function') {
          atZero();
        }
      }
    };
    invalidInputTimer = setTimeout(displayCounter, 1000);
  }
}

export default invalidInputDowncount;

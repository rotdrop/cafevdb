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
import { $, appName } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Dialogs from './dialogs.js';
import { token as pmeToken } from './pme-selectors.js';

/**
 * Brief UI check for invalid input elements.
 *
 * @param {jQuery} container Either a container element containing a
 * form or a form. The function checks all contained forms if
 * cointainer is not itself a form element.
 *
 * If the container does not contain a form and is not itself a form,
 * then all contained elements are search for ':invalid'.
 *
 * @param {function} cleanup Cleanup function called after displaying a
 * warning about invalid elements. It is not executed if no invalid
 * form elements can be found.
 *
 * @param {function} labelCallback A callback receiving the invalid
 * input element as only argument. The return value may be logical
 * false or a non-zero string which is used as the display label for
 * the error message.
 *
 * @returns {boolean} true iff no error is found.
 */
function checkInvalidInputs(container, cleanup, labelCallback) {

  if (typeof cleanup !== 'function') {
    cleanup = function() {};
  }

  if (typeof labelCallback !== 'function') {
    labelCallback = function($input) {
      return $input.closest('tr').find('td.' + pmeToken('key')).html();
    };
  }

  const containedForms = container.find('form');
  const searchBase = containedForms.length === 0 ? container : containedForms;

  // exclude fieldsets, as the contained items are also included.
  const invalidInputs = searchBase.find(':invalid').not('fieldset');

  if (invalidInputs.length !== 0) {
    const highlightInvalid = function(afterDialog) {
      for (const input of invalidInputs) {
        const $input = $(input);
        let $effectInput = $input;
        if (!$input.is(':visible') && $input.is('select')) {
          if ($input[0].selectize) {
            $effectInput = $input.next().find('.selectize-input');
          } else if (CAFEVDB.chosenActive($input)) {
            $effectInput = $input.next();
          }
        } else {
          $effectInput = $input;
        }

        if ($effectInput.is(':visible')) {
          $effectInput.cafevTooltip('enable');
          if (afterDialog) {
            $effectInput.cafevTooltip('show');
          }
          $effectInput.effect(
            'highlight',
            {},
            10000,
            function() {
              if (afterDialog) {
                if (!CAFEVDB.toolTipsEnabled()) {
                  $effectInput.cafevTooltip('disable');
                }
                cleanup();
              }
            });
        }
      }
    };
    const invalidInfo = [];
    for (const input of invalidInputs) {
      const $input = $(input);

      // use either a special label callback or the relevant label or the placeholder.
      let label = labelCallback($input);
      if (!label) {
        const id = $input.attr('id');
        label = container.find('label[for="' + id + '"]').html();
      }
      if (!label) {
        label = $input.attr('placeholder');
      }
      if (!label) {
        label = $input.closest('label').html();
      }
      if (!label) {
        label = $input.attr('name');
      }
      if (!label) {
        label = t(appName, 'Unknown input element');
      }
      const value = $input.val();
      invalidInfo.push('<li class="' + appName + ' invalid-form-input">'
                       + label
                       + ', '
                       + (value
                         ? t(appName, 'invalid data "{value}"', { value })
                         : t(appName, 'no data'))
                       + '</li>');
    }
    highlightInvalid();
    Dialogs.alert(
      '<div class="' + appName + ' invalid-form-input">'
        + t(appName, 'The following required fields are empty or contain otherwise invalid data:')
        + '<ul class="' + appName + ' invalid-form-input">'
        + invalidInfo.join('\n')
        + '</ul>'
        + t(appName, 'Please add the missing data!')
        + '</div>',
      t(appName, 'Missing Input Data'),
      () => highlightInvalid(true),
      true,
      true);
    return false;
  }
  return true;
}

export default checkInvalidInputs;

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

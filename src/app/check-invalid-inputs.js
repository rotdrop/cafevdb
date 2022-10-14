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
import { appName } from './app-info.js';
import * as CAFEVDB from './cafevdb.js';
import * as Dialogs from './dialogs.js';
import { widget as selectWidget } from './select-utils.js';
import { token as pmeToken } from './pme-selectors.js';
import mergician from 'mergician';

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
 * @param {Function} options Options with components 'cleanup',
 * 'labelCallback', 'afterDialog' and 'timeout'.
 *
 * @param {Function} labelCallback A callback receiving the invalid
 * input element as only argument. The return value may be logical
 * false or a non-zero string which is used as the display label for
 * the error message.
 *
 * @param {Function} afterDialog A callback which is called after the
 * user has closed the error callback. It receives as argument the
 * jQuery list of invalid inputs.
 *
 * @returns {boolean} true iff no error is found.
 */
function checkInvalidInputs(container, options, labelCallback, afterDialog) {
  const defaultOptions = {
    cleanup() {},
    labelCallback($input) {
      return $input.closest('tr').find('td.' + pmeToken('key')).html();
    },
    afterDialog($invalidInputs) {},
    timeout: 5000,
  };

  if (typeof options === 'function') {
    options = {
      cleanup: options,
    };
  }
  if (typeof labelCallback === 'function') {
    options.labelCallback = labelCallback;
  }

  if (typeof afterDialog === 'function') {
    options.afterDialog = afterDialog;
  }

  options = mergician({}, defaultOptions, options || {});

  const cleanup = options.cleanup;
  labelCallback = options.labelCallback;

  const containedForms = container.find('form');
  const searchBase = containedForms.length === 0 ? container : containedForms;

  // exclude fieldsets, as the contained items are also included.
  const invalidInputs = searchBase.find(':invalid').filter(function() {
    const $this = $(this);
    if ($this.is('fieldset')) {
      return false;
    }
    if ($this.hasClass('emulated-placeholder') && !$this.hasClass('value-required')) {
      return false;
    }
    return true;
  });

  if (invalidInputs.length !== 0) {
    const highlightInvalid = function(afterDialog) {
      for (const input of invalidInputs) {
        const $input = $(input);
        let $effectInput = $input;
        let $tooltipInput = $input;
        if (!$input.is(':visible') && $input.is('select')) {
          $effectInput = selectWidget($input);
          $tooltipInput = selectWidget($input);
        } else {
          // selectize moves the "required" property to its own input
          const $selectize = $input.closest('.selectize-control');
          if ($selectize.length > 0) {
            $effectInput = $input.parent();
            $tooltipInput = $selectize;
          }
        }

        if ($effectInput.is(':visible')) {
          $tooltipInput.cafevTooltip('enable');
          $effectInput.effect(
            'highlight',
            {},
            options.timeout,
            function() {
              if (afterDialog) {
                if (!CAFEVDB.toolTipsEnabled()) {
                  $tooltipInput.cafevTooltip('disable');
                }
                cleanup();
              }
            });
          if (afterDialog) {
            $tooltipInput.cafevTooltip('show');
          }
        }
      }
      if (afterDialog) {
        options.afterDialog(invalidInputs);
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
                         : t(appName, 'no or invalid data'))
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

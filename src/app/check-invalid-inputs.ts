/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $, { isJQuerySelect } from './jquery.ts';
import { appName } from '../config.ts';
import * as CAFEVDB from './cafevdb.ts';
import * as Dialogs from './dialogs.ts';
import { widget as selectWidget } from './select-utils.ts';
import { token as pmeToken } from './pme-selectors.ts';
import { translate as t } from '@nextcloud/l10n';

const defaultOptions = {
  cleanup() {},
  labelCallback($input: JQuery) {
    return $input.closest('tr').find('td.' + pmeToken('key')).html();
  },
  beforeDialog(_$invalidInputs: JQuery) {},
  afterDialog(_$invalidInputs: JQuery) {},
  timeout: 5000,
};

type Options = typeof defaultOptions;

/**
 * Brief UI check for invalid input elements.
 *
 * @param $container Either a container element containing a
 * form or a form. The function checks all contained forms if
 * cointainer is not itself a form element.
 *
 * If the container does not contain a form and is not itself a form,
 * then all contained elements are search for ':invalid'.
 *
 * @param options Options with components 'cleanup',
 * 'labelCallback', 'afterDialog' and 'timeout'.
 *
 * @returns true iff no error is found.
 */
function checkInvalidInputs($container: JQuery, options?: Partial<Options>) {

  options = { ...defaultOptions, ...(options || {}) };
  console.info('OPTIONS', options);

  const cleanup = options.cleanup;
  const labelCallback = options.labelCallback;

  const containedForms = $container.find('form');
  const searchBase = containedForms.length === 0 ? $container : containedForms;

  // exclude fieldsets, as the contained items are also included.
  const invalidInputs = searchBase.find(':invalid, input.validate-non-zero').filter(function() {
    const $this = $(this);
    if ($this.is('fieldset')) {
      return false;
    }
    if ($this.hasClass('emulated-placeholder') && !$this.hasClass('value-required')) {
      return false;
    }
    if (!$this.is(':invalid') && $this.hasClass('validate-non-zero') && +($this.val() ?? 0) !== 0 && $this.val() !== '') {
      return false;
    }
    return true;
  });

  if (invalidInputs.length === 0) {
    return true;
  }

  $.fn.cafevTooltip.remove();
  const highlightInvalid = function(afterDialog?: boolean) {
    for (const input of invalidInputs) {
      const $input = $(input);
      let $effectInput = $input;
      let $tooltipInput = $input;
      if (!$input.is(':visible') && isJQuerySelect($input)) {
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
  const invalidInfo: string[] = [];
  for (const input of invalidInputs) {
    const $input = $(input);

    // use either a special label callback or the relevant label or the placeholder.
    let label = labelCallback($input);
    if (!label) {
      const id = $input.attr('id');
      label = $container.find('label[for="' + id + '"]').html();
    }
    if (!label) {
      label = '' + $input.attr('placeholder');
    }
    if (!label) {
      label = $input.closest('label').html();
    }
    if (!label) {
      label = '' + $input.attr('name');
    }
    if (!label) {
      label = t(appName, 'Unknown input element');
    }
    const value = '' + $input.val();
    invalidInfo.push('<li class="' + appName + ' invalid-form-input">'
      + label
      + ', '
      + (value
        ? t(appName, 'invalid data "{value}"', { value })
        : t(appName, 'no or invalid data'))
      + '</li>');
  }
  options.beforeDialog(invalidInputs);
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

export default checkInvalidInputs;

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

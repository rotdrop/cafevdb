/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
require('select-utils.scss');

// find an option by its value
const findOptionByValue = function($select, value) {
  return $select.find('option[value="' + value + '"]');
};

/**
 * Determine if the given element is managed by jQuery chosen.
 *
 * @param{jQuery} $select TBD.
 *
 * @returns{bool}
 */
const chosenActive = function($select) {
  return $select.data('chosen') !== undefined;
};

const makePlaceholder = function($select) {
  if (!chosenActive($select) && !selectizeActive($select)) {
    // restore the data-placeholder as first option if chosen
    // is not active
    $select.each(function(index) {
      const $self = $(this);
      const placeHolder = $self.data('placeholder');
      if (!placeHolder) {
        return;
      }
      if ($self.hasClass('emulated-placeholder')) {
        return;
      }
      if ($self.prop('required')) {
        $self.addClass('value-required');
      }
      $self.prop('required', true)
        .addClass('emulated-placeholder');
      $self.find('option:first')
        .attr('value', '')
        .prop('hidden', true)
        .prop('disabled', true)
      // .prop('selected', true)
        .html(placeHolder);
    });
  }
};

/**
 * Determine if the given element is managed by selectize.
 *
 * @param{jQuery} $select TBD.
 *
 * @returns{bool}
 */
const selectizeActive = function($select) {
  return !!($select.length > 0 && ($select[0].selectize !== undefined));
};

const deselectAll = function($select) {
  if (selectizeActive($select)) {
    const selectize = $select[0].selectize;
    selectize.clear(true);
    selectize.refreshItems(true);
  } else {
    // deselect option items
    $select.find('option').prop('selected', false);
    if (chosenActive($select)) {
      $select.trigger('chosen:updated');
    }
  }
};

/**
 * Set or return the selected values and update the potentially
 * underlying "selectize" or "chosen" widget. Works for "multiple" as
 * well as single selects.
 *
 * @param{jQuery} $select collection with a single select.
 *
 * @param{(string|string[])} [values] If given then set the given
 * values into the select. If the select is not multiple and value is
 * an array then use values[0] as selected value.
 *
 * @param{bool} [trigger=false] If trigger === true then trigger a
 * change-event on the select after installing the new values.
 *
 * @returns{(Array|string|null)} Always return an array for multiple
 * selects and a string or null for single selects. When setting new
 * values return the previously set values.
 */
const selectedValues = function($select, values, trigger) {
  if (values === undefined) {
    let result;
    if (selectizeActive($select)) {
      result = $select[0].selectize.items;
    } else {
      result = $select.val() || [];
      if (!Array.isArray(result)) {
        result = [result];
      }
    }
    if ($select.prop('multiple')) {
      return result;
    } else {
      return result.length > 0 ? result[0] : null;
    }
  } else {
    const oldValues = selectedValues($select);
    if (!Array.isArray(values)) {
      values = [values];
    }
    if (selectizeActive($select)) {
      const selectize = $select[0].selectize;
      selectize.clear(true);
      selectize.addItems(values, true);
      selectize.refreshItems(true);
    } else {
      $select.val(values);
      if (chosenActive($select)) {
        $select.trigger('chosen:updated');
      }
    }
    if (trigger === true) {
      $select.trigger('change');
    }
    return oldValues;
  }
};

/**
 * Update the underlying select widget to reflect changes in the
 * original select element. This currently support jQuery chosen and
 * selectize.
 *
 * @param{jQuery} $select TBD.
 */
const refreshSelectWidget = function($select) {
  if (chosenActive($select)) {
    $select.trigger('chosen:updated');
  } else if (selectizeActive($select)) {
    let selectize = $select[0].selectize;
    const setupOptions = selectize.settings_user;
    selectize.destroy();
    $select.selectize(setupOptions);
    selectize = $select[0].selectize;
    if ($select.is('disabled')) {
      selectize.disable();
    } else {
      selectize.enable();
    }
  }
};

const locked = function($select, state) {
  if (state === undefined) {
    if (selectizeActive($select)) {
      const selectize = $select[0].selectize;
      return selectize.isLocked || selectize.isDisabled;
    } else {
      return $select.prop('disabled') || $select.prop('readonly');
    }
  } else {
    if (selectizeActive($select)) {
      const selectize = $select[0].selectize;
      if (state) {
        selectize.lock();
      } else {
        selectize.unlock();
      }
    } else {
      $select.prop('disabled', !!state);
      if (chosenActive($select)) {
        $select.trigger('chosen:updated');
      }
    }
  }
};

export {
  findOptionByValue as optionByValue,
  deselectAll,
  chosenActive,
  selectizeActive,
  refreshSelectWidget as refreshWidget,
  selectedValues as selected,
  locked,
  makePlaceholder,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

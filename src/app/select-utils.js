/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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
require('select-utils.scss');

const $ = jQuery;

/**
 * Fetch the selectize instance attached to the given $select if any.
 *
 * @param {jQuery} $select TBD.
 *
 * @returns {(object|undefined)}
 */
const getSelectize = function($select) {
  return ($select instanceof $) && $select.length > 0 ? $select[0].selectize : undefined;
};

/**
 * Determine if the given element is managed by selectize.
 *
 * @param {jQuery} $select TBD.
 *
 * @returns {boolean}
 */
const selectizeActive = function($select) {
  return !!getSelectize($select);
};

/**
 * Determine if the given element is managed by jQuery chosen.
 *
 * @param {jQuery} $select TBD.
 *
 * @returns {boolean}
 */
const chosenActive = function($select) {
  return $select.data('chosen') !== undefined;
};

/**
 * Check whether this $select is controlled by either chosen or selectize.
 *
 * @param {jQuery} $select Select element.
 *
 * @returns {boolean}
 */
const isVanilla = function($select) {
  return !selectizeActive($select) && !chosenActive($select);
};

/**
 * Fetch the control instance attached to the given $select if any.
 *
 * @param {jQuery} $select TBD.
 *
 * @returns {(object|undefined)}
 */
const getControlObject = function($select) {
  if (chosenActive($select)) {
    return $select.data('chosen');
  } else {
    return getSelectize($select);
  }
};

/**
 * Fetch the children of the underlying select regardless of the widget
 * used.
 *
 * @param {jQuery} $select TBD.
 *
 * @returns {jQuery} The set of children
 */
const getChildren = function($select) {
  const selectize = getSelectize($select);
  const $children = selectize ? selectize.revertSettings.$children : $select.children();
  if (selectize) {
    const values = selectize.items;
    $children.each(function() {
      const $child = $(this);
      if ($child.is('option')) {
        $child.prop('selected', values.indexOf($child.attr('value')));
      }
    });
  }
  return $children;
};

/**
 * Fetch the options of the underlying select regardless of the widget
 * used.
 *
 * @param {jQuery} $select TBD.
 *
 * @returns {jQuery} The set of options.
 */
const getOptions = function($select) {
  const $children = getChildren($select);
  return $children.filter('option').add($children.find('option'));
};

/**
 * Fetch the possible values as flat array.
 *
 * @param {jQuery} $select TBD.
 *
 * @returns {Array} The set of options.
 */
const getOptionValues = function($select) {
  const selectize = getSelectize($select);
  if (selectize) {
    return Object.keys(selectize.options);
  } else {
    const values = [];
    getOptions($select).each(function() {
      const $option = $(this);
      if (!$option.prop('disabled')) {
        values.push($option.val());
      }
    });
    return values;
  }
};

/**
 * Find an option by its value
 *
 * @param {jQuery} $select TBD.
 *
 * @param {string} value The value to search for.
 *
 * @returns {jQuery} The found option as jQuery object, if any.
 */
const findOptionByValue = function($select, value) {
  return getOptions($select).filter('option[value="' + value + '"]');
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

const deselectAll = function($select) {
  if (selectizeActive($select)) {
    const selectize = $select[0].selectize;
    selectize.clear(true);
    selectize.refreshItems(true);
  } else {
    // deselect option items
    $select.find('option:selected').prop('selected', false);
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
 * @param {jQuery} $select collection with a single select.
 *
 * @param {(string|string[])} [values] If given then set the given
 * values into the select. If the select is not multiple and value is
 * an array then use values[0] as selected value.
 *
 * @param {boolean} [trigger=false] If trigger === true then trigger a
 * change-event on the select after installing the new values.
 *
 * @returns {(Array|string|null)} Always return an array for multiple
 * selects and a string or null for single selects. When setting new
 * values return the previously set values.
 */
const selectedValues = function($select, values, trigger) {
  if (values === undefined) {
    let result;
    if (selectizeActive($select)) {
      result = [...$select[0].selectize.items];
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
    if (values === false) {
      values = [];
    } else if (!Array.isArray(values)) {
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
 * Fetch the selected option elements as jQuery collection. In the
 * presence of selectize the original options are returned if they
 * match the selected selectize values.
 *
 * @param {jQuery} $select collection with a single select.
 *
 * @returns {jQuery} The selected options as jQuery collection.
 */
const selectedOptions = function($select) {
  return getOptions($select).filter('option:selected');
};

/**
 * Update the underlying select widget to reflect changes in the
 * original select element. This currently supports jQuery chosen and
 * selectize.
 *
 * In the case of selectize the children of the original select were
 * removed by selectize. After calling this function the option list
 * of the selectize widget will be replace by the children of $select
 * on entry to this function if the $select.children() is non empty.
 *
 * @param {jQuery} $select The select element.
 */
const refreshSelectWidget = function($select) {
  const isDisabled = $select.prop('disabled');
  const isReadonly = $select.prop('readonly');
  if (chosenActive($select)) {
    if (isReadonly && !isDisabled) {
      $select.prop('disabled', true);
    }
    $select.trigger('chosen:updated');
    if (!isDisabled) {
      $select.prop('disabled', false);
    }
  } else if (selectizeActive($select)) {
    let selectize = $select[0].selectize;
    const setupOptions = selectize.settings_user;
    selectize.destroy();
    if (isReadonly) {
      $select.prop('readonly', false);
    }
    if (isDisabled) {
      $select.prop('disabled', false);
    }
    $select.selectize(setupOptions);
    selectize = $select[0].selectize;
    if (isDisabled || isReadonly) {
      selectize.disable();
    } else {
      selectize.enable();
    }
    $select.prop('disabled', isDisabled);
  }
};

/**
 * Return the jQuery element which actually is shown on the screen.
 *
 * @param {jQuery} $select Select element.
 *
 * @returns {jQuery}
 */
const getWidget = function($select) {
  if (chosenActive($select)) {
    return $select.next();
  } else if (selectizeActive($select)) {
    return getSelectize($select).$wrapper;
  } else {
    return $select;
  }
};

const getSelectFromWidget = function($widget) {
  if ($widget.hasClass('selectize-control') || $widget.hasClass('chosen-container')) {
    const $element = $widget.prev();
    return $element.is('select') ? $element : $();
  }
  return $();
};

/**
 * Flush the readonly and disabled properties from $select to the
 * underlying widget, if any.
 *
 * @param {jQuery} $select The select element.
 */
const refreshWidgetProperties = function($select) {
  const isDisabled = $select.prop('disabled');
  const isReadonly = $select.prop('readonly');

  if (chosenActive($select)) {
    if (isReadonly) {
      $select.prop('disabled', true);
    }
    $select.trigger('chosen:updated');
    if (!isDisabled) {
      $select.prop('disabled', false);
    }
  } else if (selectizeActive($select)) {
    const selectize = getSelectize($select);
    if (isDisabled || isReadonly) {
      selectize.disable();
    } else {
      selectize.enable();
    }
    if (!isDisabled) {
      $select.prop('disabled', false);
    }
  }
};

/**
 * Replace the options of the given select by the given options.
 *
 * @param {jQuery} $select TBD.
 *
 * @param {(jQuery|string)} options TBD.
 */
const replaceSelectOptions = function($select, options) {
  const isDisabled = $select.prop('disabled');
  const isReadonly = $select.prop('readonly');
  let selectize, setupOptions;
  if (selectizeActive($select)) {
    selectize = $select[0].selectize;
    setupOptions = selectize.settings_user;
    selectize.destroy();
  }
  if (options instanceof $) {
    $select.html('').append(options);
  } else {
    $select.html(options);
  }
  if (isReadonly && !isDisabled) {
    $select.prop('disabled', true);
  }
  if (chosenActive($select)) {
    $select.trigger('chosen:updated');
  } else if (selectize) {
    $select.selectize(setupOptions);
  }
  if (isReadonly && !isDisabled) {
    $select.prop('disabled', false);
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
  refreshWidgetProperties,
  replaceSelectOptions as replaceOptions,
  selectedValues as selected,
  selectedOptions,
  getOptions as options,
  getOptionValues as optionValues,
  getChildren as children,
  getWidget as widget,
  getSelectFromWidget as selectFromWidget,
  getControlObject,
  isVanilla,
  locked,
  makePlaceholder,
};

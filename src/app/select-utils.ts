/**
 * @file
 *
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *
 * @module
 */

import { appName } from '../config.ts';
import { getMountedChosen } from '../services/mountable-chosen.ts';
import { getMountedSelectize } from '../services/mountable-select.ts';
import jQuery from './jquery.ts';

import 'select-utils.scss';

const $ = jQuery;

type SelectValue = string;

type JQuerySelect = JQuery<HTMLSelectElement>;

/**
 * Determine if the given element is managed by jQuery chosen.
 *
 * @param $select TBD.
 */
const chosenActive = function($select: JQuerySelect) {
  return $select.data('chosen') !== undefined;
};

const getVueSelect = ($select: JQuerySelect) => getMountedSelectize($select[0]) ?? getMountedChosen($select[0]);

const vueSelectActive = ($select: JQuerySelect) => !!getVueSelect($select);

/**
 * Check whether this select has no enhanced widget.
 *
 * @param $select Select element.
 */
const isVanilla = function($select: JQuerySelect) {
  return !getVueSelect($select) && !chosenActive($select);
};

/**
 * Fetch the control instance attached to the given $select if any.
 *
 * @param $select TBD.
 */
const getControlObject = function($select: JQuerySelect) {
  const vueSelect = getVueSelect($select);
  if (vueSelect) {
    return vueSelect;
  } else if (chosenActive($select)) {
    return $select.data('chosen');
  }
};

/**
 * Fetch the children of the underlying select regardless of the widget
 * used.
 *
 * @param $select TBD.
 */
const getChildren = function($select: JQuery<HTMLSelectElement>) {
  return $select.children();
};

/**
 * Fetch the options of the underlying select regardless of the widget
 * used.
 *
 * @param $select TBD.
 */
const getOptions = function($select: JQuery<HTMLSelectElement>) {
  const $children = getChildren($select);
  return $children.filter('option').add($children.find('option')) as JQuery<HTMLOptionElement>;
};

/**
 * Fetch the possible values as flat array.
 *
 * @param $select TBD.
 */
const getOptionValues = function($select: JQuery<HTMLSelectElement>) {
  const values: string[] = [];
  getOptions($select).each(function() {
    const $option = $(this);
    if (!$option.prop('disabled')) {
      values.push($option.val() as string);
    }
  });
  return values;
};

/**
 * Find an option by its value
 *
 * @param $select TBD.
 *
 * @param value The value to search for.
 */
const findOptionByValue = function($select: JQuerySelect, value: string|number) {
  return getOptions($select).filter('option[value="' + value + '"]');
};

const makePlaceholder = function($select: JQuerySelect) {
  $select.each(function() {
    const $select = $(this);
    if (isVanilla($select)) {
      // restore the data-placeholder as first option if chosen
      // is not active
      $select.each(function() {
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
  });
};

const deselectAll = function($select: JQuerySelect) {
  $select.each(function() {
    const $select = $(this);
    const vueSelect = getVueSelect($select);
    if (vueSelect) {
      vueSelect.clear(true);
    } else {
      // deselect option items
      $select.find('option:selected').prop('selected', false);
      if (chosenActive($select)) {
        $select.trigger('chosen:updated');
      }
    }
  });
};

/**
 * Set or return the selected values and update the potentially
 * underlying Vue or Chosen widget. Works for "multiple" as
 * well as single selects.
 *
 * @param  $select collection with a single select.
 *
 * @param [values] If given then set the given values into the
 * select. If the select is not multiple and value is an array then
 * use values[0] as selected value. If values === false then
 *
 * @param [trigger] If trigger === true then trigger a change-event on
 * the select after installing the new values.
 */
const selectedValues = function(
  $select: JQuerySelect,
  values?: false|SelectValue|SelectValue[],
  trigger?: boolean,
): null|SelectValue|SelectValue[] {
  const vueSelect = getVueSelect($select);
  if (values === undefined) {
    if (vueSelect) {
      return vueSelect.getValue();
    }
    let result = $select.val() || [];
    if (!Array.isArray(result)) {
      result = [result as string];
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
    if (vueSelect) {
      vueSelect.setValue($select.prop('multiple') ? values : values[0] ?? null, true);
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
 * Fetch the selected option elements as jQuery collection.
 *
 * @param $select collection with a single select.
 */
const selectedOptions = function($select: JQuerySelect) {
  return getOptions($select).filter('option:selected');
};

/**
 * Update the underlying select widget to reflect changes in the
 * original select element. Supports Vue and Chosen.
 *
 * @param $select The select element.
 */
const refreshSelectWidget = function($select: JQuerySelect) {
  $select.each(function() {
    const $select = $(this);
    const isDisabled = $select.prop('disabled');
    const isReadonly = $select.prop('readonly');
    const vueSelect = getVueSelect($select);
    if (vueSelect) {
      vueSelect.refresh();
    } else if (chosenActive($select)) {
      if (isReadonly && !isDisabled) {
        $select.prop('disabled', true);
      }
      $select.trigger('chosen:updated');
      if (!isDisabled) {
        $select.prop('disabled', false);
      }
    }
  });
};

/**
 * Return the jQuery element which actually is shown on the screen.
 *
 * @param $select Select element.
 */
const getWidget = function($select: JQuery<HTMLSelectElement>) {
  const vueSelect = getVueSelect($select);
  if (vueSelect) {
    return $(vueSelect.element);
  } else if (chosenActive($select)) {
    return $select.next();
  } else {
    return $select;
  }
};

const getSelectFromWidget = function($widget: JQuery) {
  if ($widget.hasClass(`${appName}-vue-select-control`)) {
    const $element = $widget.prev();
    return $element.is('select') ? $element : $();
  } else if ($widget.is('select')) {
    return $widget;
  }
  return $();
};

/**
 * Flush the readonly and disabled properties from $select to the
 * underlying widget, if any.
 *
 * @param $select The select element.
 */
const refreshWidgetProperties = function($select: JQuerySelect) {
  $select.each(function() {
    const $element = $(this);
    const vueSelect = getVueSelect($element);
    if (vueSelect) {
      vueSelect.refreshProperties();
      return;
    }
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
    }
  });
};

/**
 * Replace the options of the given select by the given options.
 *
 * @param $select TBD.
 *
 * @param options TBD.
 */
const replaceSelectOptions = function($select: JQuerySelect, options: JQuery|string) {
  const vueSelect = getVueSelect($select);
  if (vueSelect) {
    if (typeof options === 'string') {
      $select.html(options);
    } else {
      $select.empty().append(options);
    }
    vueSelect.refresh();
    return;
  }
  const isDisabled = $select.prop('disabled');
  const isReadonly = $select.prop('readonly');
  if (typeof options === 'string') {
    $select.html(options);
  } else {
    $select.html('').append(options);
  }
  if (isReadonly && !isDisabled) {
    $select.prop('disabled', true);
  }
  if (chosenActive($select)) {
    $select.trigger('chosen:updated');
  }
  if (isReadonly && !isDisabled) {
    $select.prop('disabled', false);
  }
};

const locked = function($select: JQuerySelect, state?: boolean) {
  const vueSelect = getVueSelect($select);
  if (vueSelect) {
    if (state === undefined) {
      return vueSelect.isLocked || vueSelect.isDisabled;
    }
    if (state) {
      vueSelect.lock();
    } else {
      vueSelect.unlock();
    }
    return;
  }
  if (state === undefined) {
    return $select.prop('disabled') || $select.prop('readonly');
  } else {
    $select.prop('disabled', !!state);
    if (chosenActive($select)) {
      $select.trigger('chosen:updated');
    }
  }
};

export {
  getChildren as children,
  chosenActive,
  deselectAll,
  getControlObject,
  isVanilla,
  locked,
  makePlaceholder,
  findOptionByValue as optionByValue,
  getOptions as options,
  getOptionValues as optionValues,
  refreshSelectWidget as refreshWidget,
  refreshWidgetProperties,
  replaceSelectOptions as replaceOptions,
  selectedValues as selected,
  selectedOptions,
  getSelectFromWidget as selectFromWidget,
  vueSelectActive,
  getWidget as widget,
};

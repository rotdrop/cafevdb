/**
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
 */

import type SelectizeInstance from 'selectize';

import jQuery from './jquery.ts';

import 'select-utils.scss';

const $ = jQuery;

type SelectValue = string;

export type BLAH = SelectizeInstance<HTMLElement, string, { input: string }>['updatePlaceholder'];

// interface SelectizeInstance<Element extends HTMLElement = HTMLElement, T extends string = string, U = { input: string }> extends SelectizeClass<Element, T, U> {
//   revertSettings: {
//     $children: JQuery;
//   };
//   settings_user: Record<string, string>;
//   isDisabled: boolean;
//   isLocked: boolean;
// }

type JQuerySelect = JQuery<HTMLSelectElement>;

type Selectized<E extends HTMLElement = HTMLSelectElement, T extends string = string, U = { input: string }> = E & {
  selectize: SelectizeInstance<E, T, U>;
};

type MaybeSelectized<E extends HTMLElement = HTMLSelectElement, T extends string = string, U = { input: string }> = E & {
  selectize?: SelectizeInstance<E, T, U>;
};

/**
 * Fetch the selectize instance attached to the given $select if any.
 *
 * @param $select TBD.
 */
export function getSelectize<E extends HTMLElement, T extends string, U = { input: string }>($select: JQuery<Selectized<E, T, U>>): SelectizeInstance<E, T, U>;
export function getSelectize<E extends HTMLElement, T extends string, U = { input: string }>($select: JQuery<MaybeSelectized<E, T, U>>): undefined|SelectizeInstance<E, T, U>;
/**
 * Get the underlying selectize control structure.
 *
 * @param $select TBD.
 */
export function getSelectize<E extends HTMLElement, T extends string, U = { input: string }>($select: JQuery<MaybeSelectized<E, T, U>>|JQuery<Selectized<E, T, U>>) {
  return $select.length > 0 ? $select[0].selectize : undefined;
}

/**
 * Determine if the given element is managed by selectize.
 *
 * @param $select TBD.
 */
const selectizeActive = <E extends HTMLElement, T extends string, U = { input: string }>($select: JQuery<MaybeSelectized<E, T, U>>) =>
  !!getSelectize($select);

export const isSelectizedJQuery = <E extends HTMLElement, T extends string, U = { input: string }>($arg: JQuery<MaybeSelectized<E, T, U>>): $arg is JQuery<Selectized<E, T, U>> =>
  $arg.length > 0 && !!$arg[0].selectize;

/**
 * Determine if the given element is managed by jQuery chosen.
 *
 * @param $select TBD.
 */
const chosenActive = function($select: JQuerySelect) {
  return $select.data('chosen') !== undefined;
};

/**
 * Check whether this $select is controlled by either chosen or selectize.
 *
 * @param $select Select element.
 */
const isVanilla = function($select: JQuerySelect) {
  return !selectizeActive($select) && !chosenActive($select);
};

/**
 * Fetch the control instance attached to the given $select if any.
 *
 * @param $select TBD.
 */
const getControlObject = function($select: JQuerySelect) {
  if (chosenActive($select)) {
    return $select.data('chosen');
  } else if (isSelectizedJQuery($select)) {
    return getSelectize($select);
  }
};

/**
 * Fetch the children of the underlying select regardless of the widget
 * used.
 *
 * @param $select TBD.
 */
const getChildren = function($select: JQuery<HTMLSelectElement>) {
  const selectize = getSelectize($select);
  const $children = selectize ? selectize.revertSettings.$children : $select.children();
  if (selectize) {
    const values = selectize.items;
    $children.each(function() {
      const $child = $(this);
      if ($child.is('option')) {
        $child.prop('selected', values.indexOf($child.attr('value')!));
      }
    });
  }
  return $children;
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
  const selectize = getSelectize($select);
  if (selectize) {
    return Object.keys(selectize.options);
  } else {
    const values: string[] = [];
    getOptions($select).each(function() {
      const $option = $(this);
      if (!$option.prop('disabled')) {
        values.push($option.val() as string);
      }
    });
    return values;
  }
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
    if (!chosenActive($select) && !selectizeActive($select)) {
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
    if (isSelectizedJQuery($select)) {
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
  });
};

/**
 * Set or return the selected values and update the potentially
 * underlying "selectize" or "chosen" widget. Works for "multiple" as
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
  if (values === undefined) {
    let result: SelectValue|SelectValue[];
    if (isSelectizedJQuery($select)) {
      result = [...$select[0].selectize.items];
    } else {
      result = $select.val() || [];
      if (!Array.isArray(result)) {
        result = [result as string];
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
    if (isSelectizedJQuery($select)) {
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
 * @param $select collection with a single select.
 */
const selectedOptions = function($select: JQuerySelect) {
  return getOptions($select).filter('option:selected');
};

/**
 * Update the underlying select widget to reflect changes in the
 * original select element. This currently supports jQuery chosen and
 * selectize.
 *
 * In the case of selectize the children of the original select were
 * removed by selectize. After calling this function the option list
 * of the selectize widget will be replaced by the children of $select
 * on entry to this function if the $select.children() is non empty.
 *
 * @param $select The select element.
 */
const refreshSelectWidget = function($select: JQuerySelect) {
  $select.each(function() {
    const $select = $(this);
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
    } else if (isSelectizedJQuery($select)) {
      let selectize = $select[0].selectize;
      const setupOptions = selectize.settings_user;
      selectize.revertSettings.$children = $select.children().detach();
      $select.trigger('change');
      selectize.destroy();
      $select.trigger('change');
      if (isReadonly) {
        $select.prop('readonly', false);
      }
      if (isDisabled) {
        $select.prop('disabled', false);
      }
      $select.selectize(setupOptions);
      $select.trigger('change');
      selectize = $select[0].selectize;
      if (isDisabled || isReadonly) {
        selectize.disable();
      } else {
        selectize.enable();
      }
      $select.prop('disabled', isDisabled);
    }
  });
};

/**
 * Return the jQuery element which actually is shown on the screen.
 *
 * @param $select Select element.
 */
const getWidget = function($select: JQuery<HTMLSelectElement>) {
  if (chosenActive($select)) {
    return $select.next();
  } else if (isSelectizedJQuery($select)) {
    return getSelectize($select).$wrapper;
  } else {
    return $select;
  }
};

const getSelectFromWidget = function($widget: JQuery) {
  if ($widget.hasClass('selectize-control') || $widget.hasClass('chosen-container')) {
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
    } else if (isSelectizedJQuery($select)) {
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
  const isDisabled = $select.prop('disabled');
  const isReadonly = $select.prop('readonly');
  let setupOptions: Record<string, string>;
  const isSelectized = isSelectizedJQuery($select);
  if (isSelectized) {
    const selectize = $select[0].selectize;
    setupOptions = selectize.settings_user;
    selectize.destroy();
  }
  if (typeof options === 'string') {
    $select.html(options);
  } else {
    $select.html('').append(options);
  }
  if (isReadonly && !isDisabled) {
    $select.prop('disabled', true);
  }
  if (isSelectized) {
    $select.selectize(setupOptions!);
  } else if (chosenActive($select)) {
    $select.trigger('chosen:updated');
  }
  if (isReadonly && !isDisabled) {
    $select.prop('disabled', false);
  }
};

const locked = function($select: JQuerySelect, state?: boolean) {
  if (state === undefined) {
    if (isSelectizedJQuery($select)) {
      const selectize = $select[0].selectize;
      return selectize.isLocked || selectize.isDisabled;
    } else {
      return $select.prop('disabled') || $select.prop('readonly');
    }
  } else {
    if (isSelectizedJQuery($select)) {
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
  selectizeActive,
  getWidget as widget,
};

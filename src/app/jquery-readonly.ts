/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2023, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { refreshWidgetProperties, widget as selectWidget, isVanilla as isSelectVanilla } from './select-utils.ts';
import generateId from './generate-id.ts';

require('jquery-readonly.scss');

const topDataKey = '__jquery_readonly__';
const placeholderCssClass = '__jquery-readonly-placeholder__';
const elementReadonlyClass = '__jquery-readonly-active__';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type VanillaPropertyFunction = (propertyName: string, propertyValue?: boolean) => any;
const vanillaProp: VanillaPropertyFunction = $.fn.prop;

type ValueType =
  string
  | number
  | boolean
  | symbol
  | object
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  | ((this: HTMLElement, index: number, oldPropertyValue: any) => any)
  | null
  | undefined;

interface ReadOnlyData {
  mutationObserver?: MutationObserver,
  readonlyState?: boolean,
  readonlyPlaceholder?: JQuery,
  optionPlaceholdersInitialized?: boolean,
  readonlyRestoreDisabled?: boolean,
}

const data = <T extends HTMLElement>($arg: JQuery<T>) => {
  let data: ReadOnlyData = $arg.data(topDataKey);
  if (!data) {
    data = {};
    $arg.data(topDataKey, data);
  }
  return data;
};

const overrideProp = function<T extends HTMLElement>(
  this: JQuery<T>,
  property: string|JQuery.PlainObject,
  propertyValue?: ValueType,
) {
  const value: undefined|boolean = propertyValue as undefined|boolean;
  const outerArguments = value ? [property, value] as [string, boolean] : [property] as [string];
  if (propertyValue === undefined) {
    if (typeof property === 'string') {
      if (this.length === 0) {
        return;
      }
      const $this = this.first();
      const thisData = data($this);
      if (property === 'disabled' && thisData.readonlyState === true) {
        const rememberedState = thisData.readonlyRestoreDisabled;
        if (rememberedState !== undefined) {
          return rememberedState;
        }
      }
      return vanillaProp.call(this, property);
    } else {
      for (const [key, value] of Object.entries(property)) {
        overrideProp.call(this, key, value);
      }
      return this;
    }
  }
  this.each(function() {
    const $this = $(this);
    const thisData = data($this);
    if ($this.hasClass(placeholderCssClass)) {
      return;
    }
    if (property === 'disabled' && thisData.readonlyState === true) {
      const $placeholder = thisData.readonlyPlaceholder;
      if ($placeholder) {
        // enable and disable the placeholder instead of the element
        vanillaProp.apply($placeholder, outerArguments);
      }
      if ($this.is('option')) {
        // just tweak the to-be-restored value
        thisData.readonlyRestoreDisabled = value;
        const $optionPlaceholder = thisData.readonlyPlaceholder;
        if ($optionPlaceholder) {
          const optionDisabled = value || thisData.readonlyRestoreDisabled || !vanillaProp.call($this, 'selected');
          vanillaProp.call($optionPlaceholder, property, optionDisabled);
        }
      } else if (isJQuerySelect($this)) {
        // we have to disable/enable the placeholders as needed
        $this.find('option').each(function() {
          const $option = $(this);
          const $optionPlaceholder = data($option).readonlyPlaceholder;
          if ($optionPlaceholder) {
            const optionDisabled = value as boolean || $option.data().readonlyRestoreDisabled as boolean || !vanillaProp.call($option, 'selected');
            vanillaProp.call($optionPlaceholder, property, optionDisabled);
          }
        });
        if (vanillaProp.call($this, 'multiple')) {
          // just remember the value to be restored for multi selects
          thisData.readonlyRestoreDisabled = value;
        } else {
          // apply the disabled attribute to the surrounding single select
          vanillaProp.apply($this, outerArguments);
        }
      } else if ($this.is(':radio')) {
        // just tweak the to-be-restored value as the radio butotn is already disabled
        thisData.readonlyRestoreDisabled = value;
      } else if ($this.is(':checkbox')) {
        // just tweak the to-be-restored value as the checkbox is already disabled
        thisData.readonlyRestoreDisabled = value;
      } else if ($this.is(':button, :submit')) {
        thisData.readonlyRestoreDisabled = value;
      } else {
        vanillaProp.apply($this, outerArguments);
      }
    } else {
      vanillaProp.apply($this, outerArguments);
    }
  });
  return this;
};

$.fn.prop = overrideProp;

function generatePlaceHolder($element: JQuery, name: string, value: string, $pivotElement?: JQuery, remove?: boolean) {
  $pivotElement = $pivotElement || $element;
  let id = $element[0].id;
  if (!id) {
    $element[0].id = generateId();
    id = $element[0].id;
  }
  if (remove || remove === undefined) {
    $pivotElement.parent().find('input[name="' + name + '"] [value="' + value + '"]' + '.' + placeholderCssClass).remove();
  }
  const idClass = 'for-id-' + id;
  const $placeholder = $('<input type="hidden" name="' + name + '" class="' + placeholderCssClass + ' ' + idClass + '"/>');
  $pivotElement.before($placeholder);
  data($element).readonlyPlaceholder = $placeholder;

  const observer = data($pivotElement).mutationObserver || new MutationObserver((mutationList, _observer) => {
    for (const mutation of mutationList) {
      for (const removedNode of mutation.removedNodes) {
        const id = (removedNode as HTMLElement).id;
        if (id) {
          const selector = '.' + placeholderCssClass + '.for-id-' + id;
          $pivotElement.parent().find(selector).remove();
        }
      }
    }
  });
  data($pivotElement).mutationObserver = observer;
  observer.observe($pivotElement[0], { childList: true }); // attributes: true, subtree: true

  return $placeholder;
}

$.fn.readonly = function(state?: boolean|string) {
  if (state === undefined) {
    state = true;
  } else if (state === 'cleanup') {
    this.each(function() {
      let selector = '.' + placeholderCssClass;
      const $this = $(this);
      const thisData = data($this);
      const id = $this.attr('id');
      if (id) {
        selector += '.for-id-' + id;
      }
      $this.parent().find(selector).remove();
      const observer = thisData.mutationObserver;
      if (observer) {
        observer.disconnect();
      }
    });
    return this;
  } else {
    state = !!state;
  }
  this.each(function() {
    const $this = $(this);
    const thisData = data($this);
    if ($this.hasClass(placeholderCssClass)) {
      // do not allow to recurse into the placeholders
      return;
    }
    if (state === thisData.readonlyState) {
      // do not do it twice
      return;
    }
    thisData.readonlyState = state;
    $this.toggleClass(elementReadonlyClass, state);
    vanillaProp.call($this, 'readonly', state);
    if (!state) {
      $this.removeAttr('readonly');
    }
    if (isJQuerySelect($this)) {
      // Single-select can be handled like radio buttons, that is, we
      // disable all options safe the selected one. This essentially
      // makes for a read-only single-select element.
      if (!vanillaProp.call($this, 'multiple')) {
        $this.find('option').each(function() {
          const $option = $(this);
          if (!state) {
            const restoreDisabled = data($option).readonlyRestoreDisabled;
            if (restoreDisabled !== undefined) {
              vanillaProp.call($option, 'disabled', restoreDisabled);
            }
          } else {
            data($option).readonlyRestoreDisabled = vanillaProp.call($option, 'disabled');
            vanillaProp.call($option, 'disabled', !vanillaProp.call($option, 'selected'));
          }
          data($option).readonlyState = state;
        });
      } else {
        let name = $this.attr('name') ?? '';
        if (!name.endsWith('[]')) {
          name += '[]';
        }
        const placeholderInitialized = !!thisData.optionPlaceholdersInitialized;
        $this.find('option').each(function() {
          const $option = $(this);
          const optionValue = $option.attr('value') || $option.text();
          const placeholderDisabled = !state || !vanillaProp.call($option, 'selected');
          let placeholder = data($option).readonlyPlaceholder;
          if (!placeholder) {
            placeholder = generatePlaceHolder($option, name, optionValue, $this, placeholderInitialized);
          }
          placeholder.attr('value', optionValue);
          vanillaProp.call(placeholder, 'disabled', placeholderDisabled);
          if (!state) {
            const restoreDisabled = data($option).readonlyRestoreDisabled;
            if (restoreDisabled !== undefined) {
              vanillaProp.call($option, 'disabled', restoreDisabled);
            }
          } else {
            data($option).readonlyRestoreDisabled = vanillaProp.call($option, 'disabled');
            vanillaProp.call($option, 'disabled', true);
          }
          data($option).readonlyState = state;
        });
        thisData.optionPlaceholdersInitialized = true;

        if (!state) {
          const restoreDisabled = thisData.readonlyRestoreDisabled;
          if (restoreDisabled !== undefined) {
            vanillaProp.call($this, 'disabled', restoreDisabled);
          }
        } else {
          // disable the multi-select as all data is submitted via placeholders
          thisData.readonlyRestoreDisabled = vanillaProp.call($this, 'disabled');
          vanillaProp.call($this, 'disabled', true);
        }
      }
      if (!isSelectVanilla($this)) {
        refreshWidgetProperties($this);
        selectWidget($this).toggleClass(elementReadonlyClass, state).find('*').toggleClass(elementReadonlyClass, state);
      }
    } else if ($this.is(':radio')) {
      // Here the strategy is to just disable all radios safe the
      // selected one. As all other radios of the group are disabled,
      // the value is then read-only.
      let $container = $this.closest('fieldset');
      if (!$container) {
        $container = $this.closest('form');
      }
      if (!$container) {
        $container = $('body');
      }
      const $radioGroup = $container.find('input:radio[name="' + $this.attr('name') + '"]');
      $radioGroup.each(function() {
        const $radio = $(this);

        // remember the current state in each group member's data-set
        data($radio).readonlyState = state;
        $radio.toggleClass(elementReadonlyClass, state);
        vanillaProp.call($radio, 'readonly', state);

        if (!state) {
          const restoreDisabled = data($radio).readonlyRestoreDisabled;
          if (restoreDisabled !== undefined) {
            vanillaProp.call($radio, 'disabled', restoreDisabled);
          }
        } else {
          data($radio).readonlyRestoreDisabled = vanillaProp.call($radio, 'disabled');
          vanillaProp.call($radio, 'disabled', !vanillaProp.call($radio, 'checked'));
        }
      });
    } else if ($this.is(':checkbox')) {
      let placeholder = thisData.readonlyPlaceholder;
      const name = $this.attr('name') ?? '';
      const checkboxValue = $this.attr('value') || 'on';
      const placeholderDisabled = !state || !vanillaProp.call($this, 'checked');
      if (!placeholder) {
        placeholder = generatePlaceHolder($this, name, checkboxValue);
      }
      placeholder.attr('value', checkboxValue);
      vanillaProp.call(placeholder, 'disabled', placeholderDisabled);
      if (!state) {
        const restoreDisabled = thisData.readonlyRestoreDisabled;
        if (restoreDisabled !== undefined) {
          vanillaProp.call($this, 'disabled', restoreDisabled);
        }
      } else {
        thisData.readonlyRestoreDisabled = vanillaProp.call($this, 'disabled');
        vanillaProp.call($this, 'disabled', true);
      }
    } else if ($this.is(':button, :submit')) {
      // readonly-buttons do not make sense, but it simplifies the code in other places.
      if (!state) {
        const restoreDisabled = thisData.readonlyRestoreDisabled;
        if (restoreDisabled !== undefined) {
          vanillaProp.call($this, 'disabled', restoreDisabled);
        }
      } else {
        thisData.readonlyRestoreDisabled = vanillaProp.call($this, 'disabled');
        vanillaProp.call($this, 'disabled', true);
      }
    }
    if (!state) {
      delete data($this).readonlyRestoreDisabled;
    }
  });
  return this;
};

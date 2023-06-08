/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2022, 2023 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { refreshWidgetProperties, widget as selectWidget, isVanilla as isSelectVanilla } from './select-utils.js';
import generateId from './generate-id.js';

require('jquery-readonly.scss');

const mutationObserverDataKey = 'mutationObserver';
const readonlyStateDataKey = 'readonlyState';
const placeholderDataKey = 'readonlyPlaceholder';
const restoreDisabledDataKey = 'readonlyRestoreDisabled';
const placeholderCssClass = '__jquery-readonly-placeholder__';
const elementReadonlyClass = '__jquery-readonly-active__';

const vanillaProp = $.fn.prop;

const overrideProp = function(property, value) {
  const outerArguments = arguments;
  if (arguments.length === 1) {
    const $this = this.first();
    if (property === 'disabled' && $this.data(readonlyStateDataKey) === true) {
      const rememberedState = $this.data(restoreDisabledDataKey);
      if (rememberedState !== undefined) {
        return rememberedState;
      }
    }
    return vanillaProp.apply(this, outerArguments);
  }
  this.each(function() {
    const $this = $(this); // no-op ?
    if ($this.hasClass(placeholderCssClass)) {
      return;
    }
    if (property === 'disabled' && $this.data(readonlyStateDataKey) === true) {
      const $placeholder = $this.data(placeholderDataKey);
      if ($placeholder) {
        // enable and disable the placeholder instead of the element
        vanillaProp.apply($placeholder, outerArguments);
      }
      if ($this.is('option')) {
        // just tweak the to-be-restored value
        $this.data(restoreDisabledDataKey, value);
        const $optionPlaceholder = $this.data(placeholderDataKey);
        if ($optionPlaceholder) {
          const optionDisabled = value || $this.data(restoreDisabledDataKey) || !vanillaProp.call($this, 'selected');
          vanillaProp.call($optionPlaceholder, property, optionDisabled);
        }
      } else if ($this.is('select')) {
        // we have to disable/enable the placeholders as needed
        $this.find('option').each(function() {
          const $option = $(this);
          const $optionPlaceholder = $option.data(placeholderDataKey);
          if ($optionPlaceholder) {
            const optionDisabled = value || $option.data(restoreDisabledDataKey) || !vanillaProp.call($option, 'selected');
            vanillaProp.call($optionPlaceholder, property, optionDisabled);
          }
        });
        if (vanillaProp.call($this, 'multiple')) {
          // just remember the value to be restored for multi selects
          $this.data(restoreDisabledDataKey, value);
        } else {
          // apply the disabled attribute to the surrounding single select
          vanillaProp.apply($this, outerArguments);
        }
      } else if ($this.is(':radio')) {
        // just tweak the to-be-restored value as the radio butotn is already disabled
        $this.data(restoreDisabledDataKey, value);
      } else if ($this.is(':checkbox')) {
        // just tweak the to-be-restored value as the checkbox is already disabled
        $this.data(restoreDisabledDataKey, value);
      } else if ($this.is(':button, :submit')) {
        $this.data(restoreDisabledDataKey, value);
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

function generatePlaceHolder($element, name, value, $pivotElement, remove) {
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
  const placeholder = $('<input type="hidden" name="' + name + '" class="' + placeholderCssClass + ' ' + idClass + '"/>');
  $pivotElement.before(placeholder);
  $element.data(placeholderDataKey, placeholder);

  const observer = $pivotElement.data(mutationObserverDataKey) || new MutationObserver((mutationList, observer) => {
    for (const mutation of mutationList) {
      for (const removedNode of mutation.removedNodes) {
        const id = removedNode.id;
        if (id) {
          const selector = '.' + placeholderCssClass + '.for-id-' + id;
          $pivotElement.parent().find(selector).remove();
        }
      }
    }
  });
  $pivotElement.data(mutationObserverDataKey, observer);
  observer.observe($pivotElement[0], { childList: true }); // attributes: true, subtree: true

  return placeholder;
}

$.fn.readonly = function(state) {
  if (state === undefined) {
    state = true;
  } else if (state === 'cleanup') {
    this.each(function() {
      let selector = '.' + placeholderCssClass;
      const $this = $(this);
      const id = $this.attr('id');
      if (id) {
        selector += '.for-id-' + id;
      }
      $this.parent().find(selector).remove();
      const observer = $this.data(mutationObserverDataKey);
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
    if ($this.hasClass(placeholderCssClass)) {
      // do not allow to recurse into the placeholders
      return;
    }
    if (state === $this.data(readonlyStateDataKey)) {
      // do not do it twice
      return;
    }
    $this.data(readonlyStateDataKey, state);
    $this.toggleClass(elementReadonlyClass, state);
    vanillaProp.call($this, 'readonly', state);
    if (!state) {
      $this.removeAttr('readonly');
    }
    if ($this.is('select')) {
      // Single-select can be handled like radio buttons, that is, we
      // disable all options safe the selected one. This essentially
      // makes for a read-only single-select element.
      if (!vanillaProp.call($this, 'multiple')) {
        $this.find('option').each(function() {
          const $option = $(this);
          if (!state) {
            const restoreDisabled = $option.data(restoreDisabledDataKey);
            if (restoreDisabled !== undefined) {
              vanillaProp.call($option, 'disabled', restoreDisabled);
            }
          } else {
            $option.data(restoreDisabledDataKey, vanillaProp.call($option, 'disabled'));
            vanillaProp.call($option, 'disabled', !vanillaProp.call($option, 'selected'));
          }
          $option.data(readonlyStateDataKey, state);
        });
      } else {
        let name = $this.attr('name');
        if (!name.endsWith('[]')) {
          name += '[]';
        }
        const placeholderInitialized = $this.data(placeholderDataKey);
        $this.find('option').each(function() {
          const $option = $(this);
          const optionValue = $option.attr('value') || $option.text();
          const placeholderDisabled = !state || !vanillaProp.call($option, 'selected');
          let placeholder = $option.data(placeholderDataKey);
          if (!placeholder) {
            placeholder = generatePlaceHolder($option, name, optionValue, $this, placeholderInitialized);
          }
          placeholder.attr('value', optionValue);
          vanillaProp.call(placeholder, 'disabled', placeholderDisabled);
          if (!state) {
            const restoreDisabled = $option.data(restoreDisabledDataKey);
            if (restoreDisabled !== undefined) {
              vanillaProp.call($option, 'disabled', restoreDisabled);
            }
          } else {
            $option.data(restoreDisabledDataKey, vanillaProp.call($option, 'disabled'));
            vanillaProp.call($option, 'disabled', true);
          }
          $option.data(readonlyStateDataKey, state);
        });
        $this.data(placeholderDataKey, true);

        if (!state) {
          const restoreDisabled = $this.data(restoreDisabledDataKey);
          if (restoreDisabled !== undefined) {
            vanillaProp.call($this, 'disabled', restoreDisabled);
          }
        } else {
          // disable the multi-select as all data is submitted via placeholders
          $this.data(restoreDisabledDataKey, vanillaProp.call($this, 'disabled'));
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
        $radio.data(readonlyStateDataKey, state);
        $radio.toggleClass(elementReadonlyClass, state);
        vanillaProp.call($radio, 'readonly', state);

        if (!state) {
          const restoreDisabled = $radio.data(restoreDisabledDataKey);
          if (restoreDisabled !== undefined) {
            vanillaProp.call($radio, 'disabled', restoreDisabled);
          }
        } else {
          $radio.data(restoreDisabledDataKey, vanillaProp.call($radio, 'disabled'));
          vanillaProp.call($radio, 'disabled', !vanillaProp.call($radio, 'checked'));
        }
      });
    } else if ($this.is(':checkbox')) {
      let placeholder = $this.data(placeholderDataKey);
      const name = $this.attr('name');
      const checkboxValue = $this.attr('value') || 'on';
      const placeholderDisabled = !state || !vanillaProp.call($this, 'checked');
      if (!placeholder) {
        placeholder = generatePlaceHolder($this, name, checkboxValue);
      }
      placeholder.attr('value', checkboxValue);
      vanillaProp.call(placeholder, 'disabled', placeholderDisabled);
      if (!state) {
        const restoreDisabled = $this.data(restoreDisabledDataKey);
        if (restoreDisabled !== undefined) {
          vanillaProp.call($this, 'disabled', restoreDisabled);
        }
      } else {
        $this.data(restoreDisabledDataKey, vanillaProp.call($this, 'disabled'));
        vanillaProp.call($this, 'disabled', true);
      }
    } else if ($this.is(':button, :submit')) {
      // readonly-buttons do not make sense, but it simplifies the code in other places.
      if (!state) {
        const restoreDisabled = $this.data(restoreDisabledDataKey);
        if (restoreDisabled !== undefined) {
          vanillaProp.call($this, 'disabled', restoreDisabled);
        }
      } else {
        $this.data(restoreDisabledDataKey, vanillaProp.call($this, 'disabled'));
        vanillaProp.call($this, 'disabled', true);
      }
    }
    if (!state) {
      $this.removeData(restoreDisabledDataKey);
    }
  });
  return this;
};

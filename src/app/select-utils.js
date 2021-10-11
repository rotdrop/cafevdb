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

require('select-utils.scss');

// find an option by its value
const findOptionByValue = function($select, value) {
  return $select.find('option[value="' + value + '"]');
};

/**
 * Determine if the given element is managed by jQuery chosen.
 *
 * @returns bool
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
//        .prop('selected', true)
        .html(placeHolder);
    });
  }
};

/**
 * Determine if the given element is managed by selectize.
 *
 * @returns bool
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

const selectedValues = function($select, values) {
  if (values === undefined) {
    if (selectizeActive($select)) {
      return $select[0].selectize.items;
    } else {
      return $select.val() || [];
    }
  } else {
    if (selectizeActive($select)) {
      const selectize = $select[0].selectize;
      selectize.clear(true);
      selectize.addItems(values, true);
      selectize.deselectAll();
      selectize.refreshItems(true);
    } else {
      $select.find('option').each(function(idx) {
        const $option = $(this);
        $option.prop('selected', values.includes($option.val()));
      });
      if (chosenActive($select)) {
        $select.trigger('chosen:updated');
      }
    }
  }
};

/**
 * Update the underlying select widget to reflect changes in the
 * original select element. This currently support jQuery chosen and
 * selectize.
 */
const refreshSelectWidget = function($select) {
  console.info('ORIG SELECTED', $select.val());
  console.info('SELECTED', selectedValues($select));
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

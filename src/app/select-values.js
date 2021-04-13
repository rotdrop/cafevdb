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

import { $ } from './globals.js';

/**
 * Get or set the option value(s) of a select box.
 *
 * @param {jQuery} select The select element. If it is an ordinary input
 * element, then in "set" mode its value is set to optionValues[0].
 *
 * @param {Object} optionValues Single option value or array of option
 * values to set. For single selects or ordinary inputs only
 * optionValues[0] is taken into account. If optionValues is not an
 * array, then implicitly [ optionValues ] is used.
 *
 * @returns {bool|Array}
 */
const selectValues = function(select, optionValues) {
  const $select = $(select);
  const multiple = $select.prop('multiple');
  if (typeof optionValues === 'undefined') {
    console.debug('selectValues read = ', $select.val());
    let result = $select.val();
    if (multiple && !result) {
      result = [];
    }
    return result;
  }
  if (!(optionValues instanceof Array)) {
    optionValues = [optionValues];
  }
  if (!multiple) {
    optionValues = [ optionValues[0] ];
  }
  // setter has to use foreach
  $select.each(function(idx) {
    const $self = $(this);
    if (!$self.prop('multiple')) {
      $self.val(optionValues[0]);
    } else {
      $self.find('option').each(function(idx) {
        const $option = $(this);
        $option.prop('selected', optionValues.indexOf($option.val()) >= 0);
      });
      // $self.trigger('chosen:updated'); // in case ...
    }
  });
  $select.trigger('change'); // ???
  return optionValues;
};

export default selectValues;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, $ } from './globals.js';

function importAll(r) {
  r.keys().forEach(r);
}

require('jquery-ui/ui/widgets/datepicker');
importAll(require.context('jquery-ui/ui/i18n/', true, /datepicker-.*\.js$/));
require('jquery-datetimepicker/build/jquery.datetimepicker.full.js');
require('jquery-datetimepicker/build/jquery.datetimepicker.min.css');

// Override jquery-ui datepicker a little bit. Note that the
// datepicker widget does not seem to follow the ui-widget framework
// in its definition, so do it the hard way.
const jQueryUiDatePicker = $.fn.datepicker;
const onselectDatePickerReason = appName + ' datepicker onselect';
const datePickerInterceptEvents = ['focusout', 'blur'].map(x => x + '.' + appName).join(' ');
const datePickerOldValue = appName + 'OldValue';
$.fn.datepicker = function(options) {
  const $this = $(this); // maybe a collection

  $this.each(function() { const $this = $(this); $this.data(datePickerOldValue, $this.val()); });

  if (options === 'destroy') {
    $this.off(datePickerInterceptEvents);
    $this.each(function() { const $this = $(this); $this.removeData(datePickerOldValue); });
  } else if ((typeof options === 'object' && options !== null) || options === undefined) {
    $this
      .off(datePickerInterceptEvents)
      .on(datePickerInterceptEvents, function(event, reason) {
        const $eventTarget = $(this); // the individual element
        if (reason !== onselectDatePickerReason) {
          // wait until the date-picker has done its work
          event.stopImmediatePropagation();
          event.preventDefault();
          $.fn.cafevTooltip.remove(); // remove left-overs after cancelling focus-out
          console.debug('Catched datepicker blur/focusout event', event, [...arguments]);
          return false;
        } else {
          const value = $eventTarget.val();
          const oldValue = $eventTarget.data(datePickerOldValue);
          if (value !== oldValue) {
            console.debug(
              'Trigger change after date-picker blur-event old / new '
                + value + ' / ' + oldValue);
            $eventTarget.data(datePickerOldValue, value);
            $eventTarget.trigger('change');
          }
        }
      });
    console.debug('Attached jQuery-UI datepicker short-coming blur event.');
  }
  return jQueryUiDatePicker.apply(this, arguments);
};

const datePickerDefaults = $.datepicker.regional[globalState.language] || {};
$.extend(datePickerDefaults, {
  beforeShow(inputElement) {
    const $inputElement = $(inputElement);
    if ($inputElement.prop('readonly')) {
      return false;
    }
    $inputElement.data(datePickerOldValue, $inputElement.val());
    return true;
  },
  // The datepicker will not trigger the 'change' event when onSelect() is there
  onSelect(dateText, datePickerInstance) {
    const $inputElement = $(this);
    console.debug('Re-trigger jQuery-UI datepicker blur event AFTER set-date');
    $inputElement.trigger('blur', onselectDatePickerReason);
  },
});

$.datepicker.setDefaults(datePickerDefaults);
$.datetimepicker.setLocale(globalState.language);

// convert to php format, incomplete
const dateFormat = $.datepicker.regional[globalState.language].dateFormat
  .replace(/yy/g, 'Y')
  .replace(/MM/g, 'F')
//  .replace(/M/g, 'M')
  .replace(/mm/g, 'MM')
  .replace(/m/g, 'n')
  .replace(/MM/g, 'm')
  .replace(/DD/g, 'l')
//  .replace(/D/g, 'D')
  .replace(/dd/g, 'DD')
  .replace(/d/g, 'j')
  .replace(/DD/g, 'd')
;
const timeFormat = 'H:i';
const dateTimeFormat = [dateFormat, timeFormat].join(', ');

// override datetimepicker a little bit
const jQueryDateTimePicker = $.fn.datetimepicker;
$.fn.datetimepicker = function(opt, opt2) {
  $.extend(opt, {
    format: dateTimeFormat,
    formatTime: timeFormat,
    formatDate: dateFormat,
    step: 5,
    onShow(currentTime, $inputElement, event) {
      return !$inputElement.prop('readonly');
    },
    // onChangeDateTime(currentTime, $inputElement, event) {
    //   // const dateTimePicker = this;
    //   // $inputElement.blur();
    //   console.info('DATETIMEPICKER CURRENT TIME', currentTime);
    // },
    onClose(currentTime, $inputElement, event) {
      // $inputElement.trigger('blur');
      $inputElement.trigger('focusout');
    },
  }, opt);
  return jQueryDateTimePicker.apply(this, arguments);
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

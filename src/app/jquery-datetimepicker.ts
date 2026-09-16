/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from '../config.ts';
import { globalState } from './globals.ts';
import $, { jq } from './jquery.ts';

import 'jquery-ui/ui/version';
import 'jquery-ui/ui/widget';
import 'jquery-ui/ui/widgets/datepicker';
import 'jquery-datetimepicker/build/jquery.datetimepicker.full.js';
import 'jquery-datetimepicker/build/jquery.datetimepicker.min.css';

const getLanguageAsset = (lang: string) => `../../node_modules/jquery-ui/ui/i18n/datepicker-${lang}.js`;
const languageModules = import.meta.glob('../../node_modules/jquery-ui/ui/i18n/datepicker-*.js');

// Override jquery-ui datepicker a little bit. Note that the
// datepicker widget does not seem to follow the ui-widget framework
// in its definition, so do it the hard way.
const jQueryUiDatePicker = $.fn.datepicker;
const onselectDatePickerReason = appName + ' datepicker onselect';
const datePickerInterceptEvents = ['focusout', 'blur'].map((x) => x + '.' + appName).join(' ');
const datePickerOldValue = `${appName}OldValue` as const;

type DatePickerOptions = JQueryUI.DatepickerOptions;

// @ts-expect-error 2322 I DO NOT CARE.
$.fn.datepicker = function(options?: string|DatePickerOptions, ...rest: unknown[]) {
  const $this = $(this); // maybe a collection

  $this.each(function() {
    const $this = $(this);
    $this.data(datePickerOldValue, $this.val() ?? null);
  });

  if (options === 'destroy') {
    $this.off(datePickerInterceptEvents);
    $this.each(function() {
      const $this = $(this);
      $this.removeData(datePickerOldValue);
    });
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
          console.debug('Catched datepicker blur/focusout event', event, [options, ...rest]);
          return false;
        } else {
          const value = $eventTarget.val();
          const oldValue = $eventTarget.data(datePickerOldValue);
          if (value !== oldValue) {
            console.debug(
              'Trigger change after date-picker blur-event old / new '
                + value + ' / ' + oldValue,
            );
            $eventTarget.data()[datePickerOldValue] = value;
            $eventTarget.trigger('change');
          }
        }
      });
    console.debug('Attached jQuery-UI datepicker short-coming blur event.');
  }
  if (options !== undefined) {
    // @ts-expect-error 2556 I DO NOT CARE.
    return jQueryUiDatePicker.call(this, options, ...rest);
  } else {
    return jQueryUiDatePicker.apply(this);
  }
};

console.info('GLOBAL STATE LANG', { lang: globalState.language, asset: getLanguageAsset(globalState.language ?? 'en') });

languageModules[getLanguageAsset(globalState.language ?? 'en')]().then((module) => {

  console.info('DATE GLOBAL STATE', { gs: { ...globalState }, lang: globalState.language, module });

  const datePickerDefaults = $.datepicker.regional[globalState.language] || {};
  Object.assign(
    datePickerDefaults,
    {
      beforeShow(inputElement: string|HTMLElement|JQuery) {
        const $inputElement = jq(inputElement);
        if ($inputElement.prop('readonly')) {
          return false;
        }
        $inputElement.data()[datePickerOldValue] = $inputElement.val();
        return true;
      },
      // The datepicker will not trigger the 'change' event when onSelect() is there
      onSelect(_dateText: string, _datePickerInstance: unknown) {
        const $inputElement = $(this);
        console.debug('Re-trigger jQuery-UI datepicker blur event AFTER set-date');
        $inputElement.trigger('blur', onselectDatePickerReason);
      },
    },
  );

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
  $.fn.datetimepicker = function(argument: Record<string, unknown>, ...rest: unknown[]) {
    if (rest.length === 0 && typeof argument === 'object' && argument !== null) {
      argument = {
        format: dateTimeFormat,
        formatTime: timeFormat,
        formatDate: dateFormat,
        step: 5,
        onShow(_currentTime: unknown, $inputElement: JQuery, _event: unknown) {
          return !$inputElement.prop('readonly');
        },
        // onChangeDateTime(currentTime, $inputElement, event) {
        //   // const dateTimePicker = this;
        //   // $inputElement.blur();
        //   console.info('DATETIMEPICKER CURRENT TIME', currentTime);
        // },
        onClose(_currentTime: unknown, $inputElement: JQuery, _event: unknown) {
          // $inputElement.trigger('blur');
          $inputElement.trigger('focusout');
        },
        ...argument,
      };
    }
    return jQueryDateTimePicker.call(this, argument, ...rest);
  };
});

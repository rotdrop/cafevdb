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

import { onRequestTokenUpdate } from '@nextcloud/auth';
import { initialState, appName, cloudWebRoot, webRoot, cloudUser, appPrefix } from './config.js';

function importAll(r) {
  r.keys().forEach(r);
}

// jQuery stuff

const jQuery = require('jquery');
const $ = jQuery;

window.$ = $;
window.jQuery = jQuery;

require('jquery-ui');
require('jquery-ui/ui/effect');
require('jquery-ui/ui/widgets/dialog');
require('jquery-ui/ui/widgets/datepicker');
require('jquery-ui/ui/widgets/tabs');
importAll(require.context('jquery-ui/ui/i18n/', true, /datepicker-.*\.js$/));
require('jquery-datetimepicker/build/jquery.datetimepicker.full.js');
require('jquery-datetimepicker/build/jquery.datetimepicker.min.css');
require('chosen/public/chosen.jquery.js');
require('chosen/public/chosen.css');

const ImagesLoaded = require('imagesloaded');
ImagesLoaded.makeJQueryPlugin(jQuery);

// some nextcloud hacks

require('../legacy/nextcloud/jquery/requesttoken.js');
require('@nextcloud/dialogs/styles/toast.scss');

// CSS unrelated to particular modules

require('oc-fixes.css');
require('mobile.scss');
require('config-check.scss');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

if (window.CAFEFDB === undefined) {
  window.CAFEVDB = initialState.CAFEVDB;
  // @TODO the nonce in principle could go to the initial-state
  window.CAFEVDB.nonce = btoa(OC.requestToken);
}
const globalState = window.CAFEVDB;
let nonce = globalState.nonce;

onRequestTokenUpdate(function(token) {
  globalState.nonce = btoa(token);
  nonce = globalState.nonce;
  console.info('NEW REQUEST TOKEN', token);
});

// Override jquery-ui datepicker a little bit. Note that the
// datepicker widget does not seem to follow the ui-widget framework
// in its definition, so do it the hard way.
const jQueryUiDatePicker = $.fn.datepicker;
const onselectDatePickerReason = 'cafevdb datepicker onselect';
$.fn.datepicker = function(options) {
  const $this = $(this);

  if (options === 'destroy') {
    $this.off('focusout.cafevdb blur.cafevdb');
  } else if ((typeof options === 'object' && options !== null) || options === undefined) {
    $this
      .off('focusout.cafevdb blur.cafevdb')
      .on('focusout.cafevdb blur.cafevdb', function(event, reason) {
        if (reason !== onselectDatePickerReason) {
          // wait until the date-picker has done its work
          event.stopImmediatePropagation();
          event.preventDefault();
          $.fn.cafevTooltip.remove(); // remove left-overs after cancelling focus-out
          console.debug('Catched datepicker blur/focusout event', event, [...arguments]);
          return false;
        }
      });
    console.debug('Attached jQuery-UI datepicker short-coming blur event.');
  }
  return jQueryUiDatePicker.apply($this, arguments);
};

const datePickerDefaults = $.datepicker.regional[globalState.language] || {};
$.extend(datePickerDefaults, {
  beforeShow(inputElement) {
    return !$(inputElement).prop('readonly');
  },
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

export {
  globalState,
  appName,
  webRoot,
  cloudWebRoot,
  nonce,
  jQuery,
  $,
  cloudUser,
  appPrefix,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

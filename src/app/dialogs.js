/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
/**
 * @file Short popup dialogs. ATM just a wrapper around the old legacy
 * OC dialogs.
 */

import $ from './jquery.js';
import { appName } from './app-info.js';

require('dialogs.scss');

const alert = function(text, title, callback, modal, allowHtml) {
  return OC.dialogs.message(
    text,
    title,
    'alert',
    OC.dialogs.OK_BUTTON,
    callback,
    modal,
    allowHtml
  );
};

const info = function(text, title, callback, modal, allowHtml) {
  return OC.dialogs.message(
    text,
    title,
    'info',
    OC.dialogs.OK_BUTTON,
    callback,
    modal,
    allowHtml
  );
};

const confirm = function(text, title, options, modal, allowHtml) {
  const defaultOptions = {
    callback() {},
    model: false,
    allowHtml: false,
    default: 'confirm',
  };
  if (typeof options === 'function') {
    options = {
      callback: options,
      modal,
      allowHtml,
    };
  }

  let buttons;
  if (options.default === 'cancel') {
    buttons = {
      type: OC.dialogs.YES_NO_BUTTONS,
      confirm: t('core', 'No'),
      cancel: t('core', 'Yes'),
    };
    const userCallback = options.callback;
    options.callback = choice => userCallback(!choice);
  } else {
    buttons = OC.dialogs.YES_NO_BUTTONS;
  }
  options = $.extend({}, defaultOptions, options);
  return OC.dialogs.message(
    text,
    title,
    'notice',
    buttons,
    options.callback,
    options.model,
    options.allowHtml
  );
};

/**
 * Popup a dialog with debug info if data.data.debug is set and non
 * empty.
 *
 * @param {Object} data TBD.
 *
 * @param {Function} callback TBD.
 *
 */
const debugPopup = function(data, callback) {
  if (typeof data.debug !== 'undefined' && data.debug !== '') {
    if (typeof callback !== 'function') {
      callback = undefined;
    }
    info('<div class="debug error contents">' + data.debug + '</div>',
	 t(appName, 'Debug Information'),
	 callback, true, true);
  }
};

const filePicker = function(title, callback, multiselect, mimetypeFilter, modal, type, path, options) {
  return OC.dialogs.filepicker(title, callback, multiselect, mimetypeFilter, modal, type, path, options);
};

const attachDialogHandlers = function(container) {

  const $container = $(container || 'body');

  $container.on('dblclick', '.oc-dialog', function() {
    $('.oc-dialog').toggleClass('maximize-width');
  });

  $container.on('click', '.oc-dialog .exception.error.name', function() {
    $(this).next().toggleClass('visible');
  });

  $container.on('click', '.oc-dialog .error.exception ul.technical', function() {
    $(this).nextAll('.trace').toggleClass('visible');
  });

  $container.on('click', '.oc-dialog .error.exception .trace.visible', function() {
    const $this = $(this);
    $this.removeClass('visible');
    $this.next('.trace').removeClass('visible');
    $this.prev('.trace').removeClass('visible');
  });
};

export {
  alert,
  info,
  confirm,
  debugPopup,
  filePicker,
  attachDialogHandlers,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

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
/**
 * @file Short popup dialogs. ATM just a wrapper around the old legacy
 * OC dialogs.
 */

// Compatibility
import { appName, $ } from './globals.js';

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

const confirm = function(text, title, callback, modal, allowHtml) {
  return OC.dialogs.message(
    text,
    title,
    'notice',
    OC.dialogs.YES_NO_BUTTONS,
    callback,
    modal,
    allowHtml
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

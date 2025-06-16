/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import { appName } from '../config.ts';
import { DialogBuilder } from '@nextcloud/dialogs';

require('dialogs.scss');

const YES_NO_BUTTONS = 70;
const OK_BUTTONS = 71;

/* eslint-disable n/no-callback-literal */

const getLegacyButtons = function(buttons, callback) {
  const buttonList = [];

  switch (typeof buttons === 'object' ? buttons.type : buttons) {
  case YES_NO_BUTTONS:
    buttonList.push({
      label: buttons?.cancel ?? t('core', 'No'),
      callback: () => {
        callback._clicked = true;
        callback(false);
      },
    });
    buttonList.push({
      label: buttons?.confirm ?? t('core', 'Yes'),
      type: 'primary',
      callback: () => {
        callback._clicked = true;
        callback(true);
      },
    });
    break;
  case OK_BUTTONS:
    buttonList.push({
      label: buttons?.confirm ?? t('core', 'OK'),
      type: 'primary',
      callback: () => {
        callback._clicked = true;
        callback(true);
      },
    });
    break;
  default:
    console.error('Invalid call to OC.dialogs');
    break;
  }
  return buttonList;
};

const message = function(content, title, dialogType, buttons, callback = () => {}, modal, allowHtml) {

  console.info('MESSAGE', { content, title, dialogType, buttons, callback, modal, allowHtml });

  const builder = (new DialogBuilder())
    .setName(title)
    .setText(allowHtml ? '' : content)
    .setButtons(getLegacyButtons(buttons, callback));

  switch (dialogType) {
  case 'alert':
    builder.setSeverity('warning');
    break;
  case 'notice':
    builder.setSeverity('info');
    break;
  default:
    break;
  }

  const dialog = builder.build();

  console.info('DIALOG', { dialog });

  if (allowHtml) {
    dialog.setHTML(content);
  }

  return dialog.show().then(() => {
    if (!callback._clicked) {
      callback(false);
    }
  });
};

const alert = function(text, title, callback, modal, allowHtml) {
  return message(
    text,
    title,
    'alert',
    OC.dialogs.OK_BUTTONS,
    callback,
    modal,
    allowHtml,
  );
};

const info = function(text, title, callback, modal, allowHtml) {
  return message(
    text,
    title,
    'info',
    OC.dialogs.OK_BUTTONS,
    callback,
    modal,
    allowHtml,
  );
};

const confirm = function(text, title, options, modal, allowHtml) {
  const defaultOptions = {
    callback() {},
    modal: false,
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

  options = { ...defaultOptions, ...options };

  if (!options.buttons || options.buttons === OC.dialogs.YES_NO_BUTTONS) {
    options.buttons = {
      type: OC.dialogs.YES_NO_BUTTONS,
    };
  }
  const classes = [appName, 'default-' + options.default];
  if (options.buttons.confirmClasses) {
    if (Array.isArray(options.buttons.confirmClasses)) {
      classes.splice(classes.length, ...options.buttons.confirmClasses);
    } else {
      classes.push(options.buttons.confirmClasses);
    }
  }
  options.buttons.confirmClasses = classes;

  console.debug('CONFIRM TEXT', { text, title, options, modal, allowHtml });

  return new Promise((resolve, reject) =>
    message(
      text,
      title,
      'notice',
      options.buttons,
      (answer) => {
        options.callback(answer);
        // do not reject, as this triggers an exception
        resolve(answer);
      },
      options.modal,
      options.allowHtml,
    ).then(() => {
      $('body').find('.oc-dialog-buttonrow.twobuttons').each(function() {
        const $buttonRow = $(this);
        const $confirmButton = $buttonRow.find('button.primary.' + appName);
        if ($confirmButton.hasClass('default-cancel')) {
          const $cancelButton = $buttonRow.find('button:not(.primary)');
          $confirmButton.removeClass('primary');
          $cancelButton.addClass('primary');
        }
      });
    }),
  );
};

/**
 * Popup a dialog with debug info if data.data.debug is set and non
 * empty.
 *
 * @param {object} data TBD.
 *
 * @param {Function} callback TBD.
 *
 */
const debugPopup = function(data, callback) {
  if (typeof data.debug !== 'undefined' && data.debug !== '') {
    if (typeof callback !== 'function') {
      callback = undefined;
    }
    info(
      '<div class="debug error contents">' + data.debug + '</div>',
      t(appName, 'Debug Information'),
      callback, true, true);
  }
};

const filePicker = function(title, callback, multiselect, mimetypeFilter, modal, type, path, options) {
  return OC.dialogs.filepicker(title, callback, multiselect, mimetypeFilter || [], modal, type, path, options);
};

const attachDialogHandlers = function(container) {

  const $container = $(container || 'body');

  if ($container.data(appName + 'DialogHandlersAttached')) {
    return;
  }

  $container.data(appName + 'DialogHandlersAttached', true);

  $container
    .off('dblclick', '.oc-dialog')
    .on('dblclick', '.oc-dialog', function(event) {
      $('.oc-dialog').toggleClass('maximize-width');
      event.stopImmediatePropagation();
    });

  $container
    .off('click', '.oc-dialog .exception.error.name')
    .on('click', '.oc-dialog .exception.error.name', function(event) {
      $(this).next().toggleClass('visible');
      event.stopImmediatePropagation();
    });

  $container
    .off('click', '.oc-dialog .error.exception ul.technical')
    .on('click', '.oc-dialog .error.exception ul.technical', function(event) {
      $(this).nextAll('.trace').toggleClass('visible');
      event.stopImmediatePropagation();
    });

  $container
    .off('click', '.oc-dialog .error.exception .trace.visible')
    .on('click', '.oc-dialog .error.exception .trace.visible', function(event) {
      const $this = $(this);
      $this.removeClass('visible');
      $this.next('.trace').removeClass('visible');
      $this.prev('.trace').removeClass('visible');
      event.stopImmediatePropagation();
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

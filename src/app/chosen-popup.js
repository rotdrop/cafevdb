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

import { appName, $ } from './globals.js';
import './jquery-extensions.js';

/*
 * jQuery dialog popup with one chosen multi-selelct box inside.
 */
const chosenPopup = function(contents, userOptions) {
  const defaultOptions = {
    title: t(appName, 'Choose some Options'),
    position: {
      my: 'center center',
      at: 'center center',
      of: window,
    },
    dialogClass: false,
    saveText: t(appName, 'Save'),
    saveTitle: t(
      appName,
      'Accept the currently selected options and return to the underlying form. '),
    cancelText: t(appName, 'Cancel'),
    cancelTitle: t(
      appName,
      'Discard the current selection and close the dialog. '
        + 'The initial set of selected options will remain unchanged.'),
    buttons: [], // additional buttons.
    openCallback: false,
    saveCallback: false,
    closeCallback: false,
  };
  const options = $.extend({}, defaultOptions, userOptions);

  const cssClass = (options.dialogClass ? options.dialogClass + ' ' : '') + 'chosen-popup-dialog';
  const dialogHolder = $('<div class="' + cssClass + '"></div>');
  dialogHolder.html(contents);
  const selectElement = dialogHolder.find('select');
  $('body').append(dialogHolder);

  let buttons = [
    {
      text: options.saveText,
      // icons: { primary: 'ui-icon-check' },
      class: 'save',
      title: options.saveTitle,
      click() {
        const selectedOptions = [];
        selectElement.find('option:selected').each(function(idx) {
          const self = $(this);
          selectedOptions[idx] = {
            value: self.val(),
            html: self.html(),
            text: self.text(),
          };
        });
        // alert('selected: ' + JSON.stringify(selectedOptions));
        if (typeof options.saveCallback === 'function') {
          options.saveCallback.call(this, selectElement, selectedOptions);
        }

        return false;
      },
    },
    {
      text: options.cancelText,
      class: 'cancel',
      title: options.cancelTitle,
      click() {
        $(this).dialog('close');
      },
    },
  ];
  buttons = buttons.concat(options.buttons);

  dialogHolder.cafevDialog({
    title: options.title,
    position: options.position,
    dialogClass: cssClass,
    modal: true,
    draggable: false,
    closeOnEscape: false,
    width: 'auto',
    height: 'auto',
    resizable: false,
    buttons,
    open() {
      selectElement.chosen(); // {disable_search_threshold: 10});
      const dialogWidget = dialogHolder.dialog('widget');
      toolTipsInit(dialogWidget);
      dialogHolder.find('.chosen-container')
        .off('dblclick')
        .on('dblclick', function(event) {
          dialogWidget.find('.ui-dialog-buttonset .ui-button.save').trigger('click');
          return false;
        });

      if (typeof options.openCallback === 'function') {
        options.openCallback.call(this, selectElement);
      }
    },
    close() {
      if (typeof options.closeCallback === 'function') {
        options.closeCallback.call(this, selectElement);
      }

      $.fn.cafevTooltip.remove();
      dialogHolder.dialog('close');
      dialogHolder.dialog('destroy').remove();
    },
  });
};

export default chosenPopup;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

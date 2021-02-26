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
 * @file Some support functions for jQuery dialogs.
 */

// Compatibility
import { appName, $ } from './globals.js';

/**
 * Add a to-back-button to the titlebar of a jQuery-UI dialog. The
 * purpose is to be able to move the top-dialog to be bottom-most,
 * juse above a potential "modal" window layer.
 *
 * @Param {jQuery} dialogHolder TBD.
 */
function dialogToBackButton(dialogHolder) {
  const dialogWidget = dialogHolder.dialog('widget');
  const toBackButtonTitle = t(
    appName,
    'If multiple dialogs are open, '
      + 'then move this one to the lowest layer '
      + 'and display it below the others. '
      + 'Clicking anywhere on the dialog will bring to the front again.');
  const toBackButton = $('<button class="toBackButton customDialogHeaderButton" title="' + toBackButtonTitle + '"></button>');
  toBackButton.button({
    label: '_',
    icons: { primary: 'ui-icon-minusthick', secondary: null },
    text: false,
  });
  dialogWidget.find('.ui-dialog-titlebar').append(toBackButton);
  toBackButton.cafevTooltip({ placement: 'auto' });

  toBackButton.off('click');
  toBackButton.on('click', function() {
    const overlay = $('.ui-widget-overlay:last');
    let overlayIndex = 100; // OwnCloud header resides at 50.
    if (overlay.length > 0) {
      overlayIndex = parseInt(overlay.css('z-index'));
    }
    // will be only few, so what
    let needShuffle = false;
    $('.ui-dialog.ui-widget').not('.cafevdb-modalizer').each(function(index) {
      const thisIndex = parseInt($(this).css('z-index'));
      if (thisIndex === overlayIndex + 1) {
        needShuffle = true;
      }
    }).each(function(index) {
      if (needShuffle) {
        const thisIndex = parseInt($(this).css('z-index'));
        $(this).css('z-index', thisIndex + 1);
      }
    });
    dialogWidget.css('z-index', overlayIndex + 1);
    return false;
  });
};

/**
 * jQuery UI just is not flexible enough. We want to be able to
 * completely intercept the things the close button initiates. I
 * just did not find any other way than to hide the close button
 * completely and add another button with the same layout instead,
 * but this time with complete control over the events triggered by
 * this button.
 *
 * If callback is undefined, then simply call the close
 * method. Otherwise it is called like callback(event, dialogHolder).
 *
 * @param {jQuery} dialogHolder TBD.
 *
 * @param {Function} callback TBD.
 *
 */
function dialogCustomCloseButton(dialogHolder, callback) {
  const dialogWidget = dialogHolder.dialog('widget');
  const customCloseButtonTitle = t(
    appName,
    'Close the current dialog and return to the view '
      + 'which was active before this dialog had been opened. '
      + 'If the current view shows a `Back\' button, then intentionally '
      + 'clicking the close-button (THIS button) should just be '
      + 'equivalent to clicking the `Back\' button');
  const customCloseButton = $('<button class="customCloseButton customDialogHeaderButton" title="' + customCloseButtonTitle + '"></button>');
  customCloseButton.button({
    label: 'x',
    icons: { primary: 'ui-icon-closethick', secondary: null },
    text: false,
  });
  dialogWidget.find('.ui-dialog-titlebar').append(customCloseButton);
  customCloseButton.cafevTooltip({ placement: 'auto' });

  customCloseButton.off('click');
  customCloseButton.on('click', function(event) {
    if (typeof callback === 'function') {
      callback(event, dialogHolder);
    } else {
      dialogHolder.dialog('close');
    }
    return false;
  });
};

export {
  dialogToBackButton as toBackButton,
  dialogCustomCloseButton as customCloseButton,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

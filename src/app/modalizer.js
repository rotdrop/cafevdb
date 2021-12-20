/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, $ } from './globals.js';

/**
 * Open one invisible modal dialog in order to have a persistent
 * overlay for a group of dialogs. Note that the overlay blocking the
 * rest of the UI is the standard modal overlay of jquery-ui. This
 * dialog just has zero width and is just an anchor.
 *
 * @param {bool} open TBD.
 *
 * @returns {bool|jQuery}
 */
const modalizer = function(open) {
  const modalizer = $('#cafevdb-modalizer');
  if (open) {
    if (modalizer.length > 0) {
      $('body').addClass('cafevdb-modalizer');
      return modalizer;
    }
    const dialogHolder = $('<div id="cafevdb-modalizer" class="cafevdb-modalizer"></div>');
    $('body').append(dialogHolder);
    dialogHolder.cafevDialog({
      title: '',
      position: {
        my: 'top left',
        at: 'top-100% left-100%',
        of: window,
      },
      width: '0px',
      height: '0px',
      modal: true,
      closeOnEscape: false,
      dialogClass: 'transparent no-close zero-size cafevdb-modalizer',
      resizable: false,
      open() {
        // This one must be ours.
        globalState.dialogOverlay = $('.ui-widget-overlay:last');
        $('body').addClass('cafevdb-modalizer');
      },
      close() {
        globalState.dialogOverlay = false;
        dialogHolder.dialog('close');
        dialogHolder.dialog('destroy').remove();
        $('body').removeClass('cafevdb-modalizer');
      },
    });
    return dialogHolder;
  } else {
    if (modalizer.length <= 0) {
      $('body').removeClass('cafevdb-modalizer');
      return true;
    }
    const overlayIndex = parseInt(modalizer.dialog('widget').css('z-index'));
    console.debug('overlay index: ', overlayIndex);
    let numDialogs = 0;
    $('.ui-dialog.ui-widget').each(function(index) {
      const $this = $(this);
      const thisIndex = $this.data('z-index') || parseInt($this.css('z-index'));
      console.debug('that index: ', thisIndex);
      if (thisIndex >= overlayIndex) {
        ++numDialogs;
      }
    });

    console.debug('num dialogs open: ', numDialogs);
    if (numDialogs > 1) {
      // one is the modalizer itself, of course.
      return modalizer;
    }

    modalizer.dialog('close');
    $('body').removeClass('cafevdb-modalizer');

    return true;
  }
};

export default modalizer;

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

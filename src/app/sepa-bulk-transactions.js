/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { webRoot, appName, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Page from './page.js';
import * as Email from './email.js';
import * as Dialogs from './dialogs.js';
import * as PHPMyEdit from './pme.js';
import fileDownload from './file-download.js';

require('sepa-bulk-transactions.scss');

const ready = function(container, resizeCB) {

  // sanitize
  container = PHPMyEdit.container(container);

  container
    .on('click', 'table.pme-main tr.bulk-transaction.first td', function(event) {
      event.stopImmediatePropagation();
      $(this).closest('tr.bulk-transaction.first').toggleClass('following-hidden');
      return false;
    });

  container
    .off('click', '.debit-note-actions a.announce')
    .on('click', '.debit-note-actions a.announce', function(event) {
      const self = $(this);
      const post = self.data('post');

      Email.emailFormPopup($.param(post), true, false);

      return false;
    });

  container
    .off('click', '.bulk-transaction-actions a.download')
    .on('click', '.bulk-transaction-actions a.download', function(event) {
      const self = $(this);

      CAFEVDB.modalizer(true);
      Page.busyIcon(true);

      const clearBusyState = function() {
        console.info('CLEANUP');
        CAFEVDB.modalizer(false);
        Page.busyIcon(false);
        return true;
      };

      fileDownload(
        'finance/sepa/bulk-transactions/export',
        self.data('post'),
        {
          done: clearBusyState,
          errorMessage(data, url) {
            return t(appName, 'Unable to download debit notes.');
          },
          fail: clearBusyState,
        });

      return false;
    });

  resizeCB();
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {

    const container = PHPMyEdit.container();

    if (!container.hasClass('sepa-bulk-transactions')) {
      return;
    }

    ready(container, function() {});
  });

};

export {
  documentReady,
  ready
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

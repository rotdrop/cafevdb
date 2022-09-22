/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import $ from './jquery.js';
import { appName } from './app-info.js';
import * as CAFEVDB from './cafevdb.js';
import * as Page from './page.js';
import * as Email from './email.js';
import * as PHPMyEdit from './pme.js';
import fileDownload from './file-download.js';
import modalizer from './modalizer.js';

require('sepa-bulk-transactions.scss');

const ready = function(container, resizeCB) {

  // sanitize
  const $container = PHPMyEdit.container(container);

  $container
    .on('contextmenu', 'table.pme-main tr.bulk-transaction.first td', function(event) {
      if (event.ctrlKey) {
        return; // let the user see the normal context menu
      }
      const $row = $(this).closest('tr.bulk-transaction.first');
      event.stopImmediatePropagation();
      $row.toggleClass('following-hidden');
      $row.find('input.expanded-marker').val($row.hasClass('following-hidden') ? 0 : 1);
      return false;
    });

  $container
    .on('click', '.bulk-transaction-actions a.announce', function(event) {
      const self = $(this);
      const post = self.data('post');

      Email.emailFormPopup($.param(post), true, false);

      return false;
    });

  $container
    .on('click', '.bulk-transaction-actions a.download', function(event) {
      const self = $(this);

      modalizer(true);
      Page.busyIcon(true);

      const clearBusyState = function() {
        console.info('CLEANUP');
        modalizer(false);
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

    const $container = PHPMyEdit.container();

    if (!$container.hasClass('sepa-bulk-transactions')) {
      return;
    }

    ready($container, function() {});
  });

};

export {
  documentReady,
  ready,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

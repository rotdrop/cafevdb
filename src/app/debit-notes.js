/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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

const ready = function(container, resizeCB) {

  // sanitize
  container = PHPMyEdit.container(container);

  container
    .off('click', '.debit-note-actions a.announce')
    .on('click', '.debit-note-actions a.announce', function(event) {
      const self = $(this);
      const post = self.data('post');

      Email.emailFormPopup($.param(post), true, false);

      return false;
    });

  container
    .off('click', '.debit-note-actions a.download')
    .on('click', '.debit-note-actions a.download', function(event) {
      const self = $(this);

      CAFEVDB.modalizer(true);
      Page.busyIcon(true);

      const clearBusyState = function() {
        CAFEVDB.modalizer(false);
        Page.busyIcon(false);
        return true;
      };

      const post = self.data('post');
      post.DownloadCookie = CAFEVDB.makeId();

      const action = OC.filePath(appName, 'ajax/finance', 'debit-note-download.php');
      $.fileDownload(action, {
        httpMethod: 'POST',
        data: post,
        cookieName: 'debit_note_download',
        cookieValue: post.DownloadCookie,
        cookiePath: webRoot,
        successCallback() {
          clearBusyState();
        },
        failCallback(responseHtml, url, error) {
          Dialogs.alert(
            t(appName, 'Unable to download debit notes:')
              + ' '
              + responseHtml,
            t(appName, 'Error'),
            clearBusyState, true, true);
        },
      });

      return false;
    });

  if (typeof resizeCB === 'function') {
    resizeCB();
  }
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {

    if ($('div#cafevdb-page-body.debit-notes').length > 0) {
      ready();
    }
  });

};

export { documentReady, ready };

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

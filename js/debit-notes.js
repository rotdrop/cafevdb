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

var CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  'use strict';
  var DebitNotes = function() {};

  DebitNotes.ready = function(container, resizeCB) {
    var self = this;

    // sanitize
    container = PHPMYEDIT.container(container);

    container.
      off('click', '.debit-note-actions a.announce').
      on('click', '.debit-note-actions a.announce', function(event) {
      var self = $(this);
      var post = self.data('post');

      // call email dialog
      CAFEVDB.Email.emailFormPopup($.param(post), true, false);

      return false;
    });

    container.
      off('click', '.debit-note-actions a.download').
      on('click', '.debit-note-actions a.download', function(event) {
      var self = $(this);

      CAFEVDB.modalizer(true);
      CAFEVDB.Page.busyIcon(true);

      var clearBusyState = function() {
        CAFEVDB.modalizer(false);
        CAFEVDB.Page.busyIcon(false);
        return true;
      };

      var post = self.data('post');
      post['DownloadCookie'] = CAFEVDB.makeId();

      var action = OC.filePath('cafevdb', 'ajax/finance', 'debit-note-download.php');
      $.fileDownload(action, {
        httpMethod: 'POST',
        data: post,
        cookieName: 'debit_note_download',
        cookieValue: post['DownloadCookie'],
        cookiePath: oc_webroot,
        successCallback: function() {
          clearBusyState()
        },
        failCallback: function(responseHtml, url, error) {
          OC.dialogs.alert(t('cafevdb', 'Unable to download debit notes:')+
                           ' '+
                           responseHtml,
                           t('cafevdb', 'Error'),
                           clearBusyState, true, true);
        }
      });

      return false;
    });

    if (typeof resizeCB === 'function') {
      resizeCB();
    }
  };

  CAFEVDB.DebitNotes = DebitNotes;

})(window, jQuery, CAFEVDB);

$(function(){

  CAFEVDB.addReadyCallback(function() {

    if ($('div#cafevdb-page-body.debit-notes').length > 0) {
      CAFEVDB.DebitNotes.ready();
    }
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

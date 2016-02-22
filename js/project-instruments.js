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
  var ProjectInstruments = function() {};

  ProjectInstruments.recordRegistered = function() {
      var post = optionValues[1];
  };

  ProjectInstruments.ready = function(selector) {
    var self = this;
    var container = PHPMYEDIT.container(selector);

    var transferButton = container.find('input.transfer-registered-instruments');
    if (transferButton.lenght <= 0) {
      return;
    }
    transferButton.off('click').on('click', function(event) {
      var post = $(this.form).serialize();

      OC.Notification.hide(function() {
        $.post(OC.filePath('cafevdb', 'ajax/instruments', 'adjustInstrumentation.php'),
               post,
               function (data) {
                 if (!CAFEVDB.ajaxErrorHandler(data, [])) {
                   // do nothing
                 } else if (data.data.message != '') {
                   OC.Notification.show(data.data.message);
                 }
                 setTimeout(function() {
                   OC.Notification.hide(function() {
                     // Anyhow, reload and see what happens. Hit
                     // either the save and continue or the reload
                     // button.
                     PHPMYEDIT.triggerSubmit('morechange', container)
                     || PHPMYEDIT.triggerSubmit('reloadview', container)
                     || PHPMYEDIT.triggerSubmit('reloadlist', container);
                   });
                 }, 5000);
               });
      });

      return false;
    });
  };

  CAFEVDB.ProjectInstruments = ProjectInstruments;

})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  PHPMYEDIT.addTableLoadCallback('ProjectInstruments',
                                 {
                                   callback: function(selector, parameters, resizeCB) {
                                     if (parameters.reason != 'dialogOpen') {
                                       resizeCB();
                                       return;
                                     }
                                     CAFEVDB.ProjectInstruments.ready(selector);
                                     resizeCB();
                                   },
                                   context: CAFEVDB.ProjectInstruments,
                                   parameters: []
                                 });

  CAFEVDB.addReadyCallback(function() {
    var container = $(PHPMYEDIT.defaultSelector+'.project-instruments');
    if (container.length <= 0) {
      return; // not for us
    }
    CAFEVDB.ProjectInstruments.ready();
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

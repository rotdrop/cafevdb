/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  var ProjectInstrumentation = function() {};

  ProjectInstrumentation.recordRegistered = function() {
    const post = optionValues[1];
  };

  ProjectInstrumentation.ready = function(selector) {
    const self = this;
    const container = PHPMYEDIT.container(selector);

    const transferButton = container.find('input.transfer-registered-instruments');
    if (transferButton.lenght <= 0) {
      return;
    }
    transferButton.off('click').on('click', function(event) {
      const post = $(this.form).serialize();

      CAFEVDB.Notification.hide(function() {
        $.post(
          OC.generateUrl('/apps/cafevdb/instrumentation/adjust'),
          post)
          .fail(function(xhr, status, errorThrown) {
            CAFEVDB.Ajax.handleError(xhr, status, errorThrown);
            // Anyhow, reload and see what happens. Hit
            // either the save and continue or the reload
            // button.
            PHPMYEDIT.triggerSubmit('morechange', container)
              || PHPMYEDIT.triggerSubmit('reloadview', container)
              || PHPMYEDIT.triggerSubmit('reloadlist', container);
          })
          .done(function(data) {
            var timeout = 0;
            if (data.message != '') {
              CAFEVDB.Notification.show(data.message);
              timeout = 5000;
            }
            setTimeout(function() {
              CAFEVDB.Notification.hide(function() {
                // Anyhow, reload and see what happens. Hit
                // either the save and continue or the reload
                // button.
                PHPMYEDIT.triggerSubmit('morechange', container)
                  || PHPMYEDIT.triggerSubmit('reloadview', container)
                  || PHPMYEDIT.triggerSubmit('reloadlist', container);
              });
            }, timeout);
          });
      });

      return false;
    });
  };

  CAFEVDB.ProjectInstrumentation = ProjectInstrumentation;

})(window, jQuery, CAFEVDB);

$(function(){

  PHPMYEDIT.addTableLoadCallback('project-instrumentation',
                                 {
                                   callback: function(selector, parameters, resizeCB) {
                                     if (parameters.reason != 'dialogOpen') {
                                       resizeCB();
                                       return;
                                     }
                                     CAFEVDB.ProjectInstrumentation.ready(selector);
                                     resizeCB();
                                   },
                                   context: CAFEVDB.ProjectInstrumentation,
                                   parameters: []
                                 });

  CAFEVDB.addReadyCallback(function() {
    const container = $(PHPMYEDIT.defaultSelector+'.project-instrumentation');
    if (container.length <= 0) {
      return; // not for us
    }
    CAFEVDB.ProjectInstrumentation.ready();
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

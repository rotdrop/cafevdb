/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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

  var Musicians = function() {};

  Musicians.addMusicians = function(form, post) {
    var projectId = form.find('input[name="ProjectId"]').val();
    var projectName = form.find('input[name="ProjectName"]').val();
    if (typeof post == 'undefined') {
      post = form.serialize();
    }

    // Open the change-musician dialog with the newly
    // added musician in case of success.
    $.post(OC.filePath('cafevdb', 'ajax/instrumentation', 'add-musicians.php'),
           post,
           function(data) {
             if (!CAFEVDB.ajaxErrorHandler(data, [
               'musicians'
             ])) {
               // Load the underlying base-view in any case in order to go "back" ...
               CAFEVDB.Instrumentation.loadDetailedInstrumentation(form);
               return false;
             }
             if (data.data.musicians.length == 1) {
               // open single person change dialog
               var musician = data.data.musicians[0];
               //alert('data: '+CAFEVDB.print_r(musician, true));                       
               CAFEVDB.Instrumentation.loadDetailedInstrumentation(
                 form,
                 undefined,
                 function() {
                   CAFEVDB.Instrumentation.personalRecordDialog(
                     musician.instrumentationId,
                     {
                       ProjectId: projectId,
                       ProjectName: projectName,
                       InitialValue: 'Change',
                       modified: true
                     });
                 });
             } else {
               // load the instrumentation table, initially restricted to the new musicians
               CAFEVDB.Instrumentation.loadDetailedInstrumentation(form, data.data.musicians);
             }
             return false;
           }
          );
    return false;
  };


  Musicians.ready = function(container) {
    var self = this;

    if (typeof container == 'undefined') {
      container = $('body');
    }

    container.find('input.register-musician').off('click').
      on('click', function(event) {

      var form = container.find('form.pme-form');
      var projectId = form.find('input[name="ProjectId"]').val();
      var projectName = form.find('input[name="ProjectName"]').val();
      var musicianId = $(this).data('musician-id');

      self.addMusicians(form, {
        'ProjectId': projectId,
        'ProjectName': projectName,
        'MusicianId': musicianId
      });
      return false;
    });

    //container.find('input.pme-bulkcommit').addClass('formsubmit');
    container.find('input.pme-bulkcommit').
      addClass('pme-custom').prop('disabled', false).
      off('click').on('click', function(event) {

      var form = container.find('form.pme-form');
      self.addMusicians(form);
      return false;
    });
  };

  CAFEVDB.Musicians = Musicians;

})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  CAFEVDB.addReadyCallback(function() {
    if ($('div#cafevdb-page-body.musicians').length > 0) {
      CAFEVDB.Musicians.ready();
    }
  });
  
});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

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

    container.find('input.phone-number').
      not('.pme-filter').
      off('blur').
      on('blur', function(event) {
      event.stopImmediatePropagation();

      var form = container.find('form.pme-form');
      var phones = form.find('input.phone-number');
      var post = form.serialize();
      phones.prop('disabled', true);
      $.post(OC.filePath('cafevdb', 'ajax/musicians', 'validatephone.php'),
             post,
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [ 'message',
                                                     'mobilePhone',
                                                     'fixedLinePhone' ],
                                             function() {
                      phones.prop('disabled', false);
                    })) {
                 return false;
               }
               // inject the sanitized value into their proper input fields
               form.find('input[name$="MobilePhone"]').val(data.data.mobilePhone);
               form.find('input[name$="FixedLinePhone"]').val(data.data.fixedLinePhone);
               if (data.data.message != '') {
                 OC.dialogs.alert(data.data.message,
                                  t('cafevdb', 'Phone Number Validation'),
                                  function() {
                                    phones.prop('disabled', false);
                                  }, true, true);
                 CAFEVDB.debugPopup(data);
               } else {
                 phones.prop('disabled', false);
               }
               return false;
             });

      return false;
    });

    container.find('input.email').
      not('.pme-filter').
      off('blur').
      on('blur', function(event) {

      event.stopImmediatePropagation();

      var form = container.find('form.pme-form');
      var email = form.find('input.email');
      var post = form.serialize();
      email.prop('disabled', true);
      $.post(OC.filePath('cafevdb', 'ajax/musicians', 'validateemail.php'),
             post,
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [ 'message',
                                                     'email' ],
                                             function() {
                      email.prop('disabled', false);
                    })) {
                 return false;
               }
               // inject the sanitized value into their proper input fields
               form.find('input[name$="Email"]').val(data.data.email);
               if (data.data.message != '') {
                 OC.dialogs.alert(data.data.message,
                                  t('cafevdb', 'Email Validation'),
                                  function() {
                                    email.prop('disabled', false);
                                  }, true, true);
                 CAFEVDB.debugPopup(data);
               } else {
                 email.prop('disabled', false);
               }
               return false;
             });

      return false;
    });

    container.find('input.musician-name.add-musician').
      off('blur').
      on('blur', function(event) {
      event.stopImmediatePropagation();

      var form = container.find('form.pme-form');
      $.post(OC.filePath('cafevdb', 'ajax/musicians', 'validate.php'),
             form.serialize(),
             function (data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [ 'message' ])) {
                 return false;
               }
               if (data.data.message != '') {
                 container.find('input.musician-name').prop('disabled', true);
                 OC.dialogs.alert(data.data.message,
                                  t('cafevdb', 'Possible Duplicate!'),
                                  function() {
                                    container.find('input.musician-name').prop('disabled', false);
                                  }, true, true);
               }
               return false;
             });

      return false;
    });

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

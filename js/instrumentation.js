/**Orchestra member, musicion and project management application.
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
  var Instrumentation = function() {};
  
  Instrumentation.actions = function(select, container) {};

  
  /**Trigger server-side validation and fetch the result.
   *
   * @param container jQuery object for the curren active
   * form-container (i.e. the div the form is wrapped into)
   * 
   * @param selectMusicianInstrument The select box with the list of
   * the musicians arguments.
   *
   * @param ajaxURL The URL to the script that actually validates the
   * data.
   * 
   * @param finanlizeCB Callback called at the end, before submitting
   * the current form to the servre.
   *
   * @note Would perhaps be snappier to only submit the form to the
   * server if something changed. However, the validation is triggered
   * by a change event. So what.
   */
  Instrumentation.validateInstrumentChoices = function(container,
                                                       selectMusicianInstrument,
                                                       ajaxScript,
                                                       finalizeCB) {
    var projectId = container.find('input[name="ProjectId"]').val();
    var recordId = container.find('input[name="PME_sys_rec"]').val();
    
    OC.Notification.hide(function () {
      $.post(ajaxScript,
             {
               projectId: projectId,
               recordId: recordId,
               instrumentValues: selectMusicianInstrument.val()
             },
             function (data) {
               var rqData;
               var timeout = 3000;
               if (data.status == 'success') {
                 rqData = data.data;
                 if (rqData.notice != '') {
                   timeout = 10000;
                 }
                 OC.Notification.show(rqData.message+''+rqData.notice);
               } else if (data.status == 'error') {
                 rqData = data.data;
                 timeout = 6000;
                 if (rqData.error != 'exception') {
                   if (rqData.message == '') {
                     rqData.message = t('cafevdb', 'Unkown Error');
                   }
                   OC.Notification.show(rqData.message);
                 } else {
                   OC.dialogs.alert(rqData.exception+rqData.trace,
                                    t('cafevdb', 'Caught a PHP Exception'),
                                    null, true);
                 }
                 if (rqData.debug != '') {
                   OC.dialogs.alert(rqData.debug, t('cafevdb', 'Debug Information'), null, true);
                 }
               }
               // Oops. Perhaps only submit on success.
               setTimeout(function() {
                 OC.Notification.hide(finalizeCB);
               }, timeout);

               return false;
             }, 'json');
    });
  };

  Instrumentation.ready = function(selector) {
    var selector = PHPMYEDIT.selector(selector);
    var container = PHPMYEDIT.container(selector);

    var self = this;

    // Enable the controls, in order not to bloat SQL queries these PME
    // fields are flagged virtual which disables all controls initially.
    var selectMusicianInstruments = container.find('#add-instruments-block select');
    var selectProjectInstrument = container.find('select[class$="-instrument"]');

    $('#add-instruments-button').hide();
    $('#add-instruments-block div.chosen-container').show();    
    selectMusicianInstruments.removeProp('disabled');
    selectMusicianInstruments.trigger('chosen:updated');

    selectProjectInstrument.on('change', function(event) {
      event.preventDefault();

      selectMusicianInstruments.prop('disabled', true);
      selectMusicianInstruments.trigger('chosen:updated');

      self.validateInstrumentChoices(
        container, selectProjectInstrument,
        OC.filePath('cafevdb', 'ajax/instrumentation', 'change-project-instrument.php'),
        function () {

          // Reenable, other the value will not be submitted
          selectMusicianInstruments.prop('disabled', false);
          selectMusicianInstruments.trigger('chosen:updated');
          
          //No need to submit, the validation-script does not alter DB
          //data.
          //PHPMYEDIT.submitOuterForm(selector);
        });

      return false;
    });


    selectMusicianInstruments.on('change', function(event) {
      event.preventDefault();

      selectProjectInstrument.prop('disabled', true);
      selectProjectInstrument.trigger('chosen:updated');

      self.validateInstrumentChoices(
        container, selectMusicianInstruments,
        OC.filePath('cafevdb', 'ajax/instrumentation', 'change-musician-instruments.php'),
        function () {

          // Reenable, otherwise the value will not be submitted
          selectProjectInstrument.prop('disabled', false);
          selectProjectInstrument.trigger('chosen:updated');
          
          // submit the form with the "right" button,
          // i.e. save any possible changes already
          // entered by the user. The form-submit
          // will then also reload with an up to date
          // list of instruments
          //PHPMYEDIT.triggerSubmit('morechange', container);
          PHPMYEDIT.submitOuterForm(selector);
        });

      return false;
    });
    
    if (false) {
      var addInstrumentsButton = container.find('#add-instruments-button')
      addInstrumentsButton.off('click');
      addInstrumentsButton.click(function(event) {
        event.preventDefault();

        self.openAddInstrumentsDialog(container);

        return false;
      });
    }

  };

  CAFEVDB.Instrumentation = Instrumentation;
  
})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  PHPMYEDIT.addTableLoadCallback('BriefInstrumentation',
                                 {
                                   callback: function(selector, resizeCB) {
                                     CAFEVDB.exportMenu(selector);
                                     $(selector).find('input.pme-email').addClass('formsubmit');
                                     CAFEVDB.SepaDebitMandate.popupInit(selector);
                                     this.ready(selector);
                                     resizeCB();
                                   },
                                   context: CAFEVDB.Instrumentation,
                                   parameters: []
                                 });

  PHPMYEDIT.addTableLoadCallback('DetailedInstrumentation',
                                 {
                                   callback: function(selector, resizeCB) {
                                     var container = $(selector);
                                     CAFEVDB.exportMenu(selector);
                                     container.find('input.pme-email').addClass('formsubmit');
                                     container.find('span.photo').click(function(event) {
                                       event.preventDefault();
                                       CAFEVDB.Photo.popup(this);
                                       return false;
                                     });
                                     if (container.find('#file_upload_target').length > 0) {
                                       var idField = $(selector).find('input[name="PME_data_MusikerId"]');
                                       var recordId = -1;
                                       if (idField.length > 0) {
                                         recordId = idField.val();
                                       }
                                       CAFEVDB.Photo.ready(recordId, resizeCB);
                                     } else {
                                       container.find('span.photo').imagesLoaded(resizeCB);
                                     }
                                   },
                                   context: CAFEVDB,
                                   parameters: []
                                 });

  PHPMYEDIT.addTableLoadCallback('BulkAddMusicians',
                                 {
                                   callback: function(selector, resizeCB) {
                                     CAFEVDB.exportMenu(selector);
                                     $(selector).find('input.pme-email').addClass('formsubmit');
                                     CAFEVDB.SepaDebitMandate.popupInit(selector);
                                     this.ready(selector);
                                     resizeCB();
                                   },
                                   context: CAFEVDB.Instrumentation,
                                   parameters: []
                                 });

  $(PHPMYEDIT.defaultSelector+' input.pme-email').addClass('formsubmit');
  $(PHPMYEDIT.defaultSelector+' input.pme-bulkcommit').addClass('formsubmit');

  CAFEVDB.Instrumentation.ready();

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

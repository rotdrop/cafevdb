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
  var Instrumentation = function() {};

  /**Open a dialog in order to edit the personal reccords of one
   * musician.
   * 
   * @param record The record id. This is either the Id from the
   * Musiker table or the Id from the Besetzungen table, depending on
   * what else is passed in the second argument
   * 
   * @param options Object. Additional option. In particular ProjectId
   * and ProjectName are honored, and the optiones IntialValue and
   * ReloadValue which should be one of 'View' or 'Change' (though
   * 'Delete' should also work).
   *
   */
  Instrumentation.personalRecordDialog = function(record, options) {

    if (typeof options == 'undefined') {
      options = {
        InitialValue: 'View',
        ReloadValue: 'View',
        ProjectId: -1
      };
    }
    if (typeof options.InitialValue == 'undefined') {
      options.InitialValue = 'View';
    }
    if (typeof options.ReloadValue == 'undefined') {
      options.ReloadValue = options.InitialValue;
    }
    if (typeof options.Project != 'undefined') {
      options.ProjectName = options.Project;
    } else if (typeof options.ProjectName != 'undefined') {
      options.Project = options.ProjectName;
    }

    var tableOptions = {
      ProjectId: -1,
      ProjectName: '',
      Project: '',
      AmbientContainerSelector: PHPMYEDIT.selector(),
      DialogHolderCSSId: 'personal-record-dialog', 
      headervisibility: CAFEVDB.headervisibility,
      // Now special options for the dialog popup
      InitialViewOperation: options.InitialValue == 'View',
      InitialName: 'PME_sys_operation',
      InitialValue: 'View',
      ReloadName: 'PME_sys_operation',
      ReloadValue: 'View',
      PME_sys_operation: options.ReloadValue + '?PME_sys_rec='+record,
      PME_sys_rec: record,
      ModalDialog: true,
      modified: false
    };

    // Merge remaining options in.
    tableOptions = $.extend(tableOptions, options);

    if (options.ProjectId >= 0) {
      tableOptions.Table = options.projectName+'View';
      tableOptions.Template = 'detailed-instrumenation'
      tableOptions.DisplayClass = 'DetailedInstrumentation';
    } else {
      tableOptions.Table = 'Musiker';
      tableOptions.Template = 'all-musicians';
      tableOptions.DisplayClass = 'Musicians';
    }

    //alert('options: '+CAFEVDB.print_r(tableOptions, true));
    
    PHPMYEDIT.tableDialogOpen(tableOptions);
  };
  
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
                                                       finalizeCB,
                                                       errorCB) {
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
                 // Oops. Perhaps only submit on success.
                 finalizeCB();

                 rqData = data.data;
                 if (rqData.notice != '') {
                   timeout = 10000;
                 }
                 var info = rqData.message + ' ' + rqData.notice;
                 info = info.trim();
                 if (info != '') {
                   OC.Notification.show(info);
                   setTimeout(function() {
                     OC.Notification.hide();
                   }, timeout);
                 }
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
                 if (typeof errorCB == 'function' && typeof rqData.instruments != 'undefined') {
                   errorCB(rqData.instruments);
                 }
                 setTimeout(function() {
                   OC.Notification.hide();
                 }, timeout);
               }

               return false;
             }, 'json');
    });
  };

  Instrumentation.ready = function(selector) {
    selector = PHPMYEDIT.selector(selector);
    var container = PHPMYEDIT.container(selector);

    var self = this;

    // Enable the controls, in order not to bloat SQL queries these PME
    // fields are flagged virtual which disables all controls initially.
    var selectMusicianInstruments = container.find('select.musician-instruments');
    var selectProjectInstrument = container.find('select.project-instrument');

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

          // Reenable, otherwise the value will not be submitted
          selectMusicianInstruments.prop('disabled', false);
          selectMusicianInstruments.trigger('chosen:updated');
          
          //No need to submit, the validation-script does not alter DB
          //data.
          PHPMYEDIT.submitOuterForm(selector);
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
          PHPMYEDIT.submitOuterForm(selector);
        },
        function(oldInstruments) {
          var i;
          var selected = {};
          for (i = 0; i < oldInstruments.length; ++i) {
            selected[oldInstruments[i]] = true;
          }
          selectMusicianInstruments.find('option').each(function(idx) {
            var self = $(this);
            if (typeof selected[self.val()] != 'undefined') {
              self.prop('selected', true);
            } else {
              self.prop('selected', false);
            }
          });
          // Reenable, otherwise the value will not be submitted
          selectProjectInstrument.prop('disabled', false);
          selectProjectInstrument.trigger('chosen:updated');
          selectMusicianInstruments.trigger('chosen:updated');
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

    container.find('form.pme-form input.pme-add').
      addClass('pme-custom').prop('disabled', false).
      off('click').on('click', function(event) {

      //alert('click');

      var form = $(this.form);

      var inputTweak = [
        { name: "Template", value: "add-musicians" },
        { name: "Table", value: "Musiker" },
        { name: "DisplayClass", value: "Musicians" },
        { name: "ClassArguments[0]", value: "1" }
      ];

      var idx;
      for (idx = 0; idx < inputTweak.length; ++idx) {
        var name = inputTweak[idx].name;
        var value = inputTweak[idx].value;
        form.find('input[name="'+name+'"]').remove();
        form.append('<input type="hidden" name="'+name+'" value="'+value+'"/>"');
      }

      PHPMYEDIT.pseudoSubmit(form, $(this), PHPMYEDIT.selector());

      return false;
    });
  };

  CAFEVDB.Instrumentation = Instrumentation;
  
})(window, jQuery, CAFEVDB);

$(document).ready(function(){

  PHPMYEDIT.addTableLoadCallback('BriefInstrumentation', {
    callback: function(selector, resizeCB) {
      CAFEVDB.exportMenu(selector);
      CAFEVDB.SepaDebitMandate.popupInit(selector);
      this.ready(selector);
      resizeCB();
    },
    context: CAFEVDB.Instrumentation,
    parameters: []
  });

  PHPMYEDIT.addTableLoadCallback('DetailedInstrumentation', {
    callback: function(selector, resizeCB) {
      var container = $(selector);
      CAFEVDB.exportMenu(selector);
      CAFEVDB.SepaDebitMandate.popupInit(selector);
      this.ready(selector);

      container.find('div.photo, #cafevdb_inline_image_wrapper').on('click', 'img', function(event) {
        event.preventDefault();
        CAFEVDB.Photo.popup(this);
        return false;
      });

      $(':button.musician-instrument-insurance').click(function(event) {
        event.preventDefault();
        var values = $(this).attr('name');
        
        CAFEVDB.formSubmit(OC.linkTo('cafevdb', 'index.php'), values, 'post');
        
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
        container.find('div.photo, span.photo').imagesLoaded(resizeCB);
      }
    },
    context: CAFEVDB.Instrumentation,
    parameters: []
  });

  PHPMYEDIT.addTableLoadCallback('BulkAddMusicians', {
    callback: function(selector, resizeCB) {
      CAFEVDB.exportMenu(selector);
      CAFEVDB.SepaDebitMandate.popupInit(selector);
      this.ready(selector);
      resizeCB();
    },
    context: CAFEVDB.Instrumentation,
    parameters: []
  });

  CAFEVDB.Instrumentation.ready();

});

// Local Variables: ***
// js-indent-level: 2 ***
// js3-indent-level: 2 ***
// js3-label-indent-offset: -2 ***
// End: ***

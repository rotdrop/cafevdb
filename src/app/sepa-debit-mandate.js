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

import { globalState } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as Page from './page.js';
import * as Email from './email.js';
import * as PHPMyEdit from './pme.js';

globalState.SepaDebitMandate = {
  projectId: -1,
  projectName: '',
  musicianId: -1,
  musicianName: '',
  mandateId: -1,
  mandateReference: '',
  instantValidation: true,
  validationRunning:_false,
};

/**
 * Initialize the mess with contents. The "mess" is a dialog window
 * with the input form element for the bank account data.
 *
 * @param data JSON response with the fields data.status,
 *                 data.data.contents,
 *                 data.data.message is place in an error-popup if status != 'success'
 *                 data.data.debug. data.data.debug is placed
 *                 inside the '#debug' div.
 */
const init = function(data, reloadCB) {
  var self = SepaDebitMandate;

  if (!Ajax.validateResponse(data, [
    'contents',
    'projectId', 'projectName',
    'musicianId', 'musicianName',
    'mandateId', 'mandateReference'
  ])) {
    return false;
  }

  if (typeof reloadCB != 'function') {
    reloadCB = function() {};
  }

  self.projectId = data.data.projectId;
  self.projectName = data.data.projectName;
  self.musicianId = data.data.musicianId;
  self.musicianName = data.data.musicianName;
  self.mandateId = data.data.mandateId;
  self.mandateReference = data.data.mandateReference;

  Dialogs.debugPopup(data);

  var popup = $(data.data.contents)
  var mandateForm = popup.find('#sepa-debit-mandate-form');
  self.instantValidation = mandateForm.find('#sepa-validation-toggle').prop('checked');
  var lastUsedDate = mandateForm.find('input.lastUsedDate');

  popup.cafevDialog({
    position: { my: "middle top+50%",
                at: "middle bottom",
                of: "#controls" },
    width : 550,
    height: "auto",
    modal: true,
    resizable: false,
    closeOnEscape: false,
    dialogClass: 'no-close',
    buttons: [
      {
        'class': 'change',
        id: 'sepaMandateChange',
        text: t('cafevdb', 'Change'),
        title: t('cafevdb', 'Change the SEPA mandate. Note that the SEPA mandate-reference is automatically computed and cannot be changed.'),
        click: function() {
          // enable the form, disable the change button
          $(this).dialog("widget").find('button.save').attr("disabled", !self.instantValidation);
          $(this).dialog("widget").find('button.apply').attr("disabled", !self.instantValidation);
          $(this).dialog("widget").find('button.delete').attr("disabled", false);
          $(this).dialog("widget").find('button.change').attr("disabled", true);
          if (lastUsedDate.val().trim() == '') {
            mandateForm.find('input.bankAccount').attr("disabled", false);
            mandateForm.find('input.mandateDate').attr("disabled", false);
            lastUsedDate.attr("disabled", false);
          }
          $.fn.cafevTooltip.remove(); // clean up left-over balloons
        }
      },
      {
        'class': 'save',
        id: 'sepaMandateSave',
        text: t('cafevdb', 'Save'),
        title: t('cafevdb', 'Close the form and save the data in the underlying data-base storage.'),
        click: function() {
          var dlg = this;
          self.store(function () {
            $('#sepa-debit-mandate-'+self.musicianId+'-'+self.projectId).val(self.mandateReference);
            $(dlg).dialog('close');
            reloadCB();
          });
        }
      },
      {
        'class': 'apply',
        text: t('cafevdb', 'Apply'),
        title: t('cafevdb', 'Save the data in the underlying data-base storage. Keep the form open.'),
        click: function(event) {
          var dlg = this;
          self.store(function () {
            $('#sepa-debit-mandate-'+self.musicianId+'-'+self.projectId).val(self.mandateReference);
            // Disable everything and enable the change button
            // If we are about to display an existing mandate, first
            // disable all inputs and leave only the "close" and
            // "change" buttons enabled, and the lastUsed date.
            $(dlg).dialog("widget").find('button.save').attr("disabled", true);
            $(dlg).dialog("widget").find('button.apply').attr("disabled", true);
            $(dlg).dialog("widget").find('button.delete').attr("disabled", true);
            $(dlg).dialog("widget").find('button.change').attr("disabled", false);
            mandateForm.find('input.bankAccount').attr("disabled", true);
            mandateForm.find('input.mandateDate').attr("disabled", true);
            mandateForm.find('input.lastUsedDate').attr("disabled", true);
            $.fn.cafevTooltip.remove(); // clean up left-over balloons
            reloadCB();
          });
        }
      },
      {
        'class': 'delete',
        text: t('cafevdb', 'Delete'),
        title: t('cafevdb', 'Delete this mandate from the data-base. Normally, this should only be done in case of desinformation or misunderstanding. Use with care.'),
        click: function() {
          var dlg = this;
          self.delete(function () {
            $('#sepa-debit-mandate-'+self.musicianId+'-'+self.projectId).val(t('cafevdb', 'SEPA Debit Mandate'));
            $(dlg).dialog('close');
            reloadCB();
          });
        }
      },
      {
        'class': 'close',
        text: t('cafevdb', 'Close'),
        title: t('cafevdb', 'Discard all filled-in data and close the form. Note that this will not undo any changes previously stored in the data-base by pressing the `Apply\' button.'),
        click: function() {
          $(this).dialog('close');
          //$('form.pme-form').submit();
        }
      }
    ],
    open: function(){
      var dlg = $(this);
      var widget = dlg.dialog('widget');
      //$.fn.cafevTooltip.remove(); // remove tooltip form "open-button"
      widget.find('button.close').focus();

      var buttons = {
        save: widget.find('button.save'),
        apply: widget.find('button.apply'),
        delete: widget.find('button.delete'),
        change: widget.find('button.change')
      };

      if (self.mandateId > 0) {
        // If we are about to display an existing mandate, first
        // disable all inputs and leave only the "close" and
        // "change" buttons enabled.
        buttons.save.attr("disabled", true);
        buttons.apply.attr("disabled", true);
        buttons['delete'].attr("disabled", true);
        mandateForm.find('input.bankAccount').attr("disabled", true);
        mandateForm.find('input.mandateDate').attr("disabled", true);
        mandateForm.find('input.lastUsedDate').attr("disabled", true);
      } else {
        buttons.save.attr("disabled", !self.instantValidation);
        buttons.apply.attr("disabled", !self.instantValidation);
        buttons.change.attr("disabled", true);
      }

      widget.find('button, input, label, [class*="tooltip"]').cafevTooltip({placement:'auto bottom'});

      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }

      var expiredDiv = dlg.find('#mandate-expired-notice.active');
      if (expiredDiv.length > 0) {
        var notice = expiredDiv.attr('title');
        if (!notice) {
          notice = expiredDiv.attr('data-original-title');
        }
        if (notice) {
          OC.dialogs.alert('<div class="sepa-mandate-expire-notice">'+
                           notice+
                           '</div>',
                           t('cafevdb', 'Debit Mandate Expired'),
                           undefined,
                           true, true);
        }
      }

      $('#sepa-debit-mandate-form input[class$="Date"]').datepicker({
        dateFormat : 'dd.mm.yy', // this is 4-digit year
        minDate: '01.01.1990',
        beforeShow: function(input) {
          $(input).off('blur');
        },
        onSelect: function(dateText, inst) {
          var input = $(this);
          input.on('blur', function(event) {
            self.validate.call(this, event, function(lock) {
              input.prop('readonly', lock);
            });
          });
          input.focus();
          input.trigger('blur');
        }
      });

      var validateInput = function(event) {
        var input = $(this);
        if (input.prop('readonly')) {
          return;
        }
        self.validate.call(this, event, function(lock) {
          // disable the text field during validation
          input.prop('readonly', lock);
          // disable save and apply during validation
          if (lock) {
            buttons.save.attr('disabled', true);
            buttons.apply.attr('disabled', true);
          } else {
            buttons.save.attr("disabled", !self.instantValidation);
            buttons.apply.attr("disabled", !self.instantValidation);
          }
        });
      };

      mandateForm.find('input[type="text"]').on('blur', validateInput);

      // Switch off for IBAN in order not to annoy Martina
      if (!self.instantValidation) {
        mandateForm.find('#bankAccountIBAN').off('blur');
      }

      mandateForm.find('#sepa-validation-toggle').on('change', function(event) {
        event.preventDefault();

        self.instantValidation = $(this).prop('checked');
        // Switch off for IBAN in order not to annoy Martina

        mandateForm.find('#bankAccountIBAN').off('blur');
        if (self.instantValidation) {
          mandateForm.find('#bankAccountIBAN').on('blur', validateInput);
          mandateForm.find('#bankAccountIBAN').trigger('blur');
        }
        buttons.save.attr("disabled", !self.instantValidation);
        buttons.apply.attr("disabled", !self.instantValidation);

        return false;
      });

      mandateForm.find('#debit-mandate-orchestra-member').
        off('change').
        on('change', validateInput);

    },
    close: function(event, ui) {
      $.fn.cafevTooltip.remove();
      $('#sepa-debit-mandate-dialog').dialog('close');
      $(this).dialog('destroy').remove();
    }
  });
  return false;
};

// Store the form data. We assume that validation already has been
// done
const store = function(callbackOk) {
  var dialogId = '#sepa-debit-mandate-dialog';

  $('div.statusmessage').hide();
  $('span.statusmessage').hide();

  // "submit" the entire form
  var post = $('#sepa-debit-mandate-form').serialize();

  $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-store.php'),
         post,
         function (data) {
           if (!Ajax.validateResponse(data, [ 'message' ])) {
             return false;
           }
	   $(dialogId+' #msg').html(data.data.message);
	   $(dialogId+' #msg').show();
           if (data.status == "success") {
             callbackOk();
             return true;
           } else {
             return false;
           }
         });
};

// Delete a mandate
const deleteMandate = function(callbackOk) {
  var dialogId = '#sepa-debit-mandate-dialog';

  $('div.statusmessage').hide();
  $('span.statusmessage').hide();

  // "submit" the entire form
  var post = $('#sepa-debit-mandate-form').serialize();

  $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-delete.php'),
         post,
         function (data) {
           if (!Ajax.validateResponse(data, [ 'message' ])) {
             return false;
           }
	   $(dialogId+' #msg').html(data.data.message);
	   $(dialogId+' #msg').show();
           if (data.status == "success") {
             callbackOk();
             return true;
           } else {
             return false;
           }

         });
};

/**Validate version for our popup-dialog. */
const validate = function(event, validateLockCB) {
  var element = this;
  var dialogId = '#sepa-debit-mandate-dialog';

  if (typeof validateLockCB == 'undefined') {
    validateLockCB = function(lock) {};
  }

  var validateLock = function() {
    validateLockCB(true)
  };

  var validateUnlock = function() {
    validateLockCB(false)
  };

  event.preventDefault();
  $('div.statusmessage').hide();
  $('span.statusmessage').hide();

  // we "submit" the entire form in order to do some automatic
  // fill-in in checks for the bank accounts.
  var changed = $(this).attr('name');
  var post;
  post = $('#sepa-debit-mandate-form').serialize();
  post += "&"+$.param( { 'changed': changed } );

  // until end of validation
  validateLock();

  $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-settings.php'),
         post,
         function (data) {
           if (!Ajax.validateResponse(data,
                                              [ 'suggestions', 'message' ],
                                              validateUnlock)) {
             if (data.data.suggestions !== '') {
               var hints = t('cafevdb', 'Suggested alternatives based on common human mis-transcriptions:')
                   + ' '
                   + data.data.suggestions
                   + '. '
                   + t('cafevdb', 'Please do not accept these alternatives lightly!');
	       $(dialogId+' #suggestions').html(hints);
             }
             // One special case: if the user has submitted an IBAN
             // and the BLZ appeared to be valid after all checks,
             // then inject it into the form. Seems to be a common
             // case, more or less.
             if (data.data.blz) {
               $('input.bankAccountBLZ').val(data.data.blz);
             }

	     $(dialogId+' #msg').html(data.data.message);
	     $(dialogId+' #msg').show();
             if ($(dialogId+' #suggestions').html() !== '') {
	       $(dialogId+' #suggestions').show();
             }
             return false;
           }
           if (changed === 'orchestraMember') {
             $('input[name="MandateProjectId"]').val(data.data.mandateProjectId);
             $('input[name="MandateProjectName"]').val(data.data.mandateProjectName);
             $('input[name="mandateReference"]').val(data.data.reference);
             $('legend.mandateCaption .reference').html(data.data.reference);
           }
           if (data.data.value) {
             $(element).val(data.data.value);
           }
           if (data.data.iban) {
             $('input.bankAccountIBAN').val(data.data.iban);
           }
           if (data.data.blz) {
             $('input.bankAccountBLZ').val(data.data.blz);
           }
           if (data.data.bic) {
             $('input.bankAccountBIC').val(data.data.bic);
           }
	   $(dialogId+' #msg').html(data.data.message);
	   $(dialogId+' #msg').show();
           if (data.data.suggestions !== '') {
             var hints = t('cafevdb', 'Suggested alternatives based on common human mis-transcriptions:')
                 + ' '
                 + data.data.suggestions
                 + '. '
                 + t('cafevdb', 'Please do not accept these alternatives lightly!');
	     $(dialogId+' #suggestions').html(hints);
	     $(dialogId+' #suggestions').show();
           } else {
	     $(dialogId+' #suggestions').html('');
	     $(dialogId+' #suggestions').hide();
           }

           validateUnlock();

           return true;

         }, 'json');
};

/**
 * Validate version for the PME dialog.
 *
 * Note: the pme-dialog is disabled, but for the date, for the time
 * being.
 */
const validatePME = function(event, validateLockCB) {
  var $element = $(this);

  if ($element.prop('readonly')) {
    return false;
  }

  if (typeof validateLockCB == 'undefined') {
    validateLockCB = function(lock, validateOk) {};
  }

  var validateLock = function() {
    globalState.SepaDebitMandate.validationRunning = true;
    validateLockCB(true, null)
  };

  var validateUnlock = function() {
    validateLockCB(false, true)
    globalState.SepaDebitMandate.validationRunning = false;
  };

  var validateErrorUnlock = function() {
    validateLockCB(false, false)
    globalState.SepaDebitMandate.validationRunning = false;
    $('div.oc-dialog-content').ocdialog('close');
    $('div.oc-dialog-content').ocdialog('destroy').remove();
    $.fn.cafevTooltip.hide();
  };


  event.preventDefault();

  // we use the same Ajax validation script; we remap the form
  // elements. We need
  //
  // MusicianId
  // ProjectId
  // mandateReference
  // sequenceType
  // bankAccountOwner
  // bankAccountIBAN
  // bankAccountBLZ
  // bankAccountBIC
  // mandateDate
  // lastUsedDate
  var inputMapping = {
    PME_data_lastUsedDate: 'lastUsedDate',
    PME_data_mandateDate: 'mandateDate',
    PME_data_bankAccountOwner: 'bankAccountOwner',
    PME_data_IBAN: 'bankAccountIBAN',
    PME_data_BIC: 'bankAccountBIC',
    PME_data_BLZ: 'bankAccountBLZ'
  };
  var changed = $element.attr('name');
  changed = inputMapping[changed];

  var projectElem = $('[name="PME_data_project_id"]');
  var projectId;
  if (!projectElem.is('input')) {
    projectElem = projectElem.find('option[selected="selected"]');
  }
  projectId = projectElem.val();

  var mandateData = {
    mandateReference: $('input[name="PME_data_mandate_reference"]').val(),
    mandateDate: $('input[name="PME_data_mandate_date"]').val(),
    bankAccountOwner: $('input[name="PME_data_bank_account_owner"]').val(),
    lastUsedDate: $('input[name="PME_data_last_used_date"]').val(),
    MusicianId:  $('select[name="PME_data_musician_id"] option[selected="selected"]').val(),
    ProjectId:  projectId,
    MandateProjectId:  projectId,
    bankAccountIBAN: $('input[name="PME_data_iban"]').val(),
    bankAccountBIC: $('input[name="PME_data_bic"]').val(),
    bankAccountBLZ: $('input[name="PME_data_blz"]').val(),
    changed: changed
  };

  // until end of validation
  validateLock();

  var post = $.param(mandateData);
  $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-settings.php'),
         post,
         function (data) {
           // hack ...
           if (typeof data.data != 'undefined' &&
               data.data.message && data.data.suggestions  && data.data.suggestions !== '') {
             var hints = t('cafevdb', 'Suggested alternatives based on common human mis-transcriptions:')
                 + ' '
                 + data.data.suggestions
                 + '. '
                 + t('cafevdb', 'Please do not accept these alternatives lightly!');
             data.data.message += hints;
           }
           if (!Ajax.validateResponse(data,
                                              [ 'suggestions', 'message' ],
                                              validateErrorUnlock)) {
             if (data.data.blz) {
               $('input.bankAccountBLZ').val(data.data.blz);
             }
             return false;
           }

           $('#cafevdb-page-debug').html(data.data.message);
           $('#cafevdb-page-debug').show();
           if (data.data.value) {
             $element.val(data.data.value);
           }
           if (data.data.iban) {
             $('input[name="PME_data_iban"]').val(data.data.iban);
           }
           if (data.data.bic) {
             $('input[name="PME_data_bic"]').val(data.data.bic);
           }
           if (data.data.blz) {
             $('input[name="PME_data_blz"]').val(data.data.blz);
           }

           validateUnlock();

           return true;
         }, 'json');
  return false;
};

const popupInit = function(selector) {
  var self = this;

  var containerSel = PHPMyEdit.selector(selector);
  var container = PHPMyEdit.container(containerSel);
  var pmeReload = container.find('form.pme-form input.pme-reload').first();
  container.find(':button.sepa-debit-mandate').
    off('click').
    on('click', function(event) {
      event.preventDefault();
      if (container.find('#sepa-debit-mandate-dialog').dialog('isOpen') == true) {
        container.find('#sepa-debit-mandate-dialog').dialog('close').remove();
      } else {
        // We store the values in the data attribute.
        var values = $(this).data('debitMandate');
        //alert('data: ' + CAFEVDB.print_r(values, true));
        //alert('data: '+(typeof values.MandateExpired));
        $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-mandate.php'),
               values, function(data) {
                 self.init(data, function() {
                   if (pmeReload.length > 0) {
                     pmeReload.trigger('click');
                   }
                 });
               },
               'json');
      }
      return false;
    });
};

const insuranceReady = function(selector) {
  var sdm = this;

  var containerSel = PHPMyEdit.selector(selector);
  var container = PHPMyEdit.container(containerSel);

  container.find('input.pme-debit-note').
    off('click').
    on('click', sdm.exportHandler);

  return true;
};

const exportHandler = function(event) {
  var self = $(this);
  var form = $(this.form);

  event.stopImmediatePropagation(); // why?

  CAFEVDB.modalizer(true);
  Page.busyIcon(true);

  var clearBusyState = function() {
    CAFEVDB.modalizer(false);
    Page.busyIcon(false);
    console.log('after init');
    return true;
  };

  var formPost = form.serialize();
  $.post(OC.filePath('cafevdb', 'ajax/finance', 'sepa-debit-export.php'),
         formPost,
         function (data) {
           if (!Ajax.validateResponse(data, [ 'message', 'debitnote' ],
                                              clearBusyState)) {
             return false;
           }

           // Everything worked out, from here we now trigger the
           // download and the mail dialog

           console.log('debitnote', data.data.debitnote);

           var debitNote = data.data.debitnote;

           // custom post
           var postItems = [
             'requesttoken',
             'ProjectId',
             'ProjectName',
             'Table',
             'MusicianId'
           ];
           var post = {};
           for(var i = 0; i < postItems.length; ++i) {
             post[postItems[i]] = form.find('input[name="'+postItems[i]+'"]').val();
           };
           post['DebitNoteId']    = debitNote.Id;
           post['DownloadCookie'] = CAFEVDB.makeId();
           post['EmailTemplate']  = data.data.emailtemplate;

           var action = OC.filePath('cafevdb', 'ajax/finance', 'debit-note-download.php');

           $.fileDownload(action, {
             httpMethod: 'POST',
             data: post,
             cookieName: 'debit_note_download',
             cookieValue: post['DownloadCookie'],
             cookiePath: oc_webroot,
             successCallback: function() {
               // if insurance, then also epxort the invoice PDFs
               if (debitNote.Job === 'insurance') {
                 var action = OC.filePath('cafevdb', 'ajax/insurance', 'instrument-insurance-export.php');
                 $.fileDownload(action, {
                   httpMethod: 'POST',
                   data: formPost+'&'+'DownloadCookie='+post['DownloadCookie'],
                   cookieName: 'insurance_invoice_download',
                   cookieValue: post['DownloadCookie'],
                   cookiePath: oc_webroot,
                   successCallback: function() {
                     Email.emailFormPopup($.param(post), true, false, clearBusyState);
                   },
                   failCallback: function(responseHtml, url, error) {
                     OC.dialogs.alert(t('cafevdb', 'Unable to export insurance overviews:')+
                                      ' '+
                                      responseHtml,
                                      t('cafevdb', 'Error'),
                                      clearBusyState,
                                      true, true);
                   }
                 });
               } else {
                 Email.emailFormPopup($.param(post), true, false, clearBusyState);
               }
             },
             failCallback: function(responseHtml, url, error) {
               OC.dialogs.alert(t('cafevdb', 'Unable to export debit notes:')+
                                ' '+
                                responseHtml,
                                t('cafevdb', 'Error'),
                                clearBusyState, true, true);
             }
           });

           return true;
         });

  return false;
};

const ready = function(selector) {
  var sdm = this;
  var self = this;

  var containerSel = PHPMyEdit.selector(selector);
  var container = PHPMyEdit.container(containerSel);

  // bail out if not for us.
  var form = container.find('form.pme-form');
  var dbTable;
  dbTable = form.find('input[value="InstrumentInsurance"]');
  if (dbTable.length > 0) {
    return self.insuranceReady(selector);
  }

  var directDebitChooser = container.find('select.pme-debit-note-job');
  directDebitChooser.chosen({
    disable_search: true,
    inherit_select_classes:true,
    allow_single_deselect:true
  });
  directDebitChooser.
    off('change').
    on('change', function(event) {
      var self = $(this);
      // not much to be done ...
      var selected = self.find(':selected').val();
      directDebitChooser.find('option[value="'+selected+'"]').prop('selected', true);
      directDebitChooser.trigger('chosen:updated');
      if (selected === 'amount') {
        directDebitChooser.switchClass('predefined', 'custom');
      } else {
        directDebitChooser.switchClass('custom', 'predefined');
      }
      $.fn.cafevTooltip.remove();
      return false;
    });

  $.each(["debit-note-amount", "debit-note-subject"],
         function(index, classValue) {

           container.find('#pme-debit-note-job-up input.'+classValue).
             off('blur').
             on('blur', function(event) {
               var self = $(this);
               container.find('#pme-debit-note-job-down input.'+classValue).val(self.val());
               return false;
             });

           container.find('#pme-debit-note-job-down input.'+classValue).
             off('blur').
             on('blur', function(event) {
               var self = $(this);
               container.find('#pme-debit-note-job-up input.'+classValue).val(self.val());
               return false;
             });
         });

  dbTable = form.find('input[value="SepaDebitMandates"]');
  if (dbTable.length == 0) {
    return true;
  }
  var table = form.find('table[summary="SepaDebitMandates"]');

  var validateInput = function(event) {
    var input = $(this);
    self.validatePME.call(this, event, function(lock) {
      input.prop('readonly', lock);
    });
  };

  table.find('input[type="text"]').not('tr.pme-filter input').
    off('blur').
    on('blur', validateInput);

  var submitSel = 'input.pme-save,input.pme-apply,input.pme-more';
  var submitActive = false;
  form.
    off('click', submitSel).
    on('click', submitSel, function(event) {
      var button = $(this);
      if (submitActive) {
        button.blur();
        return false;
      }

      // allow delete button, validation makes no sense here
      if (button.attr('name') === PHPMyEdit.sys('savedelete')) {
        return true;
      }

      submitActive = true;

      var inputs = table.find('input[type="text"]');

      $.fn.cafevTooltip.hide();
      inputs.prop('readonly', true);
      button.blur();

      self.validatePME.call({ name: 'PME_data_IBAN' }, event, function(lock, validateOk) {
        if (lock) {
          inputs.prop('readonly', true);
        } else {
          submitActive = false;
          button.blur();
          inputs.prop('readonly', false);
          if (validateOk) {
            // submit the form ...
            form.off('click', submitSel);
            button.trigger('click');
          }
        }
      });

      return false;
    });

  CAFEVDB.exportMenu(containerSel);

  table.find('input.sepadate').datepicker({
    dateFormat : 'dd.mm.yy', // this is 4-digit year
    minDate: '01.01.1990',
    beforeShow: function(input) {
      $(input).unbind('blur');
    },
    onSelect: function(dateText, inst) {
      $(this).on('blur', validateInput);
      $(this).focus();
      $(this).trigger('blur');
    }
  });

  container.find('input.pme-debit-note').
    off('click').
    on('click', sdm.exportHandler);

  return true;
};

const documentReady = function() {

  PHPMyEdit.addTableLoadCallback('sepa-debit-mandates',
                                 {
                                   callback: function(selector, parameters, resizeCB) {
                                     this.ready(selector);
                                     resizeCB();
                                     //alert("Here I am: "+selector);
                                   },
                                   context: globalState.SepaDebitMandate,
                                   parameters: []
                                 });

  CAFEVDB.addReadyCallback(function() {
    ready(PHPMyEdit.defaultSelector);
    popupInit(PHPMyEdit.defaultSelector);
  });

};

export {
  ready,
  documentReady,
  popupInit,
  insuranceReady,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

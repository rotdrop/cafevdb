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

import { globalState, appName, webRoot, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as Page from './page.js';
import * as Email from './email.js';
import { data as pmeData } from './pme-selectors.js';
import * as PHPMyEdit from './pme.js';
import generateUrl from './generate-url.js';

require('sepa-debit-mandate.css');

const SepaDebitMandate = globalState.SepaDebitMandate = {
  projectId: -1,
  projectName: '',
  musicianId: -1,
  musicianName: '',
  mandateId: -1,
  mandateReference: '',
  instantValidation: true,
  validationRunning: false,
};

/**
 * Initialize the mess with contents. The "mess" is a dialog window
 * with the input form element for the bank account data.
 *
 * @param {Object} data JSON response with the fields data.status,
 *   data.data.contents, data.data.message is place in an error-popup
 *   if status != 'success' data.data.debug. data.data.debug is placed
 *   inside the '#debug' div.
 *
 * @param {Function} reloadCB TBD.
 *
 * @returns {bool}
 */
const mandatesInit = function(data, reloadCB) {
  const self = SepaDebitMandate;

  if (!Ajax.validateResponse(data, [
    'contents',
    'projectId', 'projectName',
    'musicianId', 'musicianName',
    'mandateId', 'mandateReference',
  ])) {
    return false;
  }

  if (typeof reloadCB !== 'function') {
    reloadCB = function() {};
  }

  self.projectId = data.data.projectId;
  self.projectName = data.data.projectName;
  self.musicianId = data.data.musicianId;
  self.musicianName = data.data.musicianName;
  self.mandateId = data.data.mandateId;
  self.mandateReference = data.data.mandateReference;

  Dialogs.debugPopup(data);

  const popup = $(data.data.contents);
  const mandateForm = popup.find('#sepa-debit-mandate-form');
  self.instantValidation = mandateForm.find('#sepa-validation-toggle').prop('checked');
  const lastUsedDate = mandateForm.find('input.lastUsedDate');

  popup.cafevDialog({
    position: {
      my: 'middle top+50%',
      at: 'middle bottom',
      of: '#controls',
    },
    width: 550,
    height: 'auto',
    modal: true,
    resizable: false,
    closeOnEscape: false,
    dialogClass: 'no-close',
    buttons: [
      {
        class: 'change',
        id: 'sepaMandateChange',
        text: t(appName, 'Change'),
        title: t(appName, 'Change the SEPA mandate. Note that the SEPA mandate-reference is automatically computed and cannot be changed.'),
        click() {
          // enable the form, disable the change button
          $(this).dialog('widget').find('button.save').prop('disabled', !self.instantValidation);
          $(this).dialog('widget').find('button.apply').prop('disabled', !self.instantValidation);
          $(this).dialog('widget').find('button.delete').prop('disabled', false);
          $(this).dialog('widget').find('button.change').prop('disabled', true);
          if (lastUsedDate.val().trim() === '') {
            mandateForm.find('input.bankAccount').prop('disabled', false);
            mandateForm.find('input.mandateDate').prop('disabled', false);
            lastUsedDate.prop('disabled', false);
          }
          $.fn.cafevTooltip.remove(); // clean up left-over balloons
        },
      },
      {
        class: 'save',
        id: 'sepaMandateSave',
        text: t(appName, 'Save'),
        title: t(appName, 'Close the form and save the data in the underlying data-base storage.'),
        click() {
          const dlg = this;
          mandateStore(function() {
            $('#sepa-debit-mandate-' + self.musicianId + '-' + self.projectId).val(self.mandateReference);
            $(dlg).dialog('close');
            reloadCB();
          });
        },
      },
      {
        class: 'apply',
        text: t(appName, 'Apply'),
        title: t(appName, 'Save the data in the underlying data-base storage. Keep the form open.'),
        click(event) {
          const dlg = this;
          mandateStore(function() {
            $('#sepa-debit-mandate-' + self.musicianId + '-' + self.projectId).val(self.mandateReference);
            // Disable everything and enable the change button
            // If we are about to display an existing mandate, first
            // disable all inputs and leave only the "close" and
            // "change" buttons enabled, and the lastUsed date.
            $(dlg).dialog('widget').find('button.save').prop('disabled', true);
            $(dlg).dialog('widget').find('button.apply').prop('disabled', true);
            $(dlg).dialog('widget').find('button.delete').prop('disabled', true);
            $(dlg).dialog('widget').find('button.change').prop('disabled', false);
            mandateForm.find('input.bankAccount').prop('disabled', true);
            mandateForm.find('input.mandateDate').prop('disabled', true);
            mandateForm.find('input.lastUsedDate').prop('disabled', true);
            $.fn.cafevTooltip.remove(); // clean up left-over balloons
            reloadCB();
          });
        },
      },
      {
        class: 'delete',
        text: t(appName, 'Delete'),
        title: t(appName, 'Delete this mandate from the data-base. Normally, this should only be done in case of desinformation or misunderstanding. Use with care.'),
        click() {
          const dlg = this;
          mandateDelete(function() {
            $('#sepa-debit-mandate-' + self.musicianId + '-' + self.projectId).val(t(appName, 'SEPA Debit Mandate'));
            $(dlg).dialog('close');
            reloadCB();
          });
        },
      },
      {
        class: 'close',
        text: t(appName, 'Close'),
        title: t(appName, 'Discard all filled-in data and close the form. Note that this will not undo any changes previously stored in the data-base by pressing the `Apply\' button.'),
        click() {
          $(this).dialog('close');
          // $('form.pme-form').submit();
        },
      },
    ],
    open() {
      const dlg = $(this);
      const widget = dlg.dialog('widget');
      // $.fn.cafevTooltip.remove(); // remove tooltip form "open-button"
      widget.find('button.close').focus();

      const buttons = {
        save: widget.find('button.save'),
        apply: widget.find('button.apply'),
        delete: widget.find('button.delete'),
        change: widget.find('button.change'),
      };

      if (self.mandateId > 0) {
        // If we are about to display an existing mandate, first
        // disable all inputs and leave only the "close" and
        // "change" buttons enabled.
        buttons.save.prop('disabled', true);
        buttons.apply.prop('disabled', true);
        buttons.delete.prop('disabled', true);
        mandateForm.find('input.bankAccount').prop('disabled', true);
        mandateForm.find('input.mandateDate').prop('disabled', true);
        mandateForm.find('input.lastUsedDate').prop('disabled', true);
      } else {
        buttons.save.prop('disabled', !self.instantValidation);
        buttons.apply.prop('disabled', !self.instantValidation);
        buttons.change.prop('disabled', true);
      }

      widget.find('button, input, label, [class*="tooltip"]').cafevTooltip({ placement: 'auto bottom' });

      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }

      const expiredDiv = dlg.find('#mandate-expired-notice.active');
      if (expiredDiv.length > 0) {
        let notice = expiredDiv.attr('title');
        if (!notice) {
          notice = expiredDiv.attr('data-original-title');
        }
        if (notice) {
          OC.dialogs.alert(
            '<div class="sepa-mandate-expire-notice">'
              + notice
              + '</div>',
            t(appName, 'Debit Mandate Expired'),
            undefined,
            true, true);
        }
      }

      $('#sepa-debit-mandate-form input[class$="Date"]').datepicker({
        dateFormat: 'dd.mm.yy', // this is 4-digit year
        minDate: '01.01.1990',
        beforeShow(input) {
          $(input).off('blur');
        },
        onSelect(dateText, inst) {
          const input = $(this);
          input.on('blur', function(event) {
            mandateValidate.call(this, event, function(lock) {
              input.prop('readonly', lock);
            });
          });
          input.focus();
          input.trigger('blur');
        },
      });

      const validateInput = function(event) {
        const input = $(this);
        if (input.prop('readonly')) {
          return;
        }
        mandateValidate.call(this, event, function(lock) {
          // disable the text field during validation
          input.prop('readonly', lock);
          // disable save and apply during validation
          if (lock) {
            buttons.save.prop('disabled', true);
            buttons.apply.prop('disabled', true);
          } else {
            buttons.save.prop('disabled', !self.instantValidation);
            buttons.apply.prop('disabled', !self.instantValidation);
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
        buttons.save.prop('disabled', !self.instantValidation);
        buttons.apply.prop('disabled', !self.instantValidation);

        return false;
      });

      mandateForm.find('#debit-mandate-orchestra-member')
        .off('change')
        .on('change', validateInput);

    },
    close(event, ui) {
      $.fn.cafevTooltip.remove();
      $('#sepa-debit-mandate-dialog').dialog('close');
      $(this).dialog('destroy').remove();
    },
  });
  return false;
};

// Store the form data. We assume that validation already has been
// done
const mandateStore = function(callbackOk) {
  const dialogId = '#sepa-debit-mandate-dialog';

  $('div.statusmessage').hide();
  $('span.statusmessage').hide();

  // "submit" the entire form
  const post = $('#sepa-debit-mandate-form').serialize();

  $.post(
    OC.filePath(appName, 'ajax/finance', 'sepa-debit-store.php'),
    post,
    function(data) {
      if (!Ajax.validateResponse(data, ['message'])) {
        return false;
      }
      $(dialogId + ' #msg').html(data.data.message);
      $(dialogId + ' #msg').show();
      if (data.status === 'success') {
        callbackOk();
        return true;
      } else {
        return false;
      }
    });
};

// Delete a mandate
const mandateDelete = function(callbackOk) {
  const dialogId = '#sepa-debit-mandate-dialog';

  $('div.statusmessage').hide();
  $('span.statusmessage').hide();

  // "submit" the entire form
  const post = $('#sepa-debit-mandate-form').serialize();

  $.post(
    OC.filePath(appName, 'ajax/finance', 'sepa-debit-delete.php'),
    post,
    function(data) {
      if (!Ajax.validateResponse(data, ['message'])) {
        return false;
      }
      $(dialogId + ' #msg').html(data.data.message);
      $(dialogId + ' #msg').show();
      if (data.status === 'success') {
        callbackOk();
        return true;
      } else {
        return false;
      }
    });
};

/**
 * Validate version for our popup-dialog.
 *
 * @param {Object} event TBD.
 *
 * @param {Function} validateLockCB TBD.
 */
const mandateValidate = function(event, validateLockCB) {
  const element = this;
  const dialogId = '#sepa-debit-mandate-dialog';

  if (typeof validateLockCB === 'undefined') {
    validateLockCB = function(lock) {};
  }

  const validateLock = function() {
    validateLockCB(true);
  };

  const validateUnlock = function() {
    validateLockCB(false);
  };

  event.preventDefault();
  $('div.statusmessage').hide();
  $('span.statusmessage').hide();

  // we "submit" the entire form in order to do some automatic
  // fill-in in checks for the bank accounts.
  const changed = $(this).attr('name');
  let post;
  post = $('#sepa-debit-mandate-form').serialize();
  post += '&' + $.param({ changed });

  // until end of validation
  validateLock();

  $.post(
    generateUrl('finance/sepa/debit-notes/mandates/validate'),
    post,
    function(data) {
      if (!Ajax.validateResponse(
        data,
        ['suggestions', 'message'],
        validateUnlock)) {
        if (data.data.suggestions !== '') {
          const hints = t(appName, 'Suggested alternatives based on common human mis-transcriptions:')
              + ' '
              + data.data.suggestions
              + '. '
              + t(appName, 'Please do not accept these alternatives lightly!');
          $(dialogId + ' #suggestions').html(hints);
        }
        // One special case: if the user has submitted an IBAN
        // and the BLZ appeared to be valid after all checks,
        // then inject it into the form. Seems to be a common
        // case, more or less.
        if (data.data.blz) {
          $('input.bankAccountBLZ').val(data.data.blz);
        }

        $(dialogId + ' #msg').html(data.data.message);
        $(dialogId + ' #msg').show();
        if ($(dialogId + ' #suggestions').html() !== '') {
          $(dialogId + ' #suggestions').show();
        }
        return false;
      }
      if (changed === 'orchestraMember') {
        $('input[name="MandateProjectId"]').val(data.data.mandateProjectId);
        // $('input[name="MandateProjectName"]').val(data.data.mandateProjectName);
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
      $(dialogId + ' #msg').html(data.data.message);
      $(dialogId + ' #msg').show();
      if (data.data.suggestions !== '') {
        const hints = t(appName, 'Suggested alternatives based on common human mis-transcriptions:')
            + ' '
            + data.data.suggestions
            + '. '
            + t(appName, 'Please do not accept these alternatives lightly!');
        $(dialogId + ' #suggestions').html(hints);
        $(dialogId + ' #suggestions').show();
      } else {
        $(dialogId + ' #suggestions').html('');
        $(dialogId + ' #suggestions').hide();
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
 *
 * @param {Object} event TBD.
 *
 * @param {Function} validateLockCB TBD.
 *
 * @returns {bool}
 */
const mandateValidatePME = function(event, validateLockCB) {
  const $element = $(this);

  if ($element.prop('readonly')) {
    return false;
  }

  if (typeof validateLockCB === 'undefined') {
    validateLockCB = function(lock, validateOk) {};
  }

  const validateLock = function() {
    globalState.SepaDebitMandate.validationRunning = true;
    validateLockCB(true, null);
  };

  const validateUnlock = function() {
    validateLockCB(false, true);
    globalState.SepaDebitMandate.validationRunning = false;
  };

  const validateErrorUnlock = function() {
    validateLockCB(false, false);
    globalState.SepaDebitMandate.validationRunning = false;
    // $('div.oc-dialog-content').ocdialog('close');
    // $('div.oc-dialog-content').ocdialog('destroy').remove();
    $.fn.cafevTooltip.hide();
  };

  event.preventDefault();

  // we use the same Ajax validation script; we remap the form
  // elements. We need
  //
  // musicianId
  // projectId
  // mandateReference
  // sequenceType
  // bankAccountOwner
  // bankAccountIBAN
  // bankAccountBLZ
  // bankAccountBIC
  // mandateDate
  // lastUsedDate
  const inputMapping = {
    [pmeData('last_used_date')]: 'lastUsedDate',
    [pmeData('mandate_date')]: 'mandateDate',
    [pmeData('bank_account_owner')]: 'bankAccountOwner',
    [pmeData('iban')]: 'bankAccountIBAN',
    [pmeData('bic')]: 'bankAccountBIC',
    [pmeData('blz')]: 'bankAccountBLZ',
    [pmeData('Projects:id')]: 'projectId',
    [pmeData('Musicians:id')]: 'musicianId',
  };
  console.info('INPUT MAPPING', inputMapping);
  console.info('ELEMENT CHANGED', $element.attr('name'));
  let changed = $element.attr('name');
  changed = inputMapping[changed];

  // let projectElem = $('[name="' + pmeData('project_id') + '"]');
  // if (!projectElem.is('input')) {
  //   projectElem = projectElem.find('option[selected="selected"]');
  // }

  const projectElem = $('[name="' + pmeData('Projects:id') + '"]');
  // if (!projectElem.is('input')) {
  //   projectElem = projectElem.find('option[selected="selected"]');
  // }
  const projectId = projectElem.val();

  const musicianElem = $('[name="' + pmeData('Musicians:id') + '"]');
  // if (!musicianElem.is('input')) {
  //   musicianElem = musicianElem.find('option[selected="selected"]');
  // }
  const musicianId = musicianElem.val();

  const mandateData = {
    mandateReference: $('input[name="' + pmeData('mandate_reference') + '"]').val(),
    mandateDate: $('input[name="' + pmeData('mandate_date') + '"]').val(),
    bankAccountOwner: $('input[name="' + pmeData('bank_account_owner') + '"]').val(),
    lastUsedDate: $('input[name="' + pmeData('last_used_date') + '"]').val(),
    musicianId,
    projectId,
    mandateProjectId: projectId,
    bankAccountIBAN: $('input[name="' + pmeData('iban') + '"]').val(),
    bankAccountBIC: $('input[name="' + pmeData('bic') + '"]').val(),
    bankAccountBLZ: $('input[name="' + pmeData('blz') + '"]').val(),
    changed,
  };

  // until end of validation
  validateLock();

  const post = $.param(mandateData);
  $.post(generateUrl('finance/sepa/debit-notes/mandates/validate'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, validateErrorUnlock);
    })
    .done(function(data) {
      // hack ...
      if (data.message && data.suggestions && data.suggestions !== '') {
        const hints = t(appName, 'Suggested alternatives based on common human mis-transcriptions:')
            + ' '
            + data.suggestions
            + '. '
            + t(appName, 'Please do not accept these alternatives lightly!');
        data.message += hints;
      }
      if (!Ajax.validateResponse(
        data,
        ['suggestions', 'message'],
        validateErrorUnlock)) {
        if (data.blz) {
          $('input.bankAccountBLZ').val(data.blz);
        }
        return false;
      }

      $('#cafevdb-page-debug').html(data.message);
      $('#cafevdb-page-debug').show();
      if (data.value) {
        $element.val(data.value);
      }
      if (data.iban) {
        $('input[name="' + pmeData('iban') + '"]').val(data.iban);
      }
      if (data.bic) {
        $('input[name="' + pmeData('bic') + '"]').val(data.bic);
      }
      if (data.blz) {
        $('input[name="' + pmeData('blz') + '"]').val(data.blz);
      }
      if (data.owner) {
        $('input[name="' + pmeData('bank_account_owner') + '"]').val(data.owner);
      }
      if (data.reference) {
        $('input[name="' + pmeData('mandate_reference') + '"]').val(data.reference);
      }

      validateUnlock();

      return true;
    }, 'json');
  return false;
};

const mandatePopupInit = function(selector) {
  const containerSel = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(containerSel);
  const pmeReload = container.find('form.pme-form input.pme-reload').first();
  container.find(':button.sepa-debit-mandate')
    .off('click')
    .on('click', function(event) {
      event.preventDefault();
      if (container.find('#sepa-debit-mandate-dialog').dialog('isOpen') === true) {
        container.find('#sepa-debit-mandate-dialog').dialog('close').remove();
      } else {
        // We store the values in the data attribute.
        const values = $(this).data('debitMandate');
        // alert('data: ' + CAFEVDB.print_r(values, true));
        // alert('data: '+(typeof values.MandateExpired));
        $.post(
          generateUrl('finance/sepa/debit-notes/mandates/dialog'),
          values,
          function(data) {
            mandatesInit(data, function() {
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

const mandateExportHandler = function(event) {
  const form = $(this.form);

  event.stopImmediatePropagation(); // why?

  CAFEVDB.modalizer(true);
  Page.busyIcon(true);

  const clearBusyState = function() {
    CAFEVDB.modalizer(false);
    Page.busyIcon(false);
    console.log('after init');
    return true;
  };

  const formPost = form.serialize();
  $.post(
    OC.filePath(appName, 'ajax/finance', 'sepa-debit-export.php'),
    formPost,
    function(data) {
      if (!Ajax.validateResponse(
        data,
        ['message', 'debitnote'],
        clearBusyState)) {
        return false;
      }

      // Everything worked out, from here we now trigger the
      // download and the mail dialog

      console.log('debitnote', data.debitnote);

      const debitNote = data.debitnote;

      // custom post
      const postItems = [
        'requesttoken',
        'projectId',
        'projectName',
        // 'Table', ?? @TODO not needed?
        'musicianId',
      ];
      const post = {};
      for (let i = 0; i < postItems.length; ++i) {
        post[postItems[i]] = form.find('input[name="' + postItems[i] + '"]').val();
      }
      post.DebitNoteId = debitNote.Id;
      post.DownloadCookie = CAFEVDB.makeId();
      post.EmailTemplate = data.emailtemplate;

      const action = OC.filePath(appName, 'ajax/finance', 'debit-note-download.php');

      $.fileDownload(action, {
        httpMethod: 'POST',
        data: post,
        cookieName: 'debit_note_download',
        cookieValue: post.DownloadCookie,
        cookiePath: webRoot,
        successCallback() {
          // if insurance, then also epxort the invoice PDFs
          if (debitNote.Job === 'insurance') {
            const action = OC.filePath(appName, 'ajax/insurance', 'instrument-insurance-export.php');
            $.fileDownload(action, {
              httpMethod: 'POST',
              data: formPost + '&' + 'DownloadCookie=' + post.DownloadCookie,
              cookieName: 'insurance_invoice_download',
              cookieValue: post.DownloadCookie,
              cookiePath: webRoot,
              successCallback() {
                Email.emailFormPopup($.param(post), true, false, clearBusyState);
              },
              failCallback(responseHtml, url, error) {
                Dialogs.alert(
                  t(appName, 'Unable to export insurance overviews:')
                    + ' '
                    + responseHtml,
                  t(appName, 'Error'),
                  clearBusyState,
                  true, true);
              },
            });
          } else {
            Email.emailFormPopup($.param(post), true, false, clearBusyState);
          }
        },
        failCallback(responseHtml, url, error) {
          Dialogs.alert(
            t(appName, 'Unable to export debit notes:')
              + ' '
              + responseHtml,
            t(appName, 'Error'),
            clearBusyState, true, true);
        },
      });

      return true;
    });

  return false;
};

const mandateInsuranceReady = function(selector) {
  const containerSel = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(containerSel);

  container.find('input.pme-debit-note')
    .off('click')
    .on('click', mandateExportHandler);

  return true;
};

const mandateReady = function(selector) {

  const containerSel = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(containerSel);

  // bail out if not for us.
  const form = container.find('form.pme-form');
  let dbTable = form.find('input[value="InstrumentInsurance"]');
  if (dbTable.length > 0) {
    return mandateInsuranceReady(selector);
  }

  const directDebitChooser = container.find('select.pme-debit-note-job');
  directDebitChooser.chosen({
    disable_search: true,
    inherit_select_classes: true,
    allow_single_deselect: true,
  });
  directDebitChooser
    .off('change')
    .on('change', function(event) {
      const self = $(this);
      // not much to be done ...
      const selected = self.find(':selected').val();
      directDebitChooser.find('option[value="' + selected + '"]').prop('selected', true);
      directDebitChooser.trigger('chosen:updated');
      if (selected === 'amount') {
        directDebitChooser.switchClass('predefined', 'custom');
      } else {
        directDebitChooser.switchClass('custom', 'predefined');
      }
      $.fn.cafevTooltip.remove();
      return false;
    });

  $.each(
    ['debit-note-amount', 'debit-note-subject'],
    function(index, classValue) {
      container.find('#pme-debit-note-job-up input.' + classValue)
        .off('blur')
        .on('blur', function(event) {
          const self = $(this);
          container.find('#pme-debit-note-job-down input.' + classValue).val(self.val());
          return false;
        });

      container.find('#pme-debit-note-job-down input.' + classValue)
        .off('blur')
        .on('blur', function(event) {
          const self = $(this);
          container.find('#pme-debit-note-job-up input.' + classValue).val(self.val());
          return false;
        });
    });

  dbTable = form.find('input[value="SepaDebitMandates"]');
  if (dbTable.length === 0) {
    return true;
  }
  const table = form.find('table[summary="SepaDebitMandates"]');

  const validateInput = function(event) {
    const input = $(this);
    mandateValidatePME.call(this, event, function(lock) {
      input.prop('readonly', lock);
    });
  };

  table.find('input[type="text"]').not('tr.pme-filter input')
    .off('blur')
    .on('blur', validateInput);

  table.find('select').not('tr.pme-filter select')
    .off('change')
    .on('change', validateInput);

  const submitSel = 'input.pme-save,input.pme-apply,input.pme-more';
  let submitActive = false;
  form
    .off('click', submitSel)
    .on('click', submitSel, function(event) {
      const button = $(this);
      if (submitActive) {
        button.blur();
        return false;
      }

      // allow delete button, validation makes no sense here
      if (button.attr('name') === PHPMyEdit.sys('savedelete')) {
        return true;
      }

      submitActive = true;

      const inputs = table.find('input[type="text"]');

      $.fn.cafevTooltip.hide();
      inputs.prop('readonly', true);
      button.blur();

      mandateValidatePME.call({ name: pmeData('IBAN') }, event, function(lock, validateOk) {
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
    dateFormat: 'dd.mm.yy', // this is 4-digit year
    minDate: '01.01.1990',
    beforeShow(input) {
      $(input).unbind('blur');
    },
    onSelect(dateText, inst) {
      $(this).on('blur', validateInput);
      $(this).focus();
      $(this).trigger('blur');
    },
  });

  container.find('input.pme-debit-note')
    .off('click')
    .on('click', mandateExportHandler);

  return true;
};

const mandatesDocumentReady = function() {

  PHPMyEdit.addTableLoadCallback(
    'sepa-debit-mandates',
    {
      callback(selector, parameters, resizeCB) {
        mandateReady(selector);
        resizeCB();
        // alert("Here I am: "+selector);
      },
      context: globalState.SepaDebitMandate,
      parameters: [],
    });

  CAFEVDB.addReadyCallback(function() {
    mandateReady(PHPMyEdit.defaultSelector);
    mandatePopupInit(PHPMyEdit.defaultSelector);
  });

};

export {
  mandateReady as ready,
  mandatesDocumentReady as documentReady,
  mandatePopupInit as popupInit,
  mandateInsuranceReady as insuranceReady,
  mandateExportHandler as exportHandler,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

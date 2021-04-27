/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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
// import * as Dialogs from './dialogs.js';
// import * as DialogUtils from './dialog-utils.js';
import * as Page from './page.js';
import * as Email from './email.js';
import * as Notification from './notification.js';
import checkInvalidInputs from './check-invalid-inputs.js';
import { data as pmeData } from './pme-selectors.js';
import * as PHPMyEdit from './pme.js';
import generateUrl from './generate-url.js';
// import fileDownload from './file-download.js';
import pmeExportMenu from './pme-export.js';
import selectValues from './select-values.js';
import './lock-input.js';

import 'selectize';
import 'selectize/dist/css/selectize.bootstrap4.css';
// import 'selectize/dist/css/selectize.css';
require('cafevdb-selectize.css');

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('sepa-debit-mandate.css');
require('lock-input.css');

const SepaDebitMandate = globalState.SepaDebitMandate = {
  projectId: -1,
  musicianId: -1,
  mandateSequence: -1,
  mandateReference: '',
  instantValidation: true,
  validationRunning: false,
};

/**
 * Initialize the mess with contents. The "mess" is a dialog window
 * with the input form element for the bank account data.
 *
 * @param {Object} data Data returned by the AJAX call.
 *
 * @param {Function} onChangeCallback function called after
 * submitting data to the database.
 *
 * @returns {bool}
 */
const mandatesInit = function(data, onChangeCallback) {
  const self = SepaDebitMandate;

  if (typeof onChangeCallback !== 'function') {
    onChangeCallback = function() {};
  }

  const popup = $(data.contents);

  const makeSepaId = function(data) {
    popup.data('sepaId', {
      projectId: data.projectId,
      musicianId: data.musicianId,
      bankAccountSequence: data.bankAccountSequence,
      mandateSequence: data.mandateSequence,
    });
  };

  makeSepaId(data);

  const mandateFormSelector = 'form.sepa-debit-mandate-form';
  const projectSelectSelector = 'select.debitMandateProjectId';
  const allReceivablesSelector = 'input.all-receivables';
  const onlyProjectSelector = 'input.only-for-project';
  const instantValidationSelector = 'input.sepa-validation-toggle';
  const mandateRegistrationSelector = 'input.debit-mandate-registration';
  const mandateDateSelector = 'input.mandateDate';
  const accountOwnerSelector = 'input.bankAccountOwner';

  const disableButtons = function(disable) {
    const buttons = popup.data('buttons');

    if (disable === undefined) {
      disable = true;
    }

    for (const [, button] of Object.entries(buttons)) {
      button.prop('disabled', disable);
    }
  };
  const enableButtons = function() { disableButtons(false); };

  const validateInput = function(event) {
    const $input = $(this);
    if ($input.prop('readonly')) {
      return;
    }
    if ($input.hasClass('no-validation')) {
      return;
    }
    const instantValidation = popup.data('instantValidation');
    if (!instantValidation && $input.is('.bankAccountIBAN')) {
      return;
    }
    const buttons = popup.data('buttons');
    mandateValidate.call(this, event, function(lock) {
      // disable the text field during validation
      $input.prop('readonly', lock);
      // disable save and apply during validation
      if (lock) {
        buttons.save.prop('disabled', true);
        buttons.apply.prop('disabled', true);
      } else {
        buttons.save.prop('disabled', !instantValidation);
        buttons.apply.prop('disabled', !instantValidation);
      }
    });
  };

  popup.on('change', mandateFormSelector + ' ' + mandateRegistrationSelector, function(event) {
    const $self = $(this);
    const $mandateDate = $(mandateFormSelector + ' ' + mandateDateSelector);
    if ($self.prop('checked)')) {
      $mandateDate.prop('required', true);
    } else {
      $mandateDate.prop('required', false);
    }
  });

  popup.on('blur', mandateFormSelector + ' ' + 'input[type="text"]', validateInput);

  // on request disable instant validation while editing, but apply
  // and save buttons stay disabled until validation is reenabled.
  popup.data('instantValidation', popup.find(mandateFormSelector + ' ' + instantValidationSelector).prop('checked'));

  popup.on('change', mandateFormSelector + ' ' + instantValidationSelector, function(event) {
    const instantValidation = $(this).prop('checked');

    popup.data('instantValidation', instantValidation);

    if (instantValidation) {
      // force validation when re-enabled.
      popup.find('input.bankAccountIBAN').trigger('blur');
    }

    const buttons = popup.data('buttons');
    buttons.save.prop('disabled', !instantValidation);
    buttons.apply.prop('disabled', !instantValidation);

    return false;
  });

  popup.on('change', mandateFormSelector + ' ' + allReceivablesSelector, function(event) {
    const projectSelect = popup.data('fieldsets').find(projectSelectSelector);
    projectSelect.val('');
    projectSelect.trigger('change');
    if (projectSelect[0].selectize) {
      projectSelect[0].selectize.clear();
    }
    projectSelect.prop('required', false);
    return false;
  });

  popup.on('change', mandateFormSelector + ' ' + onlyProjectSelector, function(event) {
    const projectSelect = popup.data('fieldsets').find(projectSelectSelector);
    projectSelect.prop('required', true);
    return false;
  });

  popup.on('change', mandateFormSelector + ' ' + projectSelectSelector, function(envent) {
    const $self = $(this);
    const allReceivables = popup.data('fieldsets').find(allReceivablesSelector);
    const onlyProject = popup.data('fieldsets').find(onlyProjectSelector);
    allReceivables.prop('checked', $self.val() === '');
    onlyProject.prop('checked', !allReceivables.prop('checked'));
    return false;
  });

  // Render some inputs as disabled to prevent accidental overwrite
  const conservativeAllowChange = function(fieldSet) {
    fieldSet.each(function(index) {
      const $self = $(this);

      const defaultLockUnlock = {
        locked: !$self.hasClass('no-data'),
        hardLocked: !$self.hasClass('unused'),
      };

      $self.find('input[type="text"], input[type="number"]').each(function(index) {
        const $input = $(this);
        const lockOptions = $input.val() === '' ? {} : defaultLockUnlock;
        $input.lockUnlock(lockOptions);
      });

      if (!$self.hasClass('unused')) {
        $self.find('select').each(function(index) {
          const $select = $(this);
          $select.prop('disabled', $select.val() !== '');
        });
        $self.find('input[type="radio"], input[type="button"], select').prop('disabled', true);
      }
    });
  };

  const initializeDialogHandlers = function($dlg) {
    const $widget = $dlg.dialog('widget');
    const data = $dlg.data();
    const buttons = data.buttons;

    $.fn.cafevTooltip.remove();

    const mandateForm = $dlg.find(mandateFormSelector);
    const fieldsets = mandateForm.find('fieldset');
    const accountFieldset = mandateForm.find('fieldset.bank-account');
    const mandateFieldset = mandateForm.find('fieldset.debit-mandate');

    data.fieldsets = fieldsets;

    // $.fn.cafevTooltip.remove(); // remove tooltip form "open-button"
    $widget.find('button.close').focus();

    mandateFieldset.find('select.debitMandateProjectId.selectize').selectize({
      plugins: ['remove_button'],
      openOnFocus: true,
      closeAfterSelect: true,
    });
    mandateFieldset.find('select.debitMandateProjectId.chosen').chosen({
      allow_single_deselect: true,
      inherit_select_classes: true,
      disable_search_threshold: 8,
    });

    const accountOwnerInput = accountFieldset.find(accountOwnerSelector);
    accountOwnerInput.autocomplete({
      source: accountOwnerInput.data('autocomplete'),
      position: { my: 'left bottom', at: 'left top' },
      minLength: 0,
      autoFocus: true,
    });
    accountOwnerInput.on('focus', function(event) {
      const $self = $(this);
      if ($self.val() === '') {
        $self.autocomplete('search', '');
      }
    });

    conservativeAllowChange(fieldsets);

    if (!data.sepaId.bankAccountSequence > 0) {
      buttons.delete.prop('disabled', true).hide();
    } else if (!data.sepaId.mandateSequence > 0) {
      buttons.revoke.prop('disabled', true).hide();
      buttons.delete.prop('disabled', false).show();
    } else {
      buttons.revoke.prop('disabled', false).show();
      buttons.delete.prop('disabled', true).hide();
    }

    $widget.find('button, input, label, [class*="tooltip"]').cafevTooltip({ placement: 'auto bottom' });

    if (globalState.toolTipsEnabled) {
      $.fn.cafevTooltip.enable();
    } else {
      $.fn.cafevTooltip.disable();
    }

    // const expiredDiv = $dlg.find('#mandate-expired-notice.active');
    // if (expiredDiv.length > 0) {
    //   let notice = expiredDiv.attr('title');
    //   if (!notice) {
    //     notice = expiredDiv.attr('data-original-title');
    //   }
    //   if (notice) {
    //     OC.dialogs.alert(
    //       '<div class="sepa-mandate-expire-notice">'
    //         + notice
    //         + '</div>',
    //       t(appName, 'Debit Mandate Expired'),
    //       undefined,
    //       true, true);
    //   }
    // }

    $('#sepa-debit-mandate-form input[class$="Date"]').datepicker({
      dateFormat: 'dd.mm.yy', // this is 4-digit year
      minDate: '01.01.1990',
      beforeShow(input) {
        const $input = $(input);
        if ($input.prop('readonly')) {
          return false;
        }
        $input.addClass('no-validation');
        $input.lockUnlock('disable');
      },
      onSelect(dateText, inst) {
        const $input = $(this);
        $input.on('blur', function(event) {
          mandateValidate.call(this, event, function(lock) {
            $input.prop('readonly', lock);
            $input.lockUnlock('checkbox').prop('disabled', lock);
          });
          $input.removeClass('no-validation');
          $input.off('blur');
          return false;
        });
        $input.focus();
        $input.trigger('blur');
      },
      onClose(dateText, inst) {
        const $input = $(this);
        $input.removeClass('no-validation');
        $input.lockUnlock('enable');
      },
    });
  };

  popup.cafevDialog({
    position: {
      my: 'middle top+50%',
      at: 'middle top',
      of: '#app-content',
    },
    width: 'auto', // 550,
    height: 'auto',
    modal: true,
    resizable: false,
    buttons: [
      {
        class: 'reload',
        id: 'sepaMandateReload',
        text: t(appName, 'Reload'),
        title: t(appName, 'Reload the form and locks it. Unsaved changes are lost.'),
        click() {
          const $dlg = $(this);
          const data = $dlg.data();

          disableButtons();

          mandateLoad({
            sepaId: data.sepaId,
            always: enableButtons,
            done(data) {
              // redefine reload-state with response
              popup.data('instantvalidation', true);
              $dlg.html($(data.contents).html());
              initializeDialogHandlers($dlg);
            },
          });
        },
      },
      {
        class: 'save',
        id: 'sepaMandateSave',
        text: t(appName, 'Save'),
        title: t(appName, 'Close the form and save the data in the underlying data-base storage.'),
        click() {
          const $dlg = $(this);
          const $form = $dlg.find(mandateFormSelector);
          mandateStore($form, function(data) {

            makeSepaId(data);

            $('#sepa-debit-mandate-' + self.musicianId + '-' + self.projectId).val(self.mandateReference);
            $dlg.dialog('close');
            onChangeCallback();
          });
        },
      },
      {
        class: 'apply',
        text: t(appName, 'Apply'),
        title: t(appName, 'Save the data in the underlying data-base storage. Keep the form open.'),
        click(event) {
          const $dlg = $(this);
          const $form = $dlg.find(mandateFormSelector);
          const data = $dlg.data();

          disableButtons();

          mandateStore({
            form: $form,
            always: enableButtons,
            done(data) {

              // update ids
              makeSepaId(data);

              // the simplest thing is just to reload the form instead
              // of updating all form elements from JS.
              mandateLoad({
                sepaId: data.sepaId,
                done(data) {
                  popup.data('instantvalidation', true);
                  $dlg.html($(data.contents).html());
                  initializeDialogHandlers($dlg);
                },
              });
            },
          });
        },
      },
      {
        class: 'delete',
        text: t(appName, 'Delete'),
        title: t(appName, 'Delete this bank-account from the data-base. Normally, this should only be done in case of desinformation or misunderstanding. Use with care.'),
        click() {
          const $dlg = $(this);
          mandateDelete(function() {
            $('#sepa-debit-mandate-' + self.musicianId + '-' + self.projectId).val(t(appName, 'SEPA Debit Mandate'));
            $dlg.dialog('close');
            onChangeCallback();
          });
        },
      },
      {
        class: 'revoke',
        text: t(appName, 'Revoke'),
        title: t(appName, 'Revoke the debit-mandate in case the bank account changed or on request of the participant.'),
        click() {
          // const $dlg = $(this);
          alert('NOT YET');
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
      const $dlg = $(this);
      const $widget = $dlg.dialog('widget');

      // DialogUtils.toBackButton($dlg);
      // DialogUtils.fullScreenButton($dlg);

      const buttons = {
        save: $widget.find('button.save'),
        apply: $widget.find('button.apply'),
        delete: $widget.find('button.delete'),
        revoke: $widget.find('button.revoke'),
        reload: $widget.find('button.reload'),
      };

      $dlg.data('buttons', buttons);

      initializeDialogHandlers($dlg);
    },
    close(event, ui) {
      $.fn.cafevTooltip.remove();
      $(this).dialog('destroy').remove();
    },
  });
  return false;
};

const mandateLoad = function(options) {
  const defaultOptions = {
    sepaId: undefined,
    done(data) {},
    fail() {},
    always() {},
  };
  options = $.extend({}, defaultOptions, options);

  $.post(generateUrl('finance/sepa/debit-mandates/dialog'), options.sepaId)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {
        options.fail();
        options.always();
      });
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, [
        'contents',
        'projectId',
        'musicianId',
        'bankAccountSequence',
        'mandateSequence',
        'mandateReference',
      ])) {
        options.fail();
        options.always();
      } else {
        options.done(data);
        options.always();
      }
    });
};

// Store the form data. We assume that validation already has been
// done
const mandateStore = function(options) {
  const defaultOptions = {
    form: undefined,
    done(data) {},
    fail() {},
    always() {},
  };
  options = $.extend({}, defaultOptions, options);

  if (!checkInvalidInputs(options.form)) {
    options.fail();
    options.always();
    return;
  }

  // "submit" the entire form
  const post = $(options.form).serialize();

  $.post(generateUrl('finance/sepa/debit-mandates/store'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {
        options.fail();
        options.always();
      });
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, [
        'message',
        'projectId',
        'musicianId',
        'bankAccountSequence',
        'mandateSequence',
      ])) {
        options.fail();
        options.always();
      } else {
        options.done(data);
        options.always();
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

  $.post(generateUrl('finance/sepa/debit-mandates/delete'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {});
    })
    .done(function(data) {
      if (!Ajax.validateResponse(data, ['message'])) {
        return false;
      }
      $(dialogId + ' #msg').html(data.message);
      $(dialogId + ' #msg').show();
    });
};

const makeSuggestions = function(data) {
  if (data.suggestions && data.suggestions !== '') {
    if (!Array.isArray(data.suggestions)) {
      data.suggestions = [data.suggestions];
    }
    return t(appName, 'Suggested alternatives based on common human mis-transcriptions:')
      + ' '
      + data.suggestions.join(', ')
      + '. '
      + t(appName, 'Please do not accept these alternatives lightly!');
  }
  return false;
};

/**
 * Validate version for our popup-dialog.
 *
 * @param {Object} event TBD.
 *
 * @param {Function} validateLockCB TBD.
 */
const mandateValidate = function(event, validateLockCB) {
  const dialogId = '#sepa-debit-mandate-dialog';

  if (typeof validateLockCB === 'undefined') {
    validateLockCB = function(lock) {};
  }

  const validateLock = function() {
    validateLockCB(true);
  };

  const validateUnlock = function(data) {
    if (data) {
      const hints = makeSuggestions(data);
      if (hints) {
        $(dialogId + ' .suggestions').html(hints).show();
      }
    }
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

  $.post(generateUrl('finance/sepa/debit-mandates/validate'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, {
        cleanup: validateUnlock,
        preProcess(data) {
          const hints = makeSuggestions(data);
          if (hints) {
            if (data.message) {
              data.message += ' ' + hints;
            } else {
              data.message = hints;
            }
          }
        },
      });
    })
    .done(function(data) {
      if (!Ajax.validateResponse(
        data,
        ['suggestions', 'message'],
        validateUnlock)) {
        // One special case: if the user has submitted an IBAN
        // and the BLZ appeared to be valid after all checks,
        // then inject it into the form. Seems to be a common
        // case, more or less.
        if (data.blz) {
          $('input.bankAccountBLZ').val(data.blz);
        }
        $(dialogId + ' #msg').html(data.message);
        $(dialogId + ' #msg').show();
        return false;
      }
      if (changed === 'orchestraMember') {
        $('input[name="mandateProjectId"]').val(data.mandateProjectId);
        // $('input[name="MandateProjectName"]').val(data.mandateProjectName);
        $('input[name="mandateReference"]').val(data.reference);
        $('legend.mandateCaption .reference').html(data.reference);
      }
      // if (data.value) {
      //   $(element).val(data.value);
      // }
      if (data.iban) {
        $('input.bankAccountIBAN').val(data.iban);
      }
      if (data.blz) {
        $('input.bankAccountBLZ').val(data.blz);
      }
      if (data.bic) {
        $('input.bankAccountBIC').val(data.bic);
      }
      if (data.owner) {
        $('input.bankAccountOwner').val(data.owner);
      }
      if (data.reference) {
        $('span.reference').html(data.reference);
      }
      if (data.nonRecurring !== undefined) {
        if (data.nonRecurring) {
          $('#sepa-debit-mandate-dialog .debitRecurringInfo').removeClass('permanent').addClass('once');
        } else {
          $('#sepa-debit-mandate-dialog .debitRecurringInfo').removeClass('once').addClass('permanent');
        }
      }
      Notification.messages(data.message, { timeout: 15 });

      if (data.suggestions !== '') {
        const hints = t(appName, 'Suggested alternatives based on common human mis-transcriptions:')
            + ' '
            + data.suggestions
            + '. '
            + t(appName, 'Please do not accept these alternatives lightly!');
        $(dialogId + ' .suggestions').html(hints).show();
      } else {
        $(dialogId + ' .suggestions').html('').hide();
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
  event.preventDefault();

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

  const validateErrorUnlock = function(responseData) {
    if (responseData) {
      const msg = (responseData.message || '')
            + ' '
            + (makeSuggestions(responseData) || '');
      if (msg !== ' ') {
        $('#cafevdb-page-debug').html(msg).show();
      }
    }
    validateLockCB(false, false);
    globalState.SepaDebitMandate.validationRunning = false;
    $.fn.cafevTooltip.hide();
  };

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
    [pmeData('mandate_reference')]: 'mandateReference',
    [pmeData('bank_account_owner')]: 'bankAccountOwner',
    [pmeData('iban')]: 'bankAccountIBAN',
    [pmeData('bic')]: 'bankAccountBIC',
    [pmeData('blz')]: 'bankAccountBLZ',
    [pmeData('Projects:id')]: 'projectId',
    [pmeData('Musicians:id')]: 'musicianId',
    [pmeData('non_recurring[]')]: 'nonRecurring',
  };
  let changed = $element.attr('name');
  changed = inputMapping[changed];

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
    nonRecurring: $('input[name="' + pmeData('non_recurring[]') + '"]').prop('checked'),
    changed,
  };

  // until end of validation
  validateLock();

  const post = $.param(mandateData);

  $.post(generateUrl('finance/sepa/debit-mandates/validate'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, {
        cleanup: validateErrorUnlock,
        preProcess(data) {
          const hints = makeSuggestions(data);
          if (hints) {
            if (data.message) {
              data.message += ' ' + hints;
            } else {
              data.message = hints;
            }
          }
        },
      });
    })
    .done(function(data) {
      if (!Ajax.validateResponse(
        data,
        ['suggestions', 'message'],
        validateErrorUnlock)) {
        if (data.blz) {
          $('input.bankAccountBLZ').val(data.blz);
        }
        return false;
      }

      if (!Array.isArray(data.message)) {
        data.message = [data.message];
      }

      const hints = makeSuggestions(data);
      if (hints) {
        data.message.push(hints);
      }

      // if (data.value) {
      //   $element.val(data.value);
      // }
      if (data.iban !== undefined) {
        $('input[name="' + pmeData('iban') + '"]').val(data.iban);
      }
      if (data.bic !== undefined) {
        $('input[name="' + pmeData('bic') + '"]').val(data.bic);
      }
      if (data.blz !== undefined) {
        $('input[name="' + pmeData('blz') + '"]').val(data.blz);
      }
      if (data.owner !== undefined) {
        $('input[name="' + pmeData('bank_account_owner') + '"]').val(data.owner);
      }
      if (data.reference !== undefined) {
        $('input[name="' + pmeData('mandate_reference') + '"]').val(data.reference);
      }
      if (data.nonRecurring !== undefined) {
        $('input[name="' + pmeData('non_recurring[]') + '"]').prop('checked', data.nonRecurring === 'true');
      }

      Notification.hide();
      Notification.messages(data.message, { timeout: 15 });

      validateUnlock();

      return true;
    }, 'json');
  return false;
};

const mandatePopupInit = function(selector) {
  const containerSel = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(containerSel);
  const pmeReload = container.find('form.pme-form input.pme-reload').first();
  container.find(':button.sepa-debit-mandate, input.dialog.sepa-debit-mandate')
    .off('click')
    .on('click', function(event) {
      if (container.find('#sepa-debit-mandate-dialog').dialog('isOpen') === true) {
        container.find('#sepa-debit-mandate-dialog').dialog('close').remove();
      } else {
        // We store the values in the data attribute.
        const values = $(this).data('debitMandate');

        mandateLoad({
          sepaId: values,
          done(data) {
            mandatesInit(data, function() {
              if (pmeReload.length > 0) {
                pmeReload.trigger('click');
              }
            });
          },
        });
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
  $.post(generateUrl('finance/sepa/debit-notes/create'), formPost)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, clearBusyState);
    })
    .done(function(data) {
      console.info(data);
      clearBusyState();
    });

  // formPost,
  // function(data) {
  //   if (!Ajax.validateResponse(
  //     data,
  //     ['message', 'debitnote'],
  //     clearBusyState)) {
  //     return false;
  //   }

  //   // Everything worked out, from here we now trigger the
  //   // download and the mail dialog

  //   console.log('debitnote', data.debitnote);

  //   const debitNote = data.debitnote;

  //   // custom post
  //   const postItems = [
  //     'requesttoken',
  //     'projectId',
  //     'projectName',
  //     // 'Table', ?? @TODO not needed?
  //     'musicianId',
  //   ];
  //   const post = {};
  //   for (let i = 0; i < postItems.length; ++i) {
  //     post[postItems[i]] = form.find('input[name="' + postItems[i] + '"]').val();
  //   }
  //   post.DebitNoteId = debitNote.Id;
  //   post.EmailTemplate = data.emailtemplate;

  //   const action = 'ajax/finance/debit-note-download.php';
  //   fileDownload(
  //     action,
  //     post, {
  //       errorMessage(data, url) {
  //         return t(appName, 'Unable to export debit notes.');
  //       },
  //       fail(data) {
  //         clearBusyState();
  //       },
  //       done(url) {
  //         // if insurance, then also epxort the invoice PDFs
  //         if (debitNote.Job === 'insurance') {
  //           const action = 'ajax/insurance/instrument-insurance-export.php';
  //           fileDownload(
  //             action,
  //             formPost, {
  //               done(url) {
  //                 Email.emailFormPopup($.param(post), true, false, clearBusyState);
  //               },
  //               errorMessage(data, url) {
  //                 return t(appName, 'Unable to export insurance overviews.');
  //               },
  //               fail: clearBusyState,
  //             });
  //         } else {
  //           Email.emailFormPopup($.param(post), true, false, clearBusyState);
  //         }
  //       },
  //     });

  //   return true;
  // });

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
      const $self = $(this);
      const otherClass = $self.hasClass('top') ? '.bottom' : '.top';
      const $other = directDebitChooser.filter(otherClass);
      selectValues($other, selectValues($self));
      $other.trigger('chosen:updated');
      $.fn.cafevTooltip.remove();
      return false;
    });

  $.each(
    ['debit-note-amount', 'debit-note-subject'],
    function(index, classValue) {
      container.find('#pme-debit-note-job-up input.' + classValue)
        .off('blur')
        .on('blur', function(event) {
          const $self = $(this);
          container.find('#pme-debit-note-job-down input.' + classValue).val($self.val());
          return false;
        });

      container.find('#pme-debit-note-job-down input.' + classValue)
        .off('blur')
        .on('blur', function(event) {
          const $self = $(this);
          container.find('#pme-debit-note-job-up input.' + classValue).val($self.val());
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

  table.find('input[type="checkbox"]').not('tr.pme-filter input')
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

      mandateValidatePME.call({ name: pmeData('iban') }, event, function(lock, validateOk) {
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

  pmeExportMenu(containerSel);

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
    'sepa-bank-accounts',
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

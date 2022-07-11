/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, $ } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as DialogUtils from './dialog-utils.js';
import * as Page from './page.js';
// import * as Email from './email.js';
import * as Notification from './notification.js';
import checkInvalidInputs from './check-invalid-inputs.js';
import * as PHPMyEdit from './pme.js';
import participantFieldsHandlers from './project-participant-fields-display.js';
import generateUrl from './generate-url.js';
import * as FileUpload from './file-upload.js';
import fileDownload from './file-download.js';
import pmeExportMenu from './pme-export.js';
import * as SelectUtils from './select-utils.js';
import modalizer from './modalizer.js';
// import { recordValue as pmeRecordValue } from './pme-record-id.js';
import { confirmedReceivablesUpdate } from './project-participant-fields.js';
import initFileUploadRow from './pme-file-upload-row.js';
import './lock-input.js';
import {
  data as pmeData,
  formSelector as pmeFormSelector,
  token as pmeToken,
  classSelectors as pmeClassSelectors,
} from './pme-selectors.js';

import 'selectize';
import 'selectize/dist/css/selectize.bootstrap4.css';
// import 'selectize/dist/css/selectize.css';
require('cafevdb-selectize.scss');

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('sepa-debit-mandate.scss');
require('project-participant-fields-display.scss');
require('lock-input.scss');

require('./jquery-datetimepicker.js');

require('./jquery-readonly.js');

/**
 * Initialize the mess with contents. The "mess" is a dialog window
 * with the input form element for the bank account data.
 *
 * @param {object} data Data returned by the AJAX call.
 *
 * @param {Function} onChangeCallback function called after
 * submitting data to the database.
 *
 * @returns {boolean}
 */
const mandatesInit = function(data, onChangeCallback) {

  if (typeof onChangeCallback !== 'function') {
    onChangeCallback = function() {};
  }

  const popup = $(data.contents);

  const makeSepaId = function(data) {
    return {
      projectId: parseInt(data.projectId),
      musicianId: parseInt(data.musicianId),
      bankAccountSequence: parseInt(data.bankAccountSequence),
      mandateSequence: parseInt(data.mandateSequence),
      bankAccountDeleted: !!data.bankAccountDeleted,
      mandateDeleted: !!data.mandateDeleted,
    };
  };

  popup.data('sepaId', makeSepaId(data));

  const mandateFormSelector = 'form.sepa-debit-mandate-form';
  const projectSelectSelector = 'select.mandateProjectId';
  // const projectIdSelector = '.mandateProjectId';
  const projectIdOnlySelector = '.mandateProjectId.only-for-project';
  const projectIdAllSelector = '.mandateProjectId.for-all-receivables';
  const allReceivablesSelector = 'input.for-all-receivables';
  const onlyProjectSelector = 'input.only-for-project';
  const instantValidationSelector = 'input.sepa-validation-toggle';
  const mandateRegistrationSelector = 'input.debit-mandate-registration';
  const mandateDateSelector = 'input.mandateDate';
  const accountOwnerSelector = 'input.bankAccountOwner';
  const uploadPlaceholderSelector = 'input.upload-placeholder';
  const downloadPrefilledSelector = 'input.download-mandate-form';

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

  popup.on('blur', mandateFormSelector + ' ' + 'input[type="text"]:not(.no-validation)', validateInput);

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

  popup.on('click', mandateFormSelector + ' ' + downloadPrefilledSelector, function(event) {
    const sepaId = $.extend({}, popup.data('sepaId'));
    if (popup.find(projectIdAllSelector).prop('checked')) {
      // request pre-filled form for club-member
      sepaId.projectId = 0;
    }
    fileDownload(
      'finance/sepa/debit-mandates/pre-filled',
      sepaId,
      t(appName, 'Unable to download pre-filled mandate form.')
    );
    return false;
  });

  const configureProjectBindings = function(onlyProject) {
    const projectSelect = popup.data('fieldsets').find(projectSelectSelector);

    if (projectSelect.length > 0) {
      projectSelect
        .prop('disabled', !onlyProject)
        .prop('required', onlyProject);
      if (projectSelect[0].selectize) {
        if (onlyProject) {
          projectSelect[0].selectize.unlock();
        } else {
          projectSelect[0].selectize.clear();
          projectSelect[0].selectize.lock();
        }
        projectSelect.next().find('.selectize-input input').prop('disabled', !onlyProject);
      }
    }

    // further inputs
    popup.data('fieldsets').find(projectIdOnlySelector).prop('disabled', !onlyProject);
    popup.data('fieldsets').find(projectIdAllSelector).prop('disabled', onlyProject);
  };

  popup.on('change', mandateFormSelector + ' ' + allReceivablesSelector, function(event) {
    const projectSelect = popup.data('fieldsets').find(projectSelectSelector);
    projectSelect.val('');
    projectSelect.trigger('change');

    const onlyProject = false;
    configureProjectBindings(onlyProject);
    return false;
  });

  popup.on('change', mandateFormSelector + ' ' + onlyProjectSelector, function(event) {
    const onlyProject = true;
    configureProjectBindings(onlyProject);
    return false;
  });

  popup.on('change', mandateFormSelector + ' ' + projectSelectSelector, function(envent) {
    const $self = $(this);
    const allReceivables = popup.data('fieldsets').find(allReceivablesSelector);
    const onlyProject = popup.data('fieldsets').find(onlyProjectSelector);
    allReceivables.prop('checked', $self.val() === '');
    onlyProject.prop('checked', !allReceivables.prop('checked'));
    if ($self.val() === '') {
      configureProjectBindings(false);
    }
    return false;
  });

  const fileUploadTemplate = $('#fileUploadTemplate');
  const uploadWrapperId = appName + 'written-mandate-upload-wrapper';
  const $uploadUi = fileUploadTemplate.octemplate({
    wrapperId: uploadWrapperId,
    formClass: 'file-upload-form',
    accept: '*',
    uploadName: 'files',
    requestToken: OC.requestToken,
  });
  if ($('#' + uploadWrapperId).length === 0) {
    $('body').append($uploadUi);
  } else {
    $('+' + uploadWrapperId).replaceWith($uploadUi);
  }

  popup.on(
    'click',
    mandateFormSelector + ' ' + uploadPlaceholderSelector
      + ', '
      + mandateFormSelector + ' ' + 'input.upload-from-client',
    function(event) {
      $('#' + uploadWrapperId + ' input[type="file"]').trigger('click');
      return false;
    });

  const writtenMandateUploadDone = function(file, index, container) {
    const mandateFieldset = popup.find(mandateFormSelector + ' ' + 'fieldset.debit-mandate');
    mandateFieldset.find('input.written-mandate-file-upload').val(file.name);
    mandateFieldset.find('input.upload-placeholder')
      .val(file.original_name)
      .lockUnlock('lock', true);
    // we now should pretend that we have no written mandate in order to get the styling right
    mandateFieldset.removeClass('have-written-mandate').addClass('no-written-mandate');
  };

  popup.on(
    'click',
    mandateFormSelector + ' ' + 'input.upload-from-cloud',
    function(event) {
      const $this = $(this);
      Dialogs.filePicker(
        t(appName, 'Select debit mandate for {musicianName}', popup.data()),
        function(paths) {
          $this.addClass('busy');
          if (!paths) {
            Dialogs.alert(t(appName, 'Empty response from file selection!'), t(appName, 'Error'));
            $this.removeClass('busy');
            return;
          }
          if (!Array.isArray(paths)) {
            paths = [paths];
          }
          $.post(generateUrl('upload/stash'), { cloudPaths: paths })
            .fail(function(xhr, status, errorThrown) {
              Ajax.handleError(xhr, status, errorThrown);
              $this.removeClass('busy');
            })
            .done(function(files) {
              if (!Array.isArray(files) || files.length !== 1) {
                Dialogs.alert(
                  t(appName, 'Unable to copy selected file(s) {file}.', { file: paths.join(', ') }),
                  t(appName, 'Error'),
                  function() {
                    $this.removeClass('busy');
                  });
                return;
              }
              writtenMandateUploadDone(files[0], 0, $uploadUi);
              $this.removeClass('busy');
            });
        },
        false, // multiple
        undefined, // mimetypeFilter
        undefined, // modal
        undefined, // type
        popup.data('participantFolder'),
      );
    });

  popup.on(
    'change',
    mandateFormSelector + ' ' + 'input.upload-written-mandate-later',
    function(event) {
      $(mandateFormSelector + ' ' + uploadPlaceholderSelector)
        .prop('required', !$(this).prop('checked'));
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
        const lockOptions = $input.val() === '' ? { locked: $input.hasClass('locked') } : defaultLockUnlock;
        $input.lockUnlock(lockOptions);
      });

      if (!$self.hasClass('unused')) {
        $self.find('select').each(function(index) {
          const $select = $(this);
          $select.readonly($select.val() !== '');
        });
        $self.find('input[type="button"]:not(.download-mandate-form)').prop('disabled', true);
        $self.find('input[type="radio"], select').readonly(true);
        $self.find('input[type="button"].download-mandate-form').prop('disabled', $self.hasClass('no-written-mandate'));
        $self.find('input[type="button"].upload-button').prop('disabled', !$self.hasClass('no-written-mandate'));
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
    const accountFieldset = fieldsets.filter('.bank-account');
    const mandateFieldset = fieldsets.filter('.debit-mandate');

    data.fieldsets = fieldsets;

    // $.fn.cafevTooltip.remove(); // remove tooltip form "open-button"
    $widget.find('button.close').focus();

    mandateFieldset.find('select.selectize').each(function(index) {
      const $self = $(this);
      const disabled = $self.prop('disabled');
      $self
        .prop('disabled', false)
        .selectize({
          plugins: ['remove_button'],
          openOnFocus: true,
          closeAfterSelect: true,
        });
      if (disabled) {
        $self.prop('disabled', true);
        $self[0].selectize.lock();
        $self.next().find('.selectize-input input').prop('disabled', true);
      }
    });

    mandateFieldset.find('select.chosen').each(function(index) {
      const $self = $(this);
      const disabled = $self.prop('disabled');
      $self
        .prop('disabled', false)
        .chosen({
          allow_single_deselect: true,
          inherit_select_classes: true,
          disable_search_threshold: 8,
        });
      if (disabled) {
        $self.prop('disabled', true);
      }
    });

    const accountOwnerInput = accountFieldset.find(accountOwnerSelector);
    accountOwnerInput.autocomplete({
      source: accountOwnerInput.data('autocomplete'),
      position: { my: 'left bottom', at: 'left top' },
      minLength: 0,
      autoFocus: true,
      select(event, ui) {
        const $input = $(event.target);
        $input.val(ui.item.value);
        $input.blur();
      },
    });
    accountOwnerInput.on('focus', function(event) {
      const $self = $(this);
      if ($self.val() === '') {
        $self.autocomplete('search', '');
      }
    });

    conservativeAllowChange(fieldsets);

    const accountUsed = !accountFieldset.hasClass('unused');
    const mandateUsed = !mandateFieldset.hasClass('unused');
    const accountDeleted = accountFieldset.hasClass('deleted');
    const mandateDeleted = mandateFieldset.hasClass('deleted');

    if (!(data.sepaId.bankAccountSequence > 0)) {
      // no account, so nothing to delete or disable
      buttons.disable.prop('disabled', true).hide();
      buttons.delete.prop('disabled', true).hide();
    } else if (!(data.sepaId.mandateSequence > 0)) {
      if (accountUsed) {
        // allow only "disable"
        buttons.disable.prop('disabled', false).show();
        buttons.delete.prop('disabled', true).hide();
      } else {
        // unused, safe to delete
        buttons.disable.prop('disabled', true).hide();
        buttons.delete.prop('disabled', false).show();
      }
    } else {
      if (mandateUsed) {
        buttons.disable.prop('disabled', false).show();
        buttons.delete.prop('disabled', true).hide();
      } else {
        // unused, safe to delete
        buttons.disable.prop('disabled', true).hide();
        buttons.delete.prop('disabled', false).show();
      }
    }

    if (!accountDeleted && !mandateDeleted) {
      buttons.reactivate.prop('disabled', true).hide();
    } else {
      buttons.reactivate.prop('disabled', false).show();
    }
    if (accountDeleted && mandateDeleted) {
      buttons.disable.prop('disabled', true).hide();
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

    mandateForm.find('input.mandateDate, input.lastUsedDate').datepicker({
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

    FileUpload.init({
      url: generateUrl('upload/stash'),
      doneCallback: writtenMandateUploadDone,
      stopCallback: null,
      dropZone: mandateFieldset.find('.written-mandate-upload'),
      containerSelector: '#' + uploadWrapperId,
      inputSelector: 'input[type="file"]',
      multiple: false,
    });
  };

  const dialogReload = function($dlg, onChangeCallback) {
    const data = $dlg.data();

    disableButtons();

    mandateLoad({
      sepaId: data.sepaId,
      always: enableButtons,
      done(data) {
        // update ids
        $dlg.data('sepaId', makeSepaId(data));

        // redefine reload-state with response
        popup.data('instantvalidation', true);
        $dlg.html($(data.contents).html());
        initializeDialogHandlers($dlg);
        if (onChangeCallback !== undefined) {
          onChangeCallback();
        }
      },
    });
  };

  popup.on('change', mandateFormSelector + ' ' + mandateRegistrationSelector, function(event) {
    const $self = $(this);
    const checked = $self.prop('checked');
    $(mandateFormSelector + ' ' + mandateDateSelector).prop('required', checked);
    $(mandateFormSelector + ' ' + uploadPlaceholderSelector).prop('required', checked);

    if (checked) {
      const sepaId = popup.data('sepaId');
      if (sepaId.mandateSequence > 0) {
        // Trigger a reload without mandate sequence.
        sepaId.mandateSequence = 0;
        popup.data('sepaId', sepaId);
        dialogReload(popup, function() {
          // has been replaced, so $self is no longer usable
          popup.find(mandateFormSelector + ' ' + mandateRegistrationSelector).prop('checked', true);
        });
      }
    }

    return false;
  });

  popup.cafevDialog({
    position: {
      my: 'middle top+50%',
      at: 'middle top',
      of: '#app-content',
    },
    width: 'auto', // 550,
    height: 'auto',
    dialogClass: 'sepa-debit-mandate-dialog',
    modal: false,
    resizable: false,
    buttons: [
      {
        class: 'save',
        id: 'sepaMandateSave',
        text: t(appName, 'Save'),
        title: t(appName, 'Close the form and save the data in the underlying data-base storage.'),
        click() {
          const $dlg = $(this);
          const $form = $dlg.find(mandateFormSelector);

          disableButtons();

          mandateStore({
            form: $form,
            always: enableButtons,
            done(data) {
              $dlg.dialog('close');
              onChangeCallback();
            },
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

          disableButtons();

          mandateStore({
            form: $form,
            always: enableButtons,
            done(data) {
              // the simplest thing is just to reload the form instead
              // of updating all form elements from JS.

              // data possibly updates the sequence numbers when adding new data:
              const sepaId = makeSepaId(data);
              $dlg.data('sepaId', sepaId);

              dialogReload($dlg, onChangeCallback);
            },
          });
        },
      },
      {
        class: 'close',
        text: t(appName, 'Close'),
        title: t(appName, 'Discard all filled-in data and close the form. Note that this will not undo any changes previously stored in the data-base by pressing the "Apply" button.'),
        click() {
          $(this).dialog('close');
          // $('form.pme-form').submit();
        },
      },
      {
        class: 'delete icon-buttonn revocation-control',
        text: t(appName, 'Delete'),
        title: t(appName, 'Delete this bank-account from the data-base. Normally, this should only be done in case of desinformation or misunderstanding. Use with care.'),
        click() {
          const $dlg = $(this);
          mandateDelete($dlg.data('sepaId'), function(data) {
            const sepaId = makeSepaId(data);
            $dlg.data('sepaId', sepaId);
            dialogReload($dlg, onChangeCallback);
          }, 'delete');
        },
      },
      {
        class: 'reactivate icon-buttonn revocation-control',
        text: t(appName, 'Reactivate'),
        title: t(appName, 'Reactivate the debit-mandate or bank-account in case it'
                 + ' has been deleted in error.'),
        click() {
          const $dlg = $(this);
          mandateDelete($dlg.data('sepaId'), function(data) {
            dialogReload($dlg, onChangeCallback);
          }, 'reactivate');
        },
      },
      {
        class: 'disable icon-buttonn revocation-control',
        text: t(appName, 'Disable'),
        title: t(appName, 'Disable the debit-mandate or bank-account in case the bank account has'
                 + ' changed, or on request of the participant. The bank account can only'
                 + ' be disabled after disabling all bound debit-mandates.'),
        click() {
          const $dlg = $(this);
          mandateDelete($dlg.data('sepaId'), function(data) {
            dialogReload($dlg, onChangeCallback);
          }, 'disable');
        },
      },
      {
        class: 'reload icon-button',
        id: 'sepaMandateReload',
        text: t(appName, 'Reload'),
        title: t(appName, 'Reload the form and locks it. Unsaved changes are lost.'),
        click() {
          const $dlg = $(this);
          dialogReload($dlg);
        },
      },
    ],
    open() {
      const $dlg = $(this);
      const $widget = $dlg.dialog('widget');

      DialogUtils.toBackButton($dlg);
      // DialogUtils.fullScreenButton($dlg);

      const buttons = {
        save: $widget.find('button.save'),
        apply: $widget.find('button.apply'),
        delete: $widget.find('button.delete'),
        disable: $widget.find('button.disable'),
        reactivate: $widget.find('button.reactivate'),
        reload: $widget.find('button.reload'),
      };

      // const revocationButtons = $widget.find('button.revoation-control');

      $dlg.data('buttons', buttons);

      initializeDialogHandlers($dlg);
    },
    close(event, ui) {
      $.fn.cafevTooltip.remove();
      $(this).dialog('destroy').remove();
      modalizer(false);
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

  const $form = $(options.form);
  const $mandateFieldset = $form.find('fieldset.debit-mandate');
  if ($mandateFieldset.hasClass('no-written-mandate')
      && (!$mandateFieldset.hasClass('no-data') || $form.find('input.debit-mandate-registration').prop('checked'))
      && !$mandateFieldset.find('input.upload-written-mandate-later').prop('checked')
      && $mandateFieldset.find('input.written-mandate-file-upload').val() === '') {
    Dialogs.alert(t(appName, 'Please either upload a copy of the written and signed debit-mandate or at least check the "upload later" option'), t(appName, 'Missing Data'));
    options.fail();
    options.always();
    return;
  }

  // "submit" the entire form
  const post = $form.serialize();

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
        Notification.messages(data.message, { timeout: 15 });
        options.done(data);
        options.always();
      }
    });
};

// Delete a mandate
const mandateDelete = function(sepaId, callbackOk, action) {

  // "submit" the entire form
  // const post = $('#sepa-debit-mandate-form').serialize();

  let endPoint = 'debit-mandates';
  let confirmationText = '';
  switch (action) {
  case 'disable':
    // disable account if the mandate is already disabled
    if (!sepaId.mandateSequence || sepaId.mandateDeleted) {
      endPoint = 'bank-accounts';
      confirmationText = t(appName, 'Do you really want to disable the current bank-account?');
    } else {
      confirmationText = t(appName, 'Do you really want to disable the current debit-mandate?');
    }
    break;
  case 'reactivate':
    // first reactivate the account, then the mandate
    if (sepaId.bankAccountDeleted) {
      endPoint = 'bank-accounts';
      confirmationText = t(appName, 'Do you really want to reactivate the current bank-account?');
    } else {
      confirmationText = t(appName, 'Do you really want to reactiveate the current debit-mandate?');
    }
    break;
  case 'delete':
  default:
    action = 'delete';
    // always only try delete the mandate if we have one
    if (!sepaId.mandateSequence) {
      endPoint = 'bank-accounts';
      confirmationText = t(appName, 'Do you really want to delete the current bank-account?');
    } else {
      confirmationText = t(appName, 'Do you really want to delete the current debit-mandate?');
    }
    break;
  }

  // perhaps we should annoy the user with a confirmation dialog?
  Dialogs.confirm(
    confirmationText,
    t(appName, 'Confirmation Required'),
    function(confirmed) {
      if (!confirmed) {
        Notification.show(t(appName, 'Unconfirmed, doing nothing'));
        return;
      }
      $.post(generateUrl('finance/sepa/' + endPoint + '/' + action), sepaId)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, function() {});
        })
        .done(function(data) {
          if (!Ajax.validateResponse(data, ['message'])) {
            return false;
          }
          Notification.messages(data.message);
          if (callbackOk !== undefined) {
            callbackOk(data);
          }
        });
    },
    true);
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
 * @param {object} event TBD.
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
 * @param {object} event TBD.
 *
 * @param {Function} validateLockCB TBD.
 *
 * @returns {boolean}
 */
const mandateValidatePMEWorker = function(event, validateLockCB) {

  const $element = $(this);

  console.info('VALIDATE PME', $element, event);

  const $form = $element.closest('form.' + pmeToken('form'));

  if (typeof validateLockCB === 'undefined') {
    validateLockCB = function(lock, validateOk) {};
  }

  if ($element.is(event.target) && $element.prop('readonly')) {
    validateLockCB(false, null);
    return false;
  }

  const validateLock = function() {
    validateLockCB(true, null);
  };

  const validateUnlock = function() {
    validateLockCB(false, true);
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
    [pmeData('Projects:id')]: 'projectId',
    [pmeData('musician_id')]: 'musicianId',
    [pmeData('SepaDebitMandates:mandate_reference')]: 'mandateReference',
    [pmeData('SepaDebitMandates:mandate_date')]: 'mandateDate',
    [pmeData('SepaDebitMandates:sequence')]: 'mandateSequence',
    [pmeData('SepaDebitMandates:last_used_date')]: 'mandateLastUsedDate',
    [pmeData('SepaDebitMandates:non_recurring[]')]: 'mandateNonRecurring',
    [pmeData('sequence')]: 'bankAccountSequence',
    [pmeData('bank_account_owner')]: 'bankAccountOwner',
    [pmeData('iban')]: 'bankAccountIBAN',
    [pmeData('bic')]: 'bankAccountBIC',
    [pmeData('blz')]: 'bankAccountBLZ',
  };
  let changed = $element.attr('name');
  changed = inputMapping[changed];

  const mandateData = {
    changed,
  };
  const mandateInputs = {};
  for (const [name, key] of Object.entries(inputMapping)) {
    const $input = mandateInputs[key] = $form.find('[name="' + name + '"]');
    mandateData[key] = $input.is(':checkbox') ? $input.prop('checked') : $input.val();
  }
  mandateData.mandateProjectId = mandateData.projectId;

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

      if (data.iban !== undefined) {
        mandateInputs.bankAccountIBAN.val(data.iban);
      }
      if (data.bic !== undefined) {
        mandateInputs.bankAccountBIC.val(data.bic);
      }
      if (data.blz !== undefined) {
        mandateInputs.bankAccountBLZ.val(data.blz);
      }
      if (data.owner !== undefined) {
        mandateInputs.bankAccountOwner.val(data.owner);
      }
      const hasReference = !!data.reference;
      if (data.reference !== undefined) {
        mandateInputs.mandateDate.prop('required', hasReference);
        mandateInputs.mandateDate.readonly(!hasReference);
        mandateInputs.mandateReference.val(data.reference);
      }
      if (data.mandateNonRecurring !== undefined) {
        mandateInputs.mandateNonRecurring.readonly(!hasReference);
        mandateInputs.mandateNonRecurring.prop('checked', !!data.mandateNonRecurring);
      }

      Notification.hide();
      Notification.messages(data.message, { timeout: 15 });

      validateUnlock();

      return true;
    }, 'json');
  return false;
};

/**
 * Serialize input validation calls. The point is that even successful
 * validation may lead to a modification of input elements, which in
 * turn have to serve as input to the next validation call.
 */
let mandateValidatePMEPromise = {};

/**
 * Serialize input validation calls. The point is that even successful
 * validation may lead to a modification of input elements, which in
 * turn have to serve as input to the next validation call.
 *
 * @param {object} event TBD.
 *
 * @param {Function} validateLockCB TBD.
 *
 * @returns {boolean} false
 */
const mandateValidatePME = function(event, validateLockCB) {
  const self = this;
  if (typeof validateLockCB === 'undefined') {
    validateLockCB = function(lock, validateOk) {};
  }
  mandateValidatePMEPromise = $.when(mandateValidatePMEPromise).then(function() {
    const defer = $.Deferred();
    mandateValidatePMEWorker.call(self, event, function(lock, validateOk) {
      validateLockCB(lock, validateOk);
      if (!lock) {
        defer.resolve();
      }
    });
    return defer.promise();
  });
  return false;
};

const mandatePopupInit = function(selector) {
  const containerSel = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(containerSel);

  container.find(':button.sepa-debit-mandate, input.dialog.sepa-debit-mandate')
    .off('click')
    .on('click', function(event) {
      if ($('#sepa-debit-mandate-dialog').dialog('isOpen') === true) {
        // $('#sepa-debit-mandate-dialog').dialog('close').remove();
      } else {
        // We store the values in the data attribute.
        const values = $(this).data('debitMandate');

        mandateLoad({
          sepaId: values,
          done(data) {
            mandatesInit(data, function() {
              const pmeReload = container.find(pmeFormSelector() + ' input.' + pmeToken('reload')).first();
              if (pmeReload.length > 0) {
                pmeReload.trigger('click', {
                  postOpen(pmeDialog) {
                    // override the default in order to avoid moving the underlying dialog to top.
                  },
                });
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

  modalizer(true);
  Page.busyIcon(true);

  const clearBusyState = function() {
    modalizer(false);
    Page.busyIcon(false);
    console.log('after init');
    return true;
  };

  const formPost = form.serialize();
  $.post(generateUrl('finance/sepa/bulk-transactions/create'), formPost)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, clearBusyState);
    })
    .done(function(data) {
      if (!Ajax.validateResponse(['message', 'bankTransferId', 'debitMandateId'], clearBusyState)) {
        return;
      }
      Notification.messages(data.message);
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

  container.find(['input', 'debit-note', pmeToken('misc'), pmeToken('commit')].join('.'))
    .off('click')
    .on('click', mandateExportHandler);
};

// PME handlers, not for the popup dialog
const mandateReady = function(selector, parameters, resizeCB) {

  const containerSel = PHPMyEdit.selector(selector);
  const container = PHPMyEdit.container(containerSel);
  const pmeReload = container.find(pmeFormSelector() + ' input.' + pmeToken('reload')).first();

  // bail out if not for us.
  const form = container.find(pmeFormSelector());
  let $pmeTable = form.find('table[summary="InstrumentInsurance"]');
  if ($pmeTable.length > 0) {
    mandateInsuranceReady(selector);
    return;
  }

  const bulkTransactionChooser = container.find('select.sepa-bulk-transactions');
  bulkTransactionChooser.chosen({
    disable_search: true,
    inherit_select_classes: true,
    allow_single_deselect: true,
  });
  bulkTransactionChooser
    .off('change')
    .on('change', function(event) {
      const $self = $(this);
      const otherClass = $self.hasClass('top') ? '.bottom' : '.top';
      const $other = bulkTransactionChooser.filter(otherClass);
      SelectUtils.selected($other, SelectUtils.selected($self));
      $.fn.cafevTooltip.remove();
      return false;
    });

  const bulkDueDeadline = container.find('input.sepa-due-deadline');
  bulkDueDeadline
    .off('change')
    .on('change', function(event) {
      const $self = $(this);
      const otherClass = $self.hasClass('top') ? '.bottom' : '.top';
      const $other = bulkDueDeadline.filter(otherClass);
      $other.val($self.val());
      return false;
    });

  const $recurringReceivablesUpdateStrategy = container.find('input.recurring-receivables-update-strategy');

  // synchronize top and bottom update-strategy radio buttons´
  $recurringReceivablesUpdateStrategy
    .off('change')
    .on('change', function(event) {
      const $this = $(this);
      const otherId = $this.hasClass('top')
        ? $this.attr('id').replace(/-up$/, '-down')
        : $this.attr('id').replace(/-down$/, '-up');
      $('#' + otherId).prop('checked', true);
      return false;
    });

  container.find('input.regenerate-receivables')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);

      const updateStrategy = $recurringReceivablesUpdateStrategy.filter(':checked').val();

      const requestHandler = function(progressToken, progressCleanup) {
        const cleanup = function() {
          progressCleanup();
          Page.busyIcon(false);
          // modalizer(false);
          $this.removeClass('busy');
        };

        const request = 'generator/run-all';
        const projectId = $this.data('projectId');

        Page.busyIcon(true);
        // modalizer(true);
        $this.addClass('busy');

        return $.post(
          generateUrl('projects/participant-fields/' + request), {
            request,
            data: {
              projectId,
              updateStrategy,
              progressToken,
            },
          })
          .fail(function(xhr, status, errorThrown) {
            Ajax.handleError(xhr, status, errorThrown, cleanup);
          })
          .done(function(data) {
            if (!Ajax.validateResponse(
              data,
              ['fieldsAffected'],
              cleanup)) {
              return;
            }

            Notification.messages(data.message);
            cleanup();

            if (data.fieldsAffected > 0) {
              // reload surrounding form
              if (pmeReload.length > 0) {
                pmeReload.trigger('click');
              }
            }
          });
      };

      confirmedReceivablesUpdate(updateStrategy, requestHandler);

      return false;
    });

  pmeExportMenu(containerSel);

  // from here
  $pmeTable = form.find('table[summary="SepaBankAccounts"]');

  if ($pmeTable.length === 0) {
    return;
  }

  const $musicianIdInput = $pmeTable.find('input.pme-input.musician-id');
  const $projectParticipantSelect = $pmeTable.find('select.pme-input.project-participant');
  const $bankAccountOwnerInput = $pmeTable.find('input.pme-input.bank-account-owner');
  const $bankAccountIbanInput = $pmeTable.find('input.pme-input.bank-account-iban');
  const $bankAccountSequenceInput = $pmeTable.find('input.pme-input.bank-account-sequence');
  const $mandateProjectSelect = $pmeTable.find('select.mandate-project');

  const musicianId = $musicianIdInput.val();
  const projectId = $mandateProjectSelect.val();

  participantFieldsHandlers(container, musicianId, projectId, parameters);

  container.find(['input', 'debit-note', pmeToken('misc'), pmeToken('commit')].join('.'))
    .off('click')
    .on('click', mandateExportHandler);

  if (form.is(pmeClassSelectors('', ['list', 'view', 'delete']))) {
    return;
  }

  const validateInput = function(event) {
    const $input = $(this);
    if ($input.prop('readonly')) {
      return;
    }
    mandateValidatePME.call(this, event, function(lock) {
      $input.readonly(lock);
    });
  };

  $pmeTable.find('input[type="text"].pme-input').off('blur');
  $pmeTable.find('select, input[type="checkbox"]').filter('.pme-input').off('change');

  $mandateProjectSelect
    .closest('tr.pme-row')
    .toggleClass('empty-mandate-project', $mandateProjectSelect.val() === '');
  $mandateProjectSelect.on('change', function(event) {
    const $this = $(this);
    $this
      .closest('tr.pme-row')
      .toggleClass('empty-mandate-project', $this.val() === '');
    // $this.closest('.ui-dialog').trigger('resize');
    container.trigger('pmetable:layoutchange');
  });

  // construct IBAN auto-completion from data-pme-values
  const ibanValues = $bankAccountIbanInput.data('pmeValues');
  const ibanAutoComplete = {};
  const sequenceData = {}; // by musician id and iban
  const ibanIdentifiers = {}; // identifier by IBAN
  for (const [ibanKey, iban] of Object.entries(ibanValues.values)) {
    const ibanIds = ibanValues.data[ibanKey].split(',');
    for (const ibanId of ibanIds) {
      const identifierArray = ibanId.split('-');
      const musicianId = identifierArray[0];
      const sequence = identifierArray[1];
      ibanAutoComplete[musicianId] = ibanAutoComplete[musicianId] || [];
      ibanAutoComplete[musicianId].push(iban);
      sequenceData[musicianId] = sequenceData[musicianId] || {};
      sequenceData[musicianId][iban] = sequenceData[musicianId][iban] || [];
      sequenceData[musicianId][iban].push(sequence); // ?? only one ??
      ibanIdentifiers[iban] = ibanIdentifiers[iban] || [];
      ibanIdentifiers[iban].push(identifierArray);
    }
  }

  const ownerValues = $bankAccountOwnerInput.data('pmeValues');
  const ownerAutoComplete = [];
  const ownerData = {}; // by musician id and sequence
  for (const [ownerKey, owner] of Object.entries(ownerValues.values)) {
    const ownerIdentifiers = ownerValues.data[ownerKey].split(',');
    for (const ownerIdentifier of ownerIdentifiers) {
      const identifierArray = ownerIdentifier.split('-');
      const musicianId = identifierArray[0];
      const sequence = identifierArray[1];
      ownerData[musicianId] = ownerData[musicianId] || {};
      ownerData[musicianId][sequence] = owner;
      ownerAutoComplete.push(owner);
    }
  }

  $bankAccountIbanInput.autocomplete({
    source: ibanAutoComplete[$musicianIdInput.val()],
    position: { my: 'left bottom', at: 'left top' },
    minLength: 0,
    autoFocus: true,
    select(event, ui) {
      const $input = $(event.target);
      $input.val(ui.item.value);
      $input.blur();
    },
  });
  $bankAccountIbanInput.on('focus', function(event) {
    const $self = $(this);
    if ($self.val() === '') {
      $self.autocomplete('search', '');
    }
  });
  $bankAccountIbanInput.on('blur', function(event) {
    // const $this = $(this);

  });

  // auto-fill empty or only-autofilled inputs.
  const maybeAutoFillInput = function($input, value, blur, confirm) {
    if ($input.val() !== value) {
      const autoFillInput = function() {
        $input.data('autoFill', value);
        $input.val(value);
        if (blur === true) {
          $input.blur();
        }
      };
      if ($input.val() === '' || $input.val() === $input.data('autoFill')) {
        autoFillInput();
      } else if (typeof confirm === 'function') {
        confirm(autoFillInput, $input, value, blur);
      }
    }
  };

  $projectParticipantSelect.on('change', function(event) {
    const $this = $(this);
    const $owner = $bankAccountOwnerInput;
    const musicianId = $this.val();
    $musicianIdInput.val(musicianId);
    ibanAutoComplete[musicianId] = [...new Set(ibanAutoComplete[musicianId])];
    $bankAccountIbanInput.autocomplete('option', 'source', ibanAutoComplete[musicianId]);
    let autoOwner = $this.find('option:selected').html();
    let clearAutofill = true;
    if (ibanAutoComplete[musicianId].length === 1) {
      const iban = ibanAutoComplete[musicianId][0];
      if (sequenceData[musicianId][iban].length === 1) {
        const sequence = sequenceData[musicianId][iban][0];
        const owner = ownerData[musicianId][sequence];
        autoOwner = owner;
        maybeAutoFillInput($bankAccountSequenceInput, sequence);
        maybeAutoFillInput($bankAccountIbanInput, iban, true);
        clearAutofill = false;
      }
    }
    if (clearAutofill) {
      maybeAutoFillInput($bankAccountSequenceInput, '');
      maybeAutoFillInput($bankAccountIbanInput, '', true);
    }
    maybeAutoFillInput($owner, autoOwner, true, function(autoFillAction) {
      Dialogs.confirm(
        t(appName,
          'The bank-account-owner is already set but differs from the project-participant.'
          + ' Shall we replace the current bank-account-owner by the project-participant?'),
        t(appName, 'Set Bank-Account-Owner to Project-Participant?'),
        confirm => confirm && autoFillAction(),
        true, // modal
        false, // allowHtml
      );
    });
  });

  $bankAccountOwnerInput.on('blur', function(event) {
    const $this = $(this);
    const $participant = $projectParticipantSelect;
    const value = $this.val();
    if (value !== '') {
      let ownerMusicianId = -1;
      $participant.find('option').each(function() {
        const $option = $(this);
        if ($option.html() === value && $option.val() !== $participant.val()) {
          ownerMusicianId = $option.val();
        }
      });
      const autoFillParticipant = function() {
        SelectUtils.selected($participant, ownerMusicianId, true);
        $participant.data('autoFill', ownerMusicianId);
      };
      if (ownerMusicianId !== -1 && ownerMusicianId !== $participant.val()) {
        if ($participant.val() === '' || $participant.val() === $participant.data('autoFill')) {
          // just overwrite any previous autofill
          autoFillParticipant();
        } else {
          // ask the user if the participant should be replaced
          Dialogs.confirm(
            t(appName,
              'Project-participant is already set but differs from the bank-account-owner.'
              + ' Shall we set the project-participant to the bank-account-owner?'),
            t(appName, 'Set Project-Participant to Bank-Account-Owner?'),
            confirm => confirm && autoFillParticipant(),
            true, // modal
            false, // allowHtml
          );
        }
      }
    }
  });

  $projectParticipantSelect.find('option').each(function() {
    ownerAutoComplete.push($(this).html());
  });

  $bankAccountOwnerInput.autocomplete({
    source: ownerAutoComplete,
    position: { my: 'left bottom', at: 'left top' },
    minLength: 0,
    autoFocus: true,
    select(event, ui) {
      const $input = $(event.target);
      $input.val(ui.item.value);
      $input.blur();
    },
  });
  $bankAccountOwnerInput.on('focus', function(event) {
    const $self = $(this);
    if ($self.val() === '') {
      $self.autocomplete('search', '');
    }
  });

  $pmeTable.find('input[type="text"].pme-input').not('.revocation-date')
    .on('blur', validateInput);

  $pmeTable.find('select.pme-input').not('.project-participant')
    .on('change', validateInput);

  $pmeTable.find('input[type="checkbox"].pme-input')
    .on('change', validateInput);

  const submitSel = pmeClassSelectors('input', ['save', 'apply', 'more']);
  let submitActive = false;
  form
    .off('click', submitSel)
    .on('click', submitSel, function(event) {
      const $button = $(this);
      if (submitActive) {
        $button.blur();
        return false;
      }

      // allow delete button, validation makes no sense here
      if ($button.attr('name') === PHPMyEdit.sys('savedelete')) {
        return true;
      }

      submitActive = true;

      const $inputs = $pmeTable.find('input[type="text"]');

      $.fn.cafevTooltip.hide();
      $inputs.prop('readonly', true);
      $button.blur();

      // need only real valid input element
      const $ibanInput = $inputs.filter('[name="' + pmeData('iban') + '"]');
      console.info('INPUTS', $inputs, $ibanInput, pmeData('iban'));

      mandateValidatePME.call($ibanInput, event, function(lock, validateOk) {
        if (lock) {
          $inputs.prop('readonly', true);
        } else {
          submitActive = false;
          $button.blur();
          $inputs.prop('readonly', false);
          if (validateOk) {
            // submit the form ...
            form.off('click', submitSel);
            $button.trigger('click');
          }
        }
      });

      return false;
    });

  $pmeTable.find('input.sepadate').datepicker({
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

  console.info(
    'MUS PROJ',
    musicianId,
    projectId,
    $pmeTable.find('tr.written-mandate td.pme-value .file-upload-row'),
  );
  if (+musicianId > 0 && +projectId > 0) {
    // upload handlers
    const tableOptions = parameters.tableOptions || {};
    const ambientContainerSelector = tableOptions.ambientContainerSelector;
    const notifyUpload = ambientContainerSelector
      ? function(event) {
        event.stopImmediatePropagation();
        $(ambientContainerSelector).trigger('pmedialog:changed');
        PHPMyEdit.submitOuterForm(ambientContainerSelector);
      }
      : function() {};
    $pmeTable
      .find('tr.written-mandate td.pme-value .file-upload-row')
      .each(function() {
        // don't use arrow notation as it does not have the this binding
        initFileUploadRow.call(
          this,
          projectId,
          musicianId,
          resizeCB, {
            upload: 'finance/sepa/debit-mandates/hardcopy/upload',
            delete: 'finance/sepa/debit-mandates/hardcopy/delete',
          });
        $(this).on('pme:upload-done pme:upload-deleted', notifyUpload);
      });
  }
};

const mandatesDocumentReady = function() {

  PHPMyEdit.addTableLoadCallback(
    'sepa-bank-accounts',
    {
      callback(selector, parameters, resizeCB) {
        if (parameters.reason !== 'dialogClose') {
          mandateReady(selector, parameters, resizeCB);
        }
        resizeCB();
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
  mandatesDocumentReady as documentReady,
  mandatePopupInit as popupInit,
  mandateInsuranceReady as insuranceReady,
  mandateExportHandler as exportHandler,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

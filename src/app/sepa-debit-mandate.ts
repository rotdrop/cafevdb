/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { globalState, appName, $, appPrefix } from './globals.ts';
import * as CAFEVDB from './cafevdb.ts';
import { getRequestToken } from '@nextcloud/auth';
import { translate as t } from '@nextcloud/l10n';
import * as Ajax from './ajax.ts';
import * as Dialogs from './dialogs.ts';
import * as DialogUtils from './dialog-utils.ts';
import pageBusyIcon from './busy-icon.ts';
// import * as Email from './email.ts';
import { showError } from '@nextcloud/dialogs';
import * as Notification from './notification.ts';
import checkInvalidInputs from './check-invalid-inputs.ts';
import * as PHPMyEdit from './pme.ts';
import participantFieldsHandlers from './project-participant-fields-display.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import * as FileUpload from './file-upload.ts';
import fileDownload from './file-download.ts';
import pmeExportMenu from './pme-export.ts';
import * as SelectUtils from './select-utils.ts';
import modalizer from './modalizer.ts';
// import { recordValue as pmeRecordValue } from './pme-record-id.ts';
import {
  confirmedReceivablesUpdate,
  getProjectParticipants,
  getProjectParticipantFields,
  getProjectParticipantFieldOptions,
  receivableAccumulatorProperties,
  receivableKeyedProperties,
  type UpdateStrategy,
} from './project-participant-fields.ts';
import initFileUploadRow from './pme-file-upload-row.ts';
import cloudFilePickerDialog from './cloud-file-picker-dialog.ts';
import './lock-input.ts';
import {
  data as pmeData,
  formSelector as pmeFormSelector,
  token as pmeToken,
  classSelectors as pmeClassSelectors,
} from './pme-selectors.ts';
import {
  lazyDecrypt,
  reject as rejectDecryptionPromise,
  promise as decryptionPromise,
} from './lazy-decryption.ts';
import 'selectize';
import 'selectize/dist/css/selectize.bootstrap.css';
import type {
  ReceivablesStatistics,
  SepaBulkTransactionResponse,
  SepaDebitMandate,
  SepaBankAccount,
  SepaDebitMandateValidation,
} from '../../build/ts-types/php-modules/Controller/DTO.ts';
import {
  CssClasses,
  EnumFileUploadMode,
  EnumSepaDebitMandateBinding,
  type EnumSepaDebitMandateRevocationAction,
  type EnumSepaDebitMandateValidationParam,
} from '../../build/ts-types/php-modules/Controller.ts';
import * as DataConstants from '../../build/ts-types/php-modules/PageRenderer/DataConstants.ts';
import type { TableDialogCallbackData, TableDialogOptions } from './pme-state.ts';
import type { TemplateParameters } from '../components/oc-template/oc-template-parameters.d.ts';
import { isJqXHR } from '../types/ajax/jqxhr-error.ts';
import {
  ACTION_DIALOG,
  ACTION_HARDCOPY,
  ACTION_PRE_FILLED,
  ACTION_STORE,
  ACTION_VALIDATE,
  BASE_PATH,
  END_POINT_BANK_ACCOUNTS,
  END_POINT_DEBIT_MANDATES,
  HARDCOPY_ACTION_DELETE,
  HARDCOPY_ACTION_UPLOAD,
  WRITTEN_MANDATE_FILE_UPLOAD,
  type END_POINTS,
} from '../../build/ts-types/php-modules/Controller/SepaDebitMandatesController.ts';
import { TEMPLATE as template } from '../../build/ts-types/php-modules/PageRenderer/SepaBankAccounts.ts';
import * as UploadsController from '../../build/ts-types/php-modules/Controller/UploadsController.ts';
import { END_POINT as bulkTransactionsEndPoint, TOPIC_CREATE as bulkTransactionCreate } from '../../build/ts-types/php-modules/Controller/SepaBulkTransactionsController.ts';
import type { ResponseData } from '../types/ajax/response-data.d.ts';
import {
  HAVE_WRITTEN_MANDATE,
  NO_WRITTEN_MANDATE,
  WRITTEN_MANDATE_UPLOAD,
} from '../../build/ts-types/php-modules/Controller/CssClasses.ts';

require('cafevdb-selectize.scss');

require('jquery-ui/ui/widgets/autocomplete');
require('jquery-ui/themes/base/autocomplete.css');

require('sepa-debit-mandate.scss');
require('project-participant-fields-display.scss');
require('lock-input.scss');

require('./jquery-datetimepicker.ts');

require('./jquery-readonly.ts');

// import { hasProperty } from '../toolkit/types/type-traits.ts';
// const isSepaBankAccount = (arg: Record<string, unknown>): arg is SepaBankAccount =>
//   hasProperty('bankAccountSequence', arg);
// const isSepaDebitMandate = (arg: Record<string, unknown>): arg is SepaDebitMandate =>
//   hasProperty('debitMandateSequence', arg);

const makeSepaId = (data: SepaBankAccount & Partial<SepaDebitMandate>) => {
  return {
    projectId: data.projectId,
    musicianId: data.musicianId,
    bankAccountSequence: data.bankAccountSequence,
    mandateSequence: data.mandateSequence ?? 0,
    bankAccountDeleted: !!data.bankAccountDeleted,
    mandateDeleted: !!data.mandateDeleted,
  };
};

type SepaId = ReturnType<typeof makeSepaId>;

/**
 * Initialize the mess with contents. The "mess" is a dialog window
 * with the input form element for the bank account data.
 *
 * @param data Data returned by the AJAX call.
 *
 * @param [onChangeCallback] function called after
 * submitting data to the database.
 */
const mandatesInit = (data: SepaDebitMandate, onChangeCallback: () => void = () => {}) => {

  if (typeof onChangeCallback !== 'function') {
    onChangeCallback = function() {};
  }

  const $popup = $(data.contents!) as JQuery<HTMLDivElement>;

  $popup.data('sepaId', makeSepaId(data));

  const mandateFormSelector = 'form.sepa-debit-mandate-form';
  const projectSelectSelector = 'select.mandateProjectId';
  // const projectIdSelector = '.mandateProjectId';
  const projectIdOnlySelector = `.mandateProjectId.${EnumSepaDebitMandateBinding.ONLY_FOR_PROJECT}`;
  const projectIdAllSelector = `.mandateProjectId.${EnumSepaDebitMandateBinding.FOR_ALL_RECEIVABLES}`;
  const allReceivablesSelector = `input.${EnumSepaDebitMandateBinding.FOR_ALL_RECEIVABLES}`;
  const onlyProjectSelector = `input.${EnumSepaDebitMandateBinding.ONLY_FOR_PROJECT}`;
  const instantValidationSelector = 'input.sepa-validation-toggle';
  const mandateRegistrationSelector = 'input.debit-mandate-registration';
  const mandateDateSelector = 'input.mandateDate';
  const accountOwnerSelector = 'input.bankAccountOwner';
  const uploadPlaceholderSelector = 'input.upload-placeholder';
  const downloadPrefilledSelector = 'input.download-mandate-form';

  const disableButtons = (disable: boolean = true) => {
    const buttons = $popup.data('buttons') as JQuery<HTMLElement>[];

    for (const [, button] of Object.entries(buttons)) {
      button.prop('disabled', disable);
    }
  };
  const enableButtons = () => disableButtons(false);

  const validateInput = function<Event extends JQuery.Event>(this: HTMLInputElement, event: Event) {
    const $input = $(this);
    if ($input.prop('readonly')) {
      return;
    }
    if ($input.hasClass('no-validation')) {
      return;
    }
    const instantValidation = $popup.data('instantValidation');
    if (!instantValidation && $input.is('.bankAccountIBAN')) {
      return;
    }
    const buttons = $popup.data('buttons');
    mandateValidate.call(this, event, function(lock: boolean) {
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

  $popup.on(
    'blur',
    mandateFormSelector + ' ' + 'input[type="text"]:not(.no-validation):not(.selectize-input-element)',
    function(this: HTMLInputElement, event) {
      // @todo This is a JQ TS kludge. Get rid of this trampoline.
      validateInput.call(this, event);
    },
  );
  $popup.on(
    'change',
    mandateFormSelector + ' ' + 'select:not(.no-validation)',
    function(this: HTMLInputElement, event) {
      // @todo This is a JQ TS kludge. Get rid of this trampoline.
      // @todo This is a JQ TS kludge. Get rid of this trampoline.
      validateInput.call(this, event);
    },
  );

  // on request disable instant validation while editing, but apply
  // and save buttons stay disabled until validation is reenabled.
  $popup.data('instantValidation', $popup.find(mandateFormSelector + ' ' + instantValidationSelector).prop('checked'));

  $popup.on('change', mandateFormSelector + ' ' + instantValidationSelector, function() {
    const instantValidation = $(this).prop('checked');

    $popup.data('instantValidation', instantValidation);

    if (instantValidation) {
      // force validation when re-enabled.
      $popup.find('input.bankAccountIBAN').trigger('blur');
    }

    const buttons = $popup.data('buttons');
    buttons.save.prop('disabled', !instantValidation);
    buttons.apply.prop('disabled', !instantValidation);

    return false;
  });

  $popup.on('click', mandateFormSelector + ' ' + downloadPrefilledSelector, function() {
    const sepaId = { ...$popup.data('sepaId') };
    if ($popup.find(projectIdAllSelector).prop('checked')) {
      // request pre-filled form for club-member
      sepaId.projectId = 0;
    }
    fileDownload(
      `${BASE_PATH}/${END_POINT_DEBIT_MANDATES}/${ACTION_PRE_FILLED}`,
      sepaId,
      t(appName, 'Unable to download pre-filled mandate form.'),
    );
    return false;
  });

  const configureProjectBindings = function(onlyProject: boolean) {
    const projectSelect = $popup.data('fieldsets').find(projectSelectSelector);

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
    $popup.data('fieldsets').find(projectIdOnlySelector).prop('disabled', !onlyProject);
    $popup.data('fieldsets').find(projectIdAllSelector).prop('disabled', onlyProject);
  };

  $popup.on('change', mandateFormSelector + ' ' + allReceivablesSelector, function() {
    const projectSelect = $popup.data('fieldsets').find(projectSelectSelector);
    projectSelect.val('');
    projectSelect.trigger('change');

    const onlyProject = false;
    configureProjectBindings(onlyProject);
    return false;
  });

  $popup.on('change', mandateFormSelector + ' ' + onlyProjectSelector, function() {
    const onlyProject = true;
    configureProjectBindings(onlyProject);
    return false;
  });

  $popup.on('change', mandateFormSelector + ' ' + projectSelectSelector, function() {
    const $self = $(this);
    const allReceivables = $popup.data('fieldsets').find(allReceivablesSelector);
    const onlyProject = $popup.data('fieldsets').find(onlyProjectSelector);
    allReceivables.prop('checked', $self.val() === '');
    onlyProject.prop('checked', !allReceivables.prop('checked'));
    if ($self.val() === '') {
      configureProjectBindings(false);
    }
    return false;
  });

  const fileUploadTemplate = $('#fileUploadTemplate');
  const uploadWrapperId = `${appName}-${WRITTEN_MANDATE_UPLOAD}-wrapper` as const;
  const $uploadUi = fileUploadTemplate.octemplate<TemplateParameters['fileUploadTemplate']>({
    wrapperId: uploadWrapperId,
    formClass: 'file-upload-form',
    accept: '*',
    uploadName: 'files',
    requestToken: getRequestToken() ?? '',
  });
  if ($('#' + uploadWrapperId).length === 0) {
    $('body').append($uploadUi);
  } else {
    $('+' + uploadWrapperId).replaceWith($uploadUi);
  }

  $popup.on(
    'click',
    mandateFormSelector + ' ' + uploadPlaceholderSelector
      + ', '
      + mandateFormSelector + ' ' + 'input.upload-from-client',
    function() {
      $('#' + uploadWrapperId + ' input[type="file"]').trigger('click');
      return false;
    });

  const writtenMandateUploadDone: FileUpload.Options['doneCallback'] = (file, _index, _$container) => {
    console.info('FILE', { file });
    const mandateFieldset = $popup.find(mandateFormSelector + ' ' + 'fieldset.debit-mandate');
    mandateFieldset.find(`input[name="${WRITTEN_MANDATE_FILE_UPLOAD}"]`).val(JSON.stringify([file]));
    const fileName = (file.upload_mode !== EnumFileUploadMode.LINK)
      ? file.original_name
      : file.name;
    mandateFieldset.find('input.upload-placeholder')
      .val(fileName)
      .lockUnlock('lock', true);
    // we now should pretend that we have no written mandate in order to get the styling right
    mandateFieldset.removeClass(HAVE_WRITTEN_MANDATE).addClass(NO_WRITTEN_MANDATE);
  };

  $popup.on(
    'click',
    mandateFormSelector + ' ' + 'input.upload-from-cloud',
    function(this: JQuery<HTMLInputElement>, _event) {
      const $this = $(this);

      cloudFilePickerDialog({
        setup: () => $this.addClass('busy'),
        cleanup: () => $this.removeClass('busy'),
        filePickerCaption: t(appName, 'Select debit mandate for {musicianName}', $popup.data() as { musicianName: string }),
        initialCloudFolder: $popup.data('participantFolder'),
        handlePickedFiles(files, _paths, cleanup) {
          writtenMandateUploadDone(files[0], 0, $uploadUi);
          cleanup();
        },
      });

      $.fn.cafevTooltip.remove();
      return false;
    });

  $popup.on(
    'change',
    `${mandateFormSelector} input.${CssClasses.UPLOAD_WRITTEN_MANDATE_LATER}`,
    function() {
      $(mandateFormSelector + ' ' + uploadPlaceholderSelector)
        .prop('required', !$(this).prop('checked'));
      return false;
    });

  // Render some inputs as disabled to prevent accidental overwrite
  const conservativeAllowChange = function($fieldSet: JQuery<HTMLFieldSetElement>) {
    $fieldSet.each(function() {
      const $self = $(this);

      const defaultLockUnlock = {
        locked: !$self.hasClass('no-data'),
        hardLocked: !$self.hasClass('unused'),
      };

      $self.find('input[type="text"], input[type="number"]').each(function() {
        const $input = $(this);
        const lockOptions = $input.val() === '' ? { locked: $input.hasClass('locked') } : defaultLockUnlock;
        $input.lockUnlock(lockOptions);
      });

      if (!$self.hasClass('unused')) {
        $self.find('select').each(function() {
          const $select = $(this);
          $select.readonly($select.val() !== '');
        });
        $self.find('input[type="button"]:not(.download-mandate-form)').prop('disabled', true);
        $self.find('input[type="radio"], select').readonly(true);
        $self.find('input[type="button"].download-mandate-form').prop('disabled', $self.hasClass(NO_WRITTEN_MANDATE));
        $self.find('input[type="button"].upload-button').prop('disabled', !$self.hasClass(NO_WRITTEN_MANDATE));
      }
    });
  };

  const initializeDialogHandlers = <E>($dlg: JQuery<E>) => {
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
    $widget.find('button.close').trigger('focus');

    mandateFieldset.find('select.selectize').each(function() {
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
        SelectUtils.getSelectize($self)?.lock();
        $self.next().find('.selectize-input input').prop('disabled', true);
      }
    });

    mandateFieldset.find('select.chosen').each(function() {
      const $self = $(this);
      const disabled = $self.prop('disabled');
      $self
        .prop('disabled', false)
        .chosen({
          allow_single_deselect: true,
          inherit_select_classes: true,
          title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
          disable_search_threshold: 8,
        });
      if (disabled) {
        $self.prop('disabled', true);
      }
    });

    const accountOwnerInput = accountFieldset.find(accountOwnerSelector);
    accountOwnerInput.autocomplete({
      source: accountOwnerInput.data('autocomplete') ?? [],
      position: { my: 'left bottom', at: 'left top' },
      minLength: 0,
      autoFocus: true,
      select(event, ui) {
        const $input = $(event.target);
        $input.val(ui.item.value);
        $input.trigger('blur');
      },
    });
    accountOwnerInput.on('focus', function() {
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
    //     Dialogs.alert(
    //       '<div class="sepa-mandate-expire-notice">'
    //         + notice
    //         + '</div>',
    //       t(appName, 'Debit Mandate Expired'),
    //       undefined,
    //       true, true);
    //   }
    // }

    mandateForm.find<HTMLInputElement>('input.mandateDate, input.lastUsedDate').datepicker({
      minDate: '01.01.1990',
      beforeShow(input) {
        const $input = $(input);
        if ($input.prop('readonly')) {
          return false;
        }
        $input.addClass('no-validation');
        $input.lockUnlock('disable');
        return {};
      },
      onSelect(_dateText, _inst) {
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
        $input.trigger('focus');
        $input.trigger('blur');
      },
      onClose(_dateText, _inst) {
        const $input = $(this);
        $input.removeClass('no-validation');
        $input.lockUnlock('enable');
      },
    });

    FileUpload.init({
      url: generateAppUrl(UploadsController.END_POINT_STASH),
      doneCallback: writtenMandateUploadDone,
      stopCallback: undefined,
      dropZone: mandateFieldset.find(`input.${WRITTEN_MANDATE_UPLOAD}`),
      containerSelector: '#' + uploadWrapperId,
      inputSelector: 'input[type="file"]',
      multiple: false,
    });
  };

  const dialogReload = <E>($dlg: JQuery<E>, onChangeCallback: () => void = () => {}) => {
    const data = $dlg.data();

    disableButtons();

    mandateLoad({
      sepaId: data.sepaId,
      always: enableButtons,
      done(data: SepaDebitMandate) {
        // update ids
        $dlg.data('sepaId', makeSepaId(data));

        // redefine reload-state with response
        $popup.data('instantvalidation', true);
        $dlg.html(data.contents ?? '');
        initializeDialogHandlers($dlg);
        onChangeCallback();
      },
    });
  };

  $popup.on('change', mandateFormSelector + ' ' + mandateRegistrationSelector, function() {
    const $self = $(this);
    const checked = $self.prop('checked');
    $(mandateFormSelector + ' ' + mandateDateSelector).prop('required', checked);
    $(mandateFormSelector + ' ' + uploadPlaceholderSelector).prop('required', checked);

    if (checked) {
      const sepaId = $popup.data('sepaId');
      if (sepaId.mandateSequence > 0) {
        // Trigger a reload without mandate sequence.
        sepaId.mandateSequence = 0;
        $popup.data('sepaId', sepaId);
        dialogReload($popup, function() {
          // has been replaced, so $self is no longer usable
          $popup.find(mandateFormSelector + ' ' + mandateRegistrationSelector).prop('checked', true);
        });
      }
    }

    return false;
  });

  $popup.cafevDialog({
    position: {
      my: 'center top+50%',
      at: 'center top',
      of: '#app-content, #app-content-vue',
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
          const $form = $dlg.find<HTMLFormElement>(mandateFormSelector);

          disableButtons();
          $form.addClass('busy');

          mandateStore({
            $form,
            always: () => {
              enableButtons();
              $form.removeClass('busy');
            },
            done() {
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
        click() {

          const $dlg = $(this);
          const $form = $dlg.find<HTMLFormElement>(mandateFormSelector);

          $form.addClass('busy');
          disableButtons();

          mandateStore({
            $form,
            always: () => {
              enableButtons();
              $form.removeClass('busy');
            },
            done(data: SepaDebitMandate|SepaBankAccount) {
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
        class: 'delete icon-button revocation-control',
        text: t(appName, 'Delete'),
        title: t(appName, 'Delete this bank-account from the data-base. Normally, this should only be done in case of desinformation or misunderstanding. Use with care.'),
        click() {
          const $dlg = $(this);
          mandateDelete($dlg.data('sepaId'), (data: SepaBankAccount|SepaDebitMandate) => {
            const sepaId = makeSepaId(data);
            $dlg.data('sepaId', sepaId);
            dialogReload($dlg, onChangeCallback);
          }, 'delete');
        },
      },
      {
        class: 'reactivate icon-button revocation-control',
        text: t(appName, 'Reactivate'),
        title: t(appName, 'Reactivate the debit-mandate or bank-account in case it'
                 + ' has been deleted in error.'),
        click() {
          const $dlg = $(this);
          mandateDelete($dlg.data('sepaId'), () => {
            dialogReload($dlg, onChangeCallback);
          }, 'reactivate');
        },
      },
      {
        class: 'disable icon-button revocation-control',
        text: t(appName, 'Disable'),
        title: t(appName, 'Disable the debit-mandate or bank-account in case the bank account has'
                 + ' changed, or on request of the participant. The bank account can only'
                 + ' be disabled after disabling all bound debit-mandates.'),
        click() {
          const $dlg = $(this);
          mandateDelete($dlg.data('sepaId'), () => {
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
    close(_event, _ui) {
      $.fn.cafevTooltip.remove();
      $(this).dialog('destroy').remove();
      modalizer(false);
    },
  });
  return false;
};

type MandateLoadOptions = {
  sepaId: SepaId;
  done: (data: SepaDebitMandate) => void;
  fail: () => void;
  always: () => void;
};

const defaultLoadOptions = {
  done() {},
  fail() {},
  always() {},
};

const mandateLoad = function(options_: Pick<MandateLoadOptions, 'sepaId'> & Partial<Omit<MandateLoadOptions, 'sepaId'> >) {
  const options = { ...defaultLoadOptions, ...options_ };
  $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_DEBIT_MANDATES}/${ACTION_DIALOG}`), options.sepaId)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {
        options.fail();
        options.always();
      });
    })
    .done(function(data: SepaDebitMandate) {
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

type MandateStoreOptions = {
  $form: JQuery<HTMLFormElement>;
  done: (data: SepaDebitMandate|SepaBankAccount) => void;
  fail: () => void;
  always: () => void;
};

// Store the form data. We assume that validation already has been
// done
const mandateStore = (options_: Pick<MandateStoreOptions, '$form'> & Partial<Omit<MandateStoreOptions, '$form'> >) => {
  const defaultOptions = {
    done() {},
    fail() {},
    always() {},
  };
  const options = { ...defaultOptions, ...options_ };

  if (!checkInvalidInputs(options.$form)) {
    options.fail();
    options.always();
    return;
  }

  const $form = options.$form;
  const $mandateFieldset = $form.find('fieldset.debit-mandate');
  if ($mandateFieldset.hasClass(NO_WRITTEN_MANDATE)
      && (!$mandateFieldset.hasClass('no-data') || $form.find('input.debit-mandate-registration').prop('checked'))
    && !$mandateFieldset.find(`input.${CssClasses.UPLOAD_WRITTEN_MANDATE_LATER}`).prop('checked')
    && $mandateFieldset.find(`input[name="${WRITTEN_MANDATE_FILE_UPLOAD}"]`).val() === '') {
    Dialogs.alert(t(appName, 'Please either upload a copy of the written and signed debit-mandate or at least check the "upload later" option'), t(appName, 'Missing Data'));
    options.fail();
    options.always();
    return;
  }

  // "submit" the entire form
  const post = $form.serialize();

  $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_DEBIT_MANDATES}/${ACTION_STORE}`), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {
        options.fail();
        options.always();
      });
    })
    .done(function(data: SepaDebitMandate|SepaBankAccount) {
      if (!Ajax.validateResponse(data, [
        'messages',
        'projectId',
        'musicianId',
        'bankAccountSequence',
      ])) {
        options.fail();
        options.always();
      } else {
        Notification.messages(data.messages, { timeout: 15 });
        options.done(data);
        options.always();
      }
    });
};

// Delete a mandate
const mandateDelete = (sepaId: SepaId, callbackOk: (data: SepaDebitMandate|SepaBankAccount) => void, action: EnumSepaDebitMandateRevocationAction) => {

  // "submit" the entire form
  // const post = $('#sepa-debit-mandate-form').serialize();

  let endPoint: (typeof END_POINTS)[number] = END_POINT_DEBIT_MANDATES;
  let confirmationText = '';
  switch (action) {
    case 'disable':
      // disable account if the mandate is already disabled
      if (!sepaId.mandateSequence || sepaId.mandateDeleted) {
        endPoint = END_POINT_BANK_ACCOUNTS;
        confirmationText = t(appName, 'Do you really want to disable the current bank-account?');
      } else {
        confirmationText = t(appName, 'Do you really want to disable the current debit-mandate?');
      }
      break;
    case 'reactivate':
      // first reactivate the account, then the mandate
      if (sepaId.bankAccountDeleted) {
        endPoint = END_POINT_BANK_ACCOUNTS;
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
        endPoint = END_POINT_BANK_ACCOUNTS;
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
      $.post(generateAppUrl(`${BASE_PATH}/${endPoint}/${action}`), sepaId)
        .fail(function(xhr, status, errorThrown) {
          Ajax.handleError(xhr, status, errorThrown, function() {});
        })
        .done(function(data: ResponseData<SepaDebitMandate|SepaBankAccount>) {
          if (!Ajax.validateResponse(data, ['messages'])) {
            return false;
          }
          Notification.messages(data.messages);
          if (callbackOk !== undefined) {
            callbackOk(data);
          }
        });
    },
    true);
};

const makeSuggestions = (data: Partial<SepaDebitMandateValidation>) => {
  if (data.suggestions) {
    return t(appName, 'Suggested alternatives based on common human mis-transcriptions:')
      + ' '
      + data.suggestions.join(', ')
      + '. '
      + t(appName, 'Please do not accept these alternatives lightly!');
  }
  return false;
};

/**
 * Validate version for our $popup-dialog.
 *
 * @param event TBD.
 *
 * @param validateLockCB TBD.
 */
const mandateValidate = function <Element extends HTMLElement, Event extends JQuery.Event>(this: Element, event: Event, validateLockCB: (lock: boolean) => void = () => {}) {
  const dialogId = '#sepa-debit-mandate-dialog';

  const validateLock = function() {
    validateLockCB(true);
  };

  const validateUnlock = function(data?: ResponseData<SepaDebitMandateValidation>) {
    if (data && typeof data !== 'string') {
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
  let post = $('#sepa-debit-mandate-form').serialize();
  post += '&' + $.param({ changed });

  // until end of validation
  validateLock();

  $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_DEBIT_MANDATES}/${ACTION_VALIDATE}`), post)
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
    .done(function(data: ResponseData<SepaDebitMandateValidation>) {
      if (!Ajax.validateResponse(
        data,
        ['suggestions', 'messages'],
        validateUnlock)) {
        if (typeof data !== 'string') {
          // One special case: if the user has submitted an IBAN and
          // the BLZ appeared to be valid after all checks, then
          // inject it into the form. Seems to be a common case, more
          // or less.
          if (data.blz) {
            $('input.bankAccountBLZ').val(data.blz);
          }
          if (data.messages) {
            $(dialogId + ' #msg').html(data.messages.join(' '));
            $(dialogId + ' #msg').show();
          }
        }
        return false;
      }
      if (changed === 'orchestraMember') {
        $('input[name="mandateProjectId"]').val(data.mandateProjectId ?? '');
        // $('input[name="MandateProjectName"]').val(data.mandateProjectName);
        $('input[name="mandateReference"]').val(data.reference ?? '');
        $('legend.mandateCaption .reference').html(data.reference ?? '');
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
      if (data.mandateNonRecurring !== undefined) {
        if (data.mandateNonRecurring) {
          $('#sepa-debit-mandate-dialog .debitRecurringInfo').removeClass('permanent').addClass('once');
        } else {
          $('#sepa-debit-mandate-dialog .debitRecurringInfo').removeClass('once').addClass('permanent');
        }
      }
      Notification.messages(data.messages, { timeout: 15 });

      if (data.suggestions?.length) {
        const hints = t(appName, 'Suggested alternatives based on common human mis-transcriptions:')
            + ' '
            + data.suggestions.join(' ')
            + t(appName, 'Please do not accept these alternatives lightly!');
        $(dialogId + ' .suggestions').html(hints).show();
      } else {
        $(dialogId + ' .suggestions').html('').hide();
      }

      validateUnlock();

      return true;

    });
};

/**
 * Validate version for the PME dialog.
 *
 * Note: the pme-dialog is disabled, but for the date, for the time
 * being.
 *
 * @param event TBD.
 *
 * @param validateLockCB TBD.
 */
const mandateValidatePMEWorker = function<Element extends HTMLElement, ET1, ET2, ET3, ET4 extends HTMLElement>(
  this: Element,
  event: JQuery.TriggeredEvent<ET1, ET2, ET3, ET4>,
  validateLockCB: (lock: boolean, validateOk: null|boolean) => void = () => {},
) {

  const $element = $(this);

  console.info('VALIDATE PME', $element, event);

  const $form = $element.closest('form.' + pmeToken('form'));

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

  const validateErrorUnlock = function(responseData: ResponseData<SepaDebitMandateValidation>) {
    if (responseData && typeof responseData !== 'string') {
      const msg = (responseData.messages ?? []).join(' ')
            + ' '
            + (makeSuggestions(responseData) || '');
      if (msg !== ' ') {
        $('#' + appPrefix('page-debug')).html(msg).show();
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
  const inputMapping: { [key: string]: EnumSepaDebitMandateValidationParam } = {
    [pmeData('Projects:id')]: 'mandateProjectId',
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
  let changed = $element.attr('name') as EnumSepaDebitMandateValidationParam;
  changed = inputMapping[changed];

  if (!changed) {
    return false;
  }

  const mandateData = <{
    changed: EnumSepaDebitMandateValidationParam;
  } & {
    [k in EnumSepaDebitMandateValidationParam]: string;
  }>{
    changed,
  };
  const mandateInputs = <{
    [k in EnumSepaDebitMandateValidationParam]: JQuery<HTMLInputElement>;
  }>{};
  for (const [name, key] of Object.entries(inputMapping)) {
    const $input = mandateInputs[key] = $form.find<HTMLInputElement>('[name="' + name + '"]');
    mandateData[key] = $input.is(':checkbox') ? $input.prop('checked') : $input.val();
  }

  // until end of validation
  validateLock();

  const post = $.param(mandateData);

  $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_DEBIT_MANDATES}/${ACTION_VALIDATE}`), post)
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
    .done(function(data: ResponseData<SepaDebitMandateValidation>) {
      if (!Ajax.validateResponse(
        data,
        ['suggestions', 'messages'],
        validateErrorUnlock)) {
        if (typeof data !== 'string' && data.blz) {
          $('input.bankAccountBLZ').val(data.blz);
        }
        return false;
      }

      const hints = makeSuggestions(data);
      if (hints) {
        data.messages.push(hints);
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
      Notification.messages(data.messages, { timeout: 15 });

      validateUnlock();

      return true;
    });
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
 * @param event TBD.
 *
 * @param validateLockCB TBD.
 */
const mandateValidatePME = function<Element extends HTMLElement, ET1, ET2, ET3, ET4 extends HTMLElement>(
  this: Element,
  event: JQuery.TriggeredEvent<ET1, ET2, ET3, ET4>,
  validateLockCB: (lock: boolean, validateOk: null|boolean) => void = () => {},
) {
  mandateValidatePMEPromise = $.when(mandateValidatePMEPromise).then(() => {
    const defer = $.Deferred();
    mandateValidatePMEWorker.call(this, event, function(lock, validateOk) {
      validateLockCB(lock, validateOk);
      if (!lock) {
        defer.resolve();
      }
    });
    return defer.promise();
  });
  return false;
};

const mandatePopupInit = function(selector: string|JQuery) {
  const containerSel = PHPMyEdit.selector(selector);
  const $container = PHPMyEdit.container(containerSel);

  $container.find(':button.sepa-debit-mandate, input.dialog.sepa-debit-mandate')
    .off('click')
    .on('click', function() {
      if ($('#sepa-debit-mandate-dialog').dialog('isOpen') === true) {
        // $('#sepa-debit-mandate-dialog').dialog('close').remove();
      } else {
        // We store the values in the data attribute.
        const values = $(this).data('debitMandate');

        mandateLoad({
          sepaId: values,
          done(data) {
            mandatesInit(data, function() {
              const $pmeReload = $container.find<HTMLInputElement>(pmeFormSelector + ' input.' + pmeToken('reload')).first();
              if ($pmeReload.length > 0) {
                $pmeReload.trigger('click', {
                  postOpen() {
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

const mandateExportHandler = function<Element extends HTMLFormElement, Event extends JQuery.Event>(this: Element, event: Event) {
  const form = $(this.form!);

  event.stopImmediatePropagation(); // why?

  modalizer(true);
  pageBusyIcon(true);

  const clearBusyState = function() {
    modalizer(false);
    pageBusyIcon(false);
    console.log('after init');
    return true;
  };

  const formPost = form.serialize();
  $.post(generateAppUrl(`${bulkTransactionsEndPoint}/${bulkTransactionCreate}`), formPost)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, clearBusyState);
    })
    .done(function(data: SepaBulkTransactionResponse) {
      if (!Ajax.validateResponse(data, ['messages', 'bankTransferId', 'debitNoteId'], clearBusyState)) {
        return;
      }
      Notification.messages(data.messages);
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

const mandateInsuranceReady = function(selector: string|JQuery) {
  const containerSel = PHPMyEdit.selector(selector);
  const $container = PHPMyEdit.container(containerSel);

  $container.find<HTMLFormElement>(['input', 'debit-note', pmeToken('misc'), pmeToken('commit')].join('.'))
    .off('click')
    .on('click', mandateExportHandler);
};

// PME handlers, not for the popup dialog
const mandateReady = function(selector: string|JQuery, parameters?: TableDialogCallbackData, resizeCB: () => void = () => {}) {

  const containerSel = PHPMyEdit.selector(selector);
  const $container = PHPMyEdit.container(containerSel);
  const $pmeReload = $container.find(pmeFormSelector + ' input.' + pmeToken('reload')).first();

  // bail out if not for us.
  const $form = $container.find(pmeFormSelector);
  let $pmeTable = $form.find('table[summary="InstrumentInsurance"]');
  if ($pmeTable.length > 0) {
    mandateInsuranceReady(selector);
    return;
  }

  const $bulkTransactionChooser = $container.find<HTMLSelectElement>('select.sepa-bulk-transactions');
  $bulkTransactionChooser.chosen({
    disable_search: true,
    inherit_select_classes: true,
    title_attributes: ['title', 'data-original-title', `data-${appName}-title`],
    allow_single_deselect: true,
  });
  $bulkTransactionChooser
    .off('change')
    .on('change', function(this: HTMLSelectElement) {
      const $self = $(this);
      // handle special "select-all" option.
      const selectedValues = SelectUtils.selected($self) as string[];
      if (selectedValues.indexOf('-1') !== -1) {
        const allValues = SelectUtils.optionValues($self).filter((x) => x !== '');
        if (allValues.length === selectedValues.length) {
          selectedValues.splice(0, selectedValues.length);
        } else {
          allValues.splice(allValues.indexOf('-1'), 1);
          selectedValues.splice(0, selectedValues.length, ...allValues);
        }
        while (selectedValues.indexOf('') !== -1) {
          selectedValues.splice(selectedValues.indexOf(''), 1);
        }
        SelectUtils.selected($self, selectedValues);
      }
      const otherClass = $self.hasClass('top') ? '.bottom' : '.top';
      const $other = $bulkTransactionChooser.filter(otherClass);
      SelectUtils.selected($other, selectedValues);
      $.fn.cafevTooltip.remove();
      return false;
    });

  const bulkDueDeadline = $container.find<HTMLInputElement>('input.sepa-due-deadline');
  bulkDueDeadline
    .off('change')
    .on('change', function(this: HTMLInputElement) {
      const $self = $(this);
      const otherClass = $self.hasClass('top') ? '.bottom' : '.top';
      const $other = bulkDueDeadline.filter(otherClass);
      $other.val($self.val()!);
      return false;
    });

  const $recurringReceivablesUpdateStrategy = $container.find<HTMLInputElement>('input.recurring-receivables-update-strategy');

  // synchronize top and bottom update-strategy radio buttons´
  $recurringReceivablesUpdateStrategy
    .off('change')
    .on('change', function(this: HTMLInputElement) {
      const $this = $(this);
      const otherId = $this.hasClass('top')
        ? $this.attr('id')!.replace(/-up$/, '-down')
        : $this.attr('id')!.replace(/-down$/, '-up');
      $('#' + otherId).prop('checked', true);
      return false;
    });

  $container.find<HTMLInputElement>('input.regenerate-receivables')
    .off('click')
    .on('click', function() {
      const $this = $(this);
      (async function() {
        const projectId = $this.data('projectId');
        const cleanup = () => {
          pageBusyIcon(false);
          $this.removeClass('busy');
        };
        pageBusyIcon(true);
        $this.addClass('busy');
        const updateStrategy = $recurringReceivablesUpdateStrategy.filter(':checked').val()! as UpdateStrategy;
        const participants = await getProjectParticipants(projectId);
        if (!participants) {
          cleanup();
          return;
        }
        const participantFields = await getProjectParticipantFields(projectId, 'recurring');
        if (!participantFields) {
          cleanup();
          return;
        }
        const statistics = <ReceivablesStatistics & { cancel?: string }>{};
        for (const key of receivableAccumulatorProperties) {
          statistics[key] = 0;
        }
        for (const key of receivableKeyedProperties) {
          statistics[key] = {};
        }
        for (const field of participantFields) {
          const receivables = await getProjectParticipantFieldOptions(field.id);
          if (!receivables) {
            continue;
          }
          try {
            const data = await confirmedReceivablesUpdate(field, receivables, participants, updateStrategy);
            if (data === false || data.cancel) {
              showError(t(appName, 'Update canceled by user'));
              break;
            }
            for (const key of receivableAccumulatorProperties) {
              statistics[key] += data[key];
            }
            for (const key of receivableKeyedProperties) {
              Object.assign(statistics[key], data[key]);
            }
          } catch (e) {
            console.info('ERROR', e);
            const xhr = (e as Error).cause;
            if (isJqXHR(xhr)) {
              await new Promise((resolve) => Ajax.handleError(xhr, 'error', xhr.statusText, resolve));
            } else {
              throw e;
            }
            break;
          }
        }

        cleanup();

        if (statistics.added + statistics.removed + statistics.changed > 0) {
          // reload surrounding form
          if ($pmeReload.length > 0) {
            $pmeReload.trigger('click');
          }
        }
      })();
      return false;
    });

  pmeExportMenu(containerSel);

  // from here
  $pmeTable = $form.find('table[summary="SepaBankAccounts"]');

  if ($pmeTable.length === 0) {
    return;
  }

  const $musicianIdInput = $pmeTable.find<HTMLInputElement>('input.pme-input.musician-id');
  const $projectParticipantSelect = $pmeTable.find<HTMLSelectElement>('select.pme-input.project-participant');
  const $bankAccountOwnerInput = $pmeTable.find<HTMLInputElement>('input.pme-input.bank-account-owner');
  const $bankAccountIbanInput = $pmeTable.find<HTMLInputElement>('input.pme-input.bank-account-iban');
  const $bankAccountSequenceInput = $pmeTable.find<HTMLInputElement>('input.pme-input.bank-account-sequence');
  const $mandateProjectSelect = $pmeTable.find<HTMLSelectElement>('select.mandate-project');

  const musicianId = +($musicianIdInput.val()!);
  const projectId = +($mandateProjectSelect.val() || $form.find('input[name="projectId"]').val()!);

  participantFieldsHandlers($container, musicianId, projectId, parameters);

  $container.find<HTMLFormElement>(['input', 'debit-note', pmeToken('misc'), pmeToken('commit')].join('.'))
    .off('click')
    .on('click', mandateExportHandler);

  rejectDecryptionPromise(); // terminate previous calls
  decryptionPromise.always((maxJobs) => {
    console.timeEnd('DECRYPTION PROMISE');
    console.info('MAX DECRYPTION JOBS HANDLED', maxJobs);
  });

  if ($form.is(pmeClassSelectors('', ['list', 'view', 'delete']))) {
    lazyDecrypt($form);
    return;
  }

  // initialize auto-complete only after decryption has finished

  decryptionPromise.done(() => {

    // construct IBAN auto-completion from data-pme-values
    const ibanValues: {
      [DataConstants.DATA_DATA_KEY]: Record<string, { data: string }>;
      [DataConstants.DATA_VALUES_KEY]: Record<string, string>;
    } = $bankAccountIbanInput.data('pmeValues');
    const ibanAutoComplete: Record<string, string[]> = {};
    const sequenceData = {}; // by musician id and iban
    const ibanIdentifiers = {}; // identifier by IBAN
    for (const [ibanKey, iban] of Object.entries(ibanValues.values)) {
      const ibanIds = ibanValues.data[ibanKey].data.split(DataConstants.VALUES_SEP);
      for (const ibanId of ibanIds) {
        const identifierArray = ibanId.split(DataConstants.COMP_KEY_SEP);
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

    // construct bank account owner completion
    const ownerValues: {
      [DataConstants.DATA_DATA_KEY]: Record<string, { data: string }>;
      [DataConstants.DATA_VALUES_KEY]: Record<string, string>;
    } = $bankAccountOwnerInput.data('pmeValues');
    const ownerAutoComplete: string[] = [];
    const ownerData = {}; // by musician id and sequence
    for (const [ownerKey, owner] of Object.entries(ownerValues.values)) {
      const ownerIdentifiers = ownerValues.data[ownerKey].data.split(',');
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
      source: ibanAutoComplete?.[$musicianIdInput.val() ?? ''] ?? [],
      position: { my: 'left bottom', at: 'left top' },
      minLength: 0,
      autoFocus: true,
      select(event, ui) {
        const $input = $(event.target);
        $input.val(ui.item.value);
        $input.trigger('blur');
      },
    });
    $bankAccountIbanInput.on('focus', function() {
      const $self = $(this);
      if ($self.val() === '') {
        $self.autocomplete('search', '');
      }
    });
    $bankAccountIbanInput.on('blur', function() {
      // const $this = $(this);
    });

    // auto-fill empty or only-autofilled inputs.
    const maybeAutoFillInput = (
      $input: JQuery<HTMLInputElement>,
      value: string,
      blur?: boolean,
      confirm?: (autoFillInput: () => void, $input: JQuery<HTMLInputElement>, value: string, blur?: boolean) => void,
    ) => {
      if ($input.val() !== value) {
        const autoFillInput = function() {
          $input.data('autoFill', value);
          $input.val(value);
          if (blur === true) {
            $input.trigger('blur');
          }
        };
        if ($input.val() === '' || $input.val() === $input.data('autoFill')) {
          autoFillInput();
        } else if (typeof confirm === 'function') {
          confirm(autoFillInput, $input, value, blur);
        }
      }
    };

    $projectParticipantSelect.on('change', function(this: HTMLSelectElement) {
      const $this = $(this);
      const $owner = $bankAccountOwnerInput;
      const musicianId = $this.val() as string;
      $musicianIdInput.val(musicianId);
      ibanAutoComplete[musicianId] = [...new Set(ibanAutoComplete[musicianId])];
      $bankAccountIbanInput.autocomplete('option', 'source', ibanAutoComplete[musicianId]);
      let autoOwner = SelectUtils.selectedOptions($this).html();
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
          $input.trigger('blur');
        },
      });
      $bankAccountOwnerInput.on('focus', function() {
        const $self = $(this);
        if ($self.val() === '') {
          $self.autocomplete('search', '');
        }
      });
    });
  });

  $bankAccountOwnerInput.on('blur', function() {
    const $this = $(this);
    const $participant = $projectParticipantSelect;
    const value = $this.val();
    if (value !== '') {
      let ownerMusicianId = -1;
      $participant.find('option').each(function() {
        const $option = $(this);
        if ($option.html() === value && $option.val() !== $participant.val()) {
          ownerMusicianId = +($option.val()!);
        }
      });
      const autoFillParticipant = function() {
        SelectUtils.selected($participant, '' + ownerMusicianId, true);
        $participant.data('autoFill', ownerMusicianId);
      };
      if (ownerMusicianId !== -1 && ownerMusicianId !== +($participant.val()!)) {
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

  // must come after the promise.done()
  lazyDecrypt($form);

  const validateInput = function<Element extends HTMLElement, ET1, ET2, ET3, ET4 extends HTMLElement>(
    this: Element,
    event: JQuery.TriggeredEvent<ET1, ET2, ET3, ET4>,
  ) {
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
  $mandateProjectSelect.on('change', function(this: HTMLSelectElement) {
    const $this = $(this);
    $this
      .closest('tr.pme-row')
      .toggleClass('empty-mandate-project', $this.val() === '');
    // $this.closest('.ui-dialog').trigger('resize');
    $container.trigger('pmetable:layoutchange');
  });

  $pmeTable.find<HTMLInputElement>('input[type="text"].pme-input').not('.revocation-date, .participant-field')
    .on('blur', function(event) { validateInput.call(this, event); });

  $pmeTable.find('select.pme-input').not('.project-participant, .participant-field')
    .on('change', validateInput);

  $pmeTable.find('input[type="checkbox"].pme-input').not('.participant-field')
    .on('change', validateInput);

  const submitSel = pmeClassSelectors('input', ['save', 'apply', 'more']);
  let submitActive = false;
  $form
    .off('click', submitSel)
    .on('click', submitSel, function(event) {
      const $button = $(this);
      if (submitActive) {
        $button.trigger('blur');
        return false;
      }

      // allow delete button, validation makes no sense here
      if ($button.attr('name') === PHPMyEdit.sys('savedelete')) {
        return true;
      }

      submitActive = true;

      const $inputs = $pmeTable.find<HTMLInputElement>('input[type="text"]');

      $.fn.cafevTooltip.hide();
      $inputs.prop('readonly', true);
      $button.trigger('blur');

      // need only real valid input element
      const $ibanInput = $inputs.filter('[name="' + pmeData('iban') + '"]');

      mandateValidatePME.call($ibanInput[0], event, function(lock, validateOk) {
        if (lock) {
          $inputs.prop('readonly', true);
        } else {
          submitActive = false;
          $button.trigger('blur');
          $inputs.prop('readonly', false);
          if (validateOk) {
            // submit the form ...
            $form.off('click', submitSel);
            $button.trigger('click');
          }
        }
      });

      return false;
    });

  $pmeTable.find<HTMLInputElement>('input.sepadate').datepicker({
    minDate: '01.01.1990',
    beforeShow(input) {
      $(input).off('blur');
      return {};
    },
    onSelect() {
      $(this).on('blur', validateInput);
      $(this).trigger('focus');
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
    const tableOptions = parameters?.tableDialogOptions ?? {} as Partial<TableDialogOptions>;
    const ambientContainerSelector = tableOptions.ambientContainerSelector;
    const notifyUpload = ambientContainerSelector
      ? function<Event extends JQuery.Event>(event: Event) {
        event.stopImmediatePropagation();
        $(ambientContainerSelector).trigger('pmedialog:changed');
        PHPMyEdit.submitOuterFormNoThrow(ambientContainerSelector);
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
            upload: `${BASE_PATH}/${END_POINT_DEBIT_MANDATES}/${ACTION_HARDCOPY}/${HARDCOPY_ACTION_UPLOAD}`,
            delete: `${BASE_PATH}/${END_POINT_DEBIT_MANDATES}/${ACTION_HARDCOPY}/${HARDCOPY_ACTION_DELETE}`,
          });
        $(this).on('pme:upload-done pme:upload-deleted', notifyUpload);
      });
  }
};

const mandatesDocumentReady = () => {

  PHPMyEdit.addTableLoadCallback(
    template,
    {
      callback(_template, selector, parameters, resizeCB) {
        if (parameters.reason !== 'dialogClose') {
          mandateReady(selector, parameters, resizeCB);
        }
        resizeCB();
      },
    });

  CAFEVDB.addReadyCallback(async () => {
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

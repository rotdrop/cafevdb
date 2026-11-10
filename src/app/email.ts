/**
 * @file
 *
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 *
 * @module
 */

import type { EnumEmailFormComposerOperation as ComposerOperation, EnumEmailFormComposerTopic as ComposerTopic, EnumEmailFormComposerElement } from '../../build/ts-types/php-modules/Controller.ts';
import type {
  // EmailFormComposerResponse,
  EmailFormComposerRequestData as ComposerRequestData,
  EmailFormComposerResponse,
  EmailFormListContactsResponse,
  EmailFormRecipientsFilterReloadResponse,
  EmailFormRecipientsFilterResponse,
  EmailFormRecipientsFilterSnapshotResponse,
  EmailWebFormResponse,
  ProgressResponse,
} from '../../build/ts-types/php-modules/Controller/DTO.ts';
import type { EnumFromTag } from '../../build/ts-types/php-modules/EmailForm.ts';
import type { MountableComponent } from '../services/mountable-components.ts';
import type { SelectizeAdapterOptions } from '../services/mountable-selectize.ts';
import type { ResponseData } from '../types/ajax/response-data.d.ts';
import type { EmailFormEvent, EmailFormTab } from '../types/email-form-envelope.ts';
import type { VueSelectValue } from '../types/vue-select.ts';
import type { UploadFile } from './file-upload.ts';

import { translate as t } from '@nextcloud/l10n';
import { generateOcsUrl } from '@nextcloud/router';
import { emit as asyncEmit, subscribe as asyncSubscribe, unsubscribe as asyncUnsubscribe, awaitEmit } from '@rotdrop/async-nextcloud-event-bus';
import {
  EnumEmailFormContactsOperation,
  EnumPersonalSettingsKey,
} from '../../build/ts-types/php-modules/Controller.ts';
import {
  EnumMusicianValidationTopic,
} from '../../build/ts-types/php-modules/Controller.ts';
import { WYSIWYG_EDITOR } from '../../build/ts-types/php-modules/Controller/CssClasses.ts';
import {
  BASE_PATH,
  END_POINT_ATTACHMENT,
  END_POINT_COMPOSER,
  END_POINT_CONTACTS,
  END_POINT_FORM,
  END_POINT_RECIPIENTS,
} from '../../build/ts-types/php-modules/Controller/EmailFormController.ts';
import {
  END_POINT as validationEndPoint,
} from '../../build/ts-types/php-modules/Controller/MusicianValidationController.ts';
import {
  GET_PROJECT_FOLDER,
  BASE_PATH as projectsEndPoint,
} from '../../build/ts-types/php-modules/Controller/ProjectsController.ts';
import { EnumAttachmentOrigin } from '../../build/ts-types/php-modules/Database/Doctrine/DBAL/Types.ts';
import {
  ComposerCgiKeys,
  EmailFormCssClasses,
  EnumPostTag,
  RecipientsFilterCgiKeys,
} from '../../build/ts-types/php-modules/EmailForm.ts';
import {
  DRAFT_EMAIL_MESSAGE_SELECTOR,
  SENT_EMAIL_MESSAGE_SELECTOR,
  STORED_MESSAGES_CHOICES_CONTAINER,
  TEMPLATE_EMAIL_MESSAGE_SELECTOR,
} from '../../build/ts-types/php-modules/EmailForm/ComposerCssClasses.ts';
import { PersistentCGIKeys } from '../../build/ts-types/php-modules/PageRenderer.ts';
import { TEMPLATE as allMusiciansTemplate } from '../../build/ts-types/php-modules/PageRenderer/AllMusicians.ts';
import { TEMPLATE as projectParticipantsTemplate } from '../../build/ts-types/php-modules/PageRenderer/ProjectParticipants.ts';
import { FOLDER_TYPE_PROJECT } from '../../build/ts-types/php-modules/Service/ProjectService.ts';
import {
  EMAIL_FORM_COMMAND,
  EMAIL_FORM_EVENT,
  GET_VUE_COMPONENT,
  LEGACY_UPDATE_EVENTS_SELECTION,
  PROJECT_EVENTS_LISTING,
} from '../event-bus-events.ts';
import { EMAIL_FORM_ENVELOPE } from '../mountable-component-names.ts';
import { addressBookSelectOptions, selectedFreeFormContacts } from '../services/address-book-select.ts';
import { DualListboxService, getMountedDualListbox } from '../services/mountable-dual-listbox.ts';
import { getMountedSelectize } from '../services/mountable-selectize.ts';
import { destroyMountedSelects, mountedSelectScope } from '../services/mounted-select-scope.ts';
import { asKey } from '../toolkit/types/type-traits.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import { showSuccess } from '../toolkit/util/toasts.ts';
import {
  handleError as ajaxHandleError,
  validateResponse as ajaxValidateResponse,
} from './ajax.ts';
import pageBusyIcon from './busy-icon.ts';
import { globalState, toolTipsInit } from './cafevdb.ts';
import debounce from './debounce.ts';
import * as Dialogs from './dialogs.ts';
import fileDownload from './file-download.ts';
import fileUploadInit from './file-upload.ts';
import { appName, appPrefix } from './globals.ts';
import $ from './jquery.ts';
import { show as notificationShow } from './notification.ts';
import { data as pmeData } from './pme-selectors.ts';
import * as ProgressStatus from './progress-status.ts';
import { personalRecordDialog as participantRecordDialog } from './project-participants.ts';
import selectPopup from './select-popup.ts';
import * as SelectUtils from './select-utils.ts';
import { setPersonalUrl } from './settings-urls.ts';
import { urlDecode } from './url-decode.ts';
import * as WysiwygEditor from './wysiwyg-editor.ts';

import './jquery-readonly.ts';
import './user-manual.ts';
import 'emailform.module.scss';
import {
  displayCssClass,
  dropdownOpenCssClass,
  editCssClass,
  emptySelectionCssClass,
  noAttachmentsCssClass,
  projectModeCssClass,
  projectModeOffCssClass,
  showSelectableCssClass,
} from 'emailform.module.scss';
// eslint-disable-next -line n/no-missing-import
import {
  disabledCssClass,
  expandedCssClass,
  hiddenCssClass,
  loadingCssClass,
  reallyHiddenCssClass,
} from 'variables.module.scss';

type AttachmentElementData = {
  options: string; // HTML fragment
  attachments: Record<string, unknown>;
};

type EmailFormRecipientsFilterResponseData = EmailFormRecipientsFilterReloadResponse
  |EmailFormRecipientsFilterResponse
  |EmailFormRecipientsFilterSnapshotResponse;

const isRecipientsFilterReloadResponse = (arg: EmailFormRecipientsFilterResponseData): arg is EmailFormRecipientsFilterReloadResponse =>
  (asKey(<EmailFormRecipientsFilterReloadResponse>arg, 'contents') in arg) && typeof arg.contents === 'string' && arg.contents.length > 0;

const isRecipientsFilterResponse = (arg: EmailFormRecipientsFilterResponseData): arg is EmailFormRecipientsFilterResponse =>
  asKey(<EmailFormRecipientsFilterResponse>arg, 'recipientsOptions') in arg;

const multiSelectOptions = {
  persist: false,
  closeAfterSelect: true,
};

const mountEmailSelect = ($select: JQuery<HTMLSelectElement>, options: SelectizeAdapterOptions = {}) => {
  for (const select of $select.toArray()) {
    const container = select.closest<HTMLElement>('#emailformrecipients, #emailformcomposer') ?? select.parentElement!;
    void mountedSelectScope(container).mount(select, options).catch((error: unknown) => {
      console.error('Unable to mount email select control.', error);
      notificationShow(t(appName, 'Unable to initialize the select controls.'), { timeout: 15 });
    });
  }
};

const Email = {
  topicUnspecific: 'general',
  active: false,
  blocked: false,
  envelope: undefined as MountableComponent<typeof EMAIL_FORM_ENVELOPE>|undefined,
  activeTab: 'recipients' as EmailFormTab,
  host: undefined as HTMLElement|undefined,
  autoSaveTimer: null as null|NodeJS.Timeout,
  autoSaveDelete() {},
};

const showEmailForm = () => asyncEmit(EMAIL_FORM_COMMAND, { action: 'show' });
const selectEmailTab = (tab: EmailFormTab) => asyncEmit(EMAIL_FORM_COMMAND, { action: 'select-tab', tab });
const isEmailFormOpen = () => Email.active;
const destroyEmailForm = () => {
  Email.envelope?.unmount();
  Email.envelope?.destroy();
  Email.host?.remove();
  Email.envelope = undefined;
  Email.host = undefined;
};
const setEmailFormBlocked = (blocked: boolean) => {
  Email.blocked = blocked;
  asyncEmit(EMAIL_FORM_COMMAND, { action: 'blocked', blocked });
  if (!blocked && Email.host && !Email.host.isConnected) {
    const host = Email.host;
    queueMicrotask(() => {
      if (Email.host === host && !Email.blocked) {
        destroyEmailForm();
      }
    });
  }
};

const closeEmailForm = () => {
  if (Email.blocked || (Email.active && !Email.envelope)) {
    return false;
  }
  destroyEmailForm();
  return true;
};

asyncSubscribe(LEGACY_UPDATE_EVENTS_SELECTION, (event) => {
  const $emailFormDialog = $('div#emailformdialog');
  $emailFormDialog.trigger(appName + ':events_changed', [event.selection]);
});

const generateEmailFormUrl: typeof generateAppUrl = (url, urlParams, urlOptions) =>
  generateAppUrl(`${BASE_PATH}/${url}`, urlParams, urlOptions);

const generateComposerUrl = <Operation extends ComposerOperation, Topic extends ComposerTopic>(
  operationArg: Operation|ComposerRequestData<Operation, Topic>,
  topicArg?: Topic,
) => {
  let topic: ComposerTopic = topicArg ?? 'general';
  let operation: ComposerOperation;
  if (typeof operationArg !== 'string') {
    topic = operationArg.topic ?? 'general';
    operation = operationArg.operation;
  } else {
    operation = operationArg;
  }
  return generateEmailFormUrl(`${END_POINT_COMPOSER}/{operation}/{topic}`, { operation, topic });
};

/**
 * @param response TBD.
 */
function attachmentFromJSON(response: UploadFile) {
  const $fileAttachmentsHolder: JQuery<HTMLInputElement> = $(`form.${appName}-email-form fieldset.attachments input.file-attachments`);
  if ($fileAttachmentsHolder.length === 0) {
    Dialogs.alert(t(appName, 'Not called from main email-form.'), t(appName, 'Error'));
    return;
  }

  const file: UploadFile = { ...response };
  file.status = 'new';
  const fileAttachmentsJSON = $fileAttachmentsHolder.val();
  const fileAttachments = !fileAttachmentsJSON ? [] : JSON.parse(fileAttachmentsJSON as string);
  fileAttachments.push(file);
  $fileAttachmentsHolder.val(JSON.stringify(fileAttachments));
}

const cloudAttachment = function(paths: string|string[], callback: () => void = () => {}) {
  $.post(generateEmailFormUrl(`${END_POINT_ATTACHMENT}/${EnumAttachmentOrigin.CLOUD}`), { paths })
    .fail(ajaxHandleError)
    .done(function(response: UploadFile[]) {
      for (const attachment of response) {
        attachmentFromJSON(attachment);
      }
      callback();
    });
};

/**
 * @param $dialogWidget TBD.
 * @param $panelHolder TBD.
 */
function emailTabResize($dialogWidget: JQuery, $panelHolder: JQuery) {
  const height = Math.max(0, $dialogWidget[0].getBoundingClientRect().bottom - $panelHolder[0].getBoundingClientRect().top);
  $panelHolder.css({ 'max-height': `${height}px`, 'overflow-y': 'auto' });
}

const findComposerRequestInput = <E extends HTMLElement, S extends keyof ComposerRequestData, T extends string = 'name'>(
  $e: JQuery<E>,
  param: S,
  attr: T = 'name' as T,
) => $e.find(`input[${attr}="${EnumPostTag.COMPOSER}[${param}]"]`);
const findComposerInput = <E extends HTMLElement, S extends string, T extends string = 'name'>(
  $e: JQuery<E>,
  param: S,
  attr: T = 'name' as T,
) => $e.find(`input[${attr}="${EnumPostTag.COMPOSER}[${param}]"]`);

/**
 * @param $emailForm TBD.
 * @param elements TBD.
 */
function updateComposerElements($emailForm: JQuery<HTMLFormElement>, elements?: EnumEmailFormComposerElement[]) {
  elements = elements ?? ['to'];
  if (!Array.isArray(elements)) {
    elements = [elements];
  }
  // we better serialize the entire form here
  let post = $emailForm.serialize();
  // place our update request
  // post += '&emailComposer[request]=update';
  const requestKey: keyof ComposerRequestData = 'formElements' as const;
  for (const element of elements) {
    post += `&${EnumPostTag.COMPOSER}[${requestKey}][]=${element}`;
  }
  const url = generateComposerUrl('update', 'element');
  $.post(url, post)
    .fail(ajaxHandleError)
    .done((data: ResponseData<EmailFormComposerResponse<'update', 'element'>>) => {
      if (!ajaxValidateResponse(data, [
        'projectId',
        'projectName',
        'operation',
        'requestData',
      ])) {
        return;
      }

      for (const element of data.requestData.formElements!) {
        switch (element) {
          case 'to': {
            const toSpan = $emailForm.find('span.email-recipients');
            const rcpts = data.requestData.elementData![element]!;
            const numRcpts = rcpts.length;

            const rcptsValue = numRcpts === 0 ? toSpan.data('placeholder') as string : rcpts.join(', ');
            const title = toSpan.data('titleIntro') + '<br>' + rcpts;

            toSpan.cafevTooltip('dispose');
            toSpan.html(rcptsValue);
            toSpan.attr('title', title);
            toSpan.cafevTooltip();

            $emailForm.find('#check-disclosed-recipients').prop('disabled', numRcpts <= 1);
            break;
          }
          case 'subjectTag': {
            const subjectTag = data.requestData.elementData![element]!;
            $emailForm.find('.subject.tag.container .content .editable').val(subjectTag);
            $emailForm.find('.subject.tag.container .content .display').html(subjectTag);
            break;
          }
          default:
            break;
        }
      }
    });
}

/**
 * Add some extra JS stuff for the select boxes. This has to be
 * called when the tab is actually visible because the add-on
 * libraries use the size of the original controls as base for their
 * layout.
 *
 * @param $dialogHolder TBD.
 *
 * @param $fieldset TBD.
 */
const emailFormRecipientsSelectControls = ($dialogHolder: JQuery, $fieldset: JQuery<HTMLFieldSetElement>) => {

  if (
    Email.activeTab !== 'recipients'
      || !!getMountedSelectize($fieldset.find<HTMLSelectElement>('#participation-status-filter')[0])
  ) {
    return;
  }

  const $participationStatusFilter = $fieldset.find<HTMLSelectElement>('#participation-status-filter');
  mountEmailSelect($participationStatusFilter, {
    ...multiSelectOptions,
    closeAfterSelect: false,
  });

  const $instrumentsFilter = $fieldset.find('#instruments-filter') as JQuery<HTMLSelectElement>;
  mountEmailSelect($instrumentsFilter, {
    ...multiSelectOptions,
    closeAfterSelect: false,
  });

  const recipientsSelect = $fieldset.find<HTMLSelectElement>('#recipients-select')[0];
  if (recipientsSelect) {
    void DualListboxService.mount(recipientsSelect).ready.catch((error: unknown) => {
      console.error('Unable to mount email recipients control.', error);
      notificationShow(t(appName, 'Unable to initialize the select controls.'), { timeout: 15 });
    });
  }

  toolTipsInit($dialogHolder.find('div#emailformrecipients'));
};

/**
 * Add handlers to the control elements, and call the AJAX sciplets
 * for validation to update the recipients selection tab accordingly.
 *
 * @param $fieldset The field-set enclosing the recipients selection part
 *
 * @param $form TBD.
 *
 * @param $dialogHolder The div holding the jQuery dialog for everything
 *
 * @param $panelHolder The div enclosing the $fieldset
 */
const emailFormRecipientsHandlers = (
  $fieldset: JQuery<HTMLFieldSetElement>,
  $form: JQuery<HTMLFormElement>,
  $dialogHolder: JQuery,
  $panelHolder: JQuery,
) => {

  emailFormRecipientsSelectControls($dialogHolder, $fieldset);

  const $recipientsSelect = $fieldset.find<HTMLSelectElement>('select#recipients-select');
  const $missingAddresses = $fieldset.find('.missing-email-addresses.names');
  const $missingLabel = $fieldset.find('.missing-email-addresses.label');
  const $noMissingLabel = $fieldset.find('.missing-email-addresses.label.empty');
  const $instrumentsFilter = $fieldset.find('.instruments-filter.' + appPrefix('container'));
  const $instrumentsSelect = $instrumentsFilter.find('select') as JQuery<HTMLSelectElement>;
  const $participationStatusFilter = $fieldset.find('.participation-status-filter.' + appPrefix('container'));
  const $participationStatusSelect = $participationStatusFilter.find('select');
  const $debugOutput = $form.find('#emailformdebug');
  const $busyIndicator = $fieldset.find('.busy-indicator');

  let filterUpdateActive = false;

  // Apply the instruments filter
  const applyRecipientsFilter = function(
    this: HTMLElement,
    event: JQuery.EventBase|JQuery.ClickEvent|JQuery.TriggeredEvent,
    userParameters: Partial<{
      historySnapshot: boolean;
      cleanup: () => void;
    }>,
  ) {
    const defaultParameters = {
      historySnapshot: false,
      cleanup() {},
    };

    $.fn.cafevTooltip.hide();

    event.preventDefault(); // as our return value is not necessarily passed back to JQ

    if (filterUpdateActive) {
      return false;
    }

    filterUpdateActive = true;

    const parameters = { ...defaultParameters, ...userParameters };

    const historySnapshot = parameters.historySnapshot;
    if (!historySnapshot) {
      $busyIndicator.show();
    }

    let post = $fieldset.serialize();
    if (historySnapshot) {
      post += '&' + $.param({
        [EnumPostTag.RECIPIENTS_FILTER]: {
          [RecipientsFilterCgiKeys.HISTORY_SNAPSHOT]: 'snapshot',
        },
      });
    } else {
      post += '&' + $form.find(`fieldset.${EmailFormCssClasses.FORM_DATA}`).serialize();
      const $element = $(event.target);
      if ($element.is(':button')) {
        post += '&' + $.param($element);
      }
      // add the name of the cause for this havoc as additional parameter
      const elementNames = [...$element.attr('name')!.matchAll(/([^[]+)\[([^\]]+)\]/g)];
      if (elementNames.length === 1 && elementNames[0].length === 3) {
        post += '&' + elementNames[0][1] + '[userInteraction]=' + elementNames[0][2];
      }
    }
    $.post(generateEmailFormUrl(`${END_POINT_RECIPIENTS}`), post)
      .fail(function(xhr, textStatus, errorThrown) {
        ajaxHandleError(xhr, textStatus, errorThrown, function() {
          parameters.cleanup();
          $busyIndicator.hide();
          filterUpdateActive = false;
        });
      })
      .done(function(data: EmailFormRecipientsFilterResponseData) {
        let debugText = '';
        let resize = false;
        if (isRecipientsFilterReloadResponse(data)) {
          const requiredKeys = [
            'contents',
            'filterHistory',
          ] as const;
          if (!ajaxValidateResponse(data, requiredKeys)) {
            parameters.cleanup();
            $busyIndicator.hide();
            filterUpdateActive = false;
            return;
          }
          // replace the entire tab.
          $.fn.cafevTooltip.remove();
          destroyMountedSelects($panelHolder[0]);
          $panelHolder.html(data.contents);
          $fieldset = $panelHolder.find<HTMLFieldSetElement>('fieldset.email-recipients.page');
          emailFormRecipientsHandlers($fieldset, $form, $dialogHolder, $panelHolder);
          resize = true;
        } else if (isRecipientsFilterResponse(data)) {

          const requiredKeys = [
            'recipientsOptions',
            'missingEmailAddresses',
            'filterHistory',
            'instrumentsFilter',
            'participationStatusFilter',
          ] as const;
          if (!ajaxValidateResponse(data, requiredKeys)) {
            parameters.cleanup();
            $busyIndicator.hide();
            filterUpdateActive = false;
            return;
          }

          // partial update
          $.fn.cafevTooltip.hide();

          // list of recipients
          $recipientsSelect.html(data.recipientsOptions);
          console.info('RECIPIENT OPTIONS', { options: data.recipientsOptions, $recipientsSelect });
          getMountedDualListbox($recipientsSelect[0])?.refresh();

          // list of broken email addresses
          $missingAddresses.html(data.missingEmailAddresses);
          if (data.missingEmailAddresses.length > 0) {
            $missingLabel.removeClass(reallyHiddenCssClass);
            $noMissingLabel.addClass(reallyHiddenCssClass);
          } else {
            $missingLabel.addClass(reallyHiddenCssClass);
            $noMissingLabel.removeClass(reallyHiddenCssClass);
          }

          // update the instruments filter
          SelectUtils.replaceOptions($instrumentsSelect, data.instrumentsFilter);

          // update the participation-status filter
          SelectUtils.replaceOptions($participationStatusSelect, data.participationStatusFilter);

          debugText = '<pre>'
            + $('<div></div>').text(data.recipientsOptions).html()
            + '</pre>'
            + data.missingEmailAddresses
            + '<br/>';

          resize = true;
        }

        if ('filterHistory' in data) {
          const filterHistory = data.filterHistory;
          if (filterHistory.historyPosition >= 0
            && filterHistory.historyPosition < filterHistory.historySize - 1) {
            // enable the undo button
            $fieldset.find('#instruments-filter-undo').prop('disabled', false);
            console.info('ENABLE UNDO', filterHistory);
          } else {
            $fieldset.find('#instruments-filter-undo').prop('disabled', true);
          }
          if (filterHistory.historyPosition > 0) {
            // enable the redo button as well
            $fieldset.find('#instruments-filter-redo').prop('disabled', false);
          } else {
            $fieldset.find('#instruments-filter-redo').prop('disabled', true);
          }
        }

        debugText += $('<div></div>').text(urlDecode(post)).html();
        $debugOutput.html(debugText);

        parameters.cleanup();
        if (resize) {
          $panelHolder.trigger('resize', { position: 'bottom' });
        }

        $busyIndicator.hide();

        filterUpdateActive = false;
      });
    return false;
  };

  /**
   * Prevent user interaction to the filter controls during loading or
   * if one of the mailing lists has been chosen as the sole
   * recipient.
   *
   * @param state TBD.
   *
   * @param exceptions Array of CSS selectors to exclude from the
   * read-only attempt.
   */
  const readonlyFilterControls = (state: boolean, exceptions?: string[]) => {
    // console.trace('READONLY FILTERS', { state, exceptions });
    // $fieldset.toggleClass('filter-controls-disabled', state);

    exceptions = exceptions ?? [];
    exceptions.push('.action-menu-toggle.basic-recipients-set');

    const $otherInputs = $fieldset.find('input, select, button').not('.dual-listbox-control *').not(exceptions.join(','));
    // Disable all recipient filters as they do not make any
    // sense. Sending to the mailing lists means to just send to
    // that list, further recipient choices are technically not possible.
    $otherInputs.readonly(state);

    $missingAddresses.toggleClass(disabledCssClass, state);
  };

  // Attach above function to almost every sensible control :)

  // Controls :..
  const controlsContainer = $fieldset.find('.filter-controls.' + appPrefix('container'));

  $instrumentsFilter
    .off('change')
    .on('change', function(event: JQuery.EventBase) {
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => readonlyFilterControls(false),
      });
    });

  // Member status filter
  $participationStatusFilter
    .off('change')
    .on('change', function(event: JQuery.EventBase) {
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => readonlyFilterControls(false),
      });
    });

  // Basic recipients set (from project, except project, use mailing lists)
  const $basicRecipientsSetContainer = $fieldset.find('.basic-recipients-set.' + appPrefix('container'));
  const $basicRecipientsSet = $basicRecipientsSetContainer.find('input[type="checkbox"], input[type="radio"]');
  const $basicRecipientsSetProject = $basicRecipientsSet.not('.mailing-list, .database');
  const $basicRecipientsSetMailingList = $basicRecipientsSet.filter('.mailing-list, .database');

  $basicRecipientsSetMailingList
    .off('change')
    .on('change', function(event: JQuery.EventBase) {
      const $this = $(this);
      const mailingListRecipients = $this.is('.mailing-list') && $this.prop('checked');

      if (mailingListRecipients) {
        $basicRecipientsSetMailingList.not(this).prop('checked', false);
        readonlyFilterControls(mailingListRecipients, ['.mailing-list', '.database']);
      } else {
        readonlyFilterControls(true);
        applyRecipientsFilter.call(this, event, {
          cleanup: () => readonlyFilterControls(false),
        });
      }
      $basicRecipientsSet.filter('.mailing-list').each(function() {
        const $radio = $(this);
        $basicRecipientsSetContainer.toggleClass($radio.val() as string, $radio.prop('checked'));
      });
      updateComposerElements($form, ['to', 'subjectTag']);
      return false;
    });

  $basicRecipientsSetProject
    .off('change')
    .on('change', function(event: JQuery.EventBase) {
      const $this = $(this);
      $basicRecipientsSetContainer.toggleClass($this.val()! as string, $this.prop('checked'));
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => {
          updateComposerElements($form, ['to', 'subjectTag']);
          readonlyFilterControls(false);
        },
      });
    });

  // initialization
  if ($basicRecipientsSet.filter('.mailing-list').prop('checked')) {
    console.info('MAILING LIST CONTROLS', {
      mlc: $basicRecipientsSet.filter('.mailing-list'),
      checked: $basicRecipientsSet.filter('.mailing-list').prop('checked'),
    });
    readonlyFilterControls(true, ['.mailing-list', '.database']);
  }

  // "submit" when hitting any of the control buttons
  controlsContainer
    .off('click', '**')
    .on('click', 'input', function(event) {
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => readonlyFilterControls(false),
      });
    });

  // Record history when the select box changes. Maybe too slow, but
  // we will see.
  $recipientsSelect
    .off('change')
    .on('change', function(event: JQuery.EventBase) {
      applyRecipientsFilter.call(this, event, { historySnapshot: true });
    });

  // Give the user a chance to change broken or missing email
  // addresses from here.
  $dialogHolder
    .off('pmedialog:changed')
    .on('pmedialog:changed', function(event) {
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => readonlyFilterControls(false),
      });
    });

  $missingAddresses
    .off('click', '.personal-record')
    .on('click', '.personal-record', function() {
      const $this = $(this);

      const musicianId = $this.data('musicianId');
      const isParticipant = $this.data('isParticipant');

      const $formData = $form.find<HTMLFieldSetElement>(`fieldset.${EmailFormCssClasses.FORM_DATA}`);
      const projectId = +($formData.find<HTMLInputElement>(`input[name="${PersistentCGIKeys.PROJECT_ID}"]`).val() ?? -1);
      const projectName = $formData.find<HTMLInputElement>(`input[name="${PersistentCGIKeys.PROJECT_NAME}"]`).val()!;

      participantRecordDialog(
        musicianId,
        {
          template: (projectId > 0 && isParticipant) ? projectParticipantsTemplate : allMusiciansTemplate,
          projectId,
          projectName,
          initialValue: 'Change',
          ambientContainerSelector: '#emailformdialog',
        },
      );

      return false;
    });

  $panelHolder
    .off('resize.' + appName)
    .on('resize.' + appName, function() {
      emailTabResize($dialogHolder.closest('.emailform-envelope'), $panelHolder);
      return false;
    });

  return false;
};

/**
 * Add handlers to the control elements, and call the AJAX scriplets
 * for validation to update the message composition tab accordingly.
 *
 * @param $fieldset The field-set enclosing the composition window part.
 *
 * @param $form TBD.
 *
 * @param $dialogHolder The div holding the jQuery dialog for everything
 *
 * @param $panelHolder The div enclosing the $fieldset
 */
const emailFormCompositionHandlers = (
  $fieldset: JQuery<HTMLFieldSetElement>,
  $form: JQuery<HTMLFormElement>,
  $dialogHolder: JQuery,
  $panelHolder: JQuery,
) => {

  // console.trace('COMPOSITION HANDLERS CALLED');

  const $formData = $form.find(`fieldset.${EmailFormCssClasses.FORM_DATA}`);
  const $projectId = $formData.find(`input[name="${PersistentCGIKeys.PROJECT_ID}"]`);
  const $projectName = $formData.find(`input[name="${PersistentCGIKeys.PROJECT_NAME}"]`);
  const $bulkTransactionId = $formData.find(`input[name="${ComposerCgiKeys.BULK_TRANSACTION_ID}"]`);
  const $donationReceiptId = $formData.find(`input[name="${ComposerCgiKeys.DONATION_RECEIPT_ID}"]`);
  const $invoiceId = $formData.find(`input[name="${ComposerCgiKeys.INVOICE_ID}"]`);
  const $debugOutput = $form.find('#emailformdebug');
  const $templateEmailsSelector = $fieldset.find(`select.${TEMPLATE_EMAIL_MESSAGE_SELECTOR}`) as JQuery<HTMLSelectElement>;
  const $draftEmailsSelector = $fieldset.find(`select.${DRAFT_EMAIL_MESSAGE_SELECTOR}`) as JQuery<HTMLSelectElement>;
  const $sentEmailsSelector = $fieldset.find(`select.${SENT_EMAIL_MESSAGE_SELECTOR}`) as JQuery<HTMLSelectElement>;
  const $saveAsTemplate = $fieldset.find('#check-save-as-template');
  const $draftAutoSave = $fieldset.find('#check-draft-auto-save');
  const $discloseRecipients = $fieldset.find('#check-disclosed-recipients');
  const $messageText = $fieldset.find('textarea');
  const $eventAttachmentsRow = $fieldset.find('tr.event-attachments');
  const $eventAttachmentsSelector = $eventAttachmentsRow.find('select#event-attachments-selector') as JQuery<HTMLSelectElement>;
  const $fileAttachmentsRow = $fieldset.find('tr.file-attachments') as JQuery<HTMLTableRowElement>;
  const $fileAttachmentsSelector = $fileAttachmentsRow.find('select#file-attachments-selector') as JQuery<HTMLSelectElement>;
  const $sendButton = $fieldset.find('input.submit.send') as JQuery<HTMLInputElement>;
  const $dialogWidget = $dialogHolder.closest('.emailform-envelope');
  const $composerPanel = $('#emailformcomposer');

  WysiwygEditor.addEditor($dialogHolder.find(`textarea.${WYSIWYG_EDITOR}`));

  const toggleSelectClass = (
    $select: JQuery<HTMLSelectElement>,
    cssClass: string,
    value?: VueSelectValue|boolean,
  ) => {
    value = !!value;
    SelectUtils
      .widget($select)
      .toggleClass(cssClass, value)
      .closest(`.${STORED_MESSAGES_CHOICES_CONTAINER}`).toggleClass(cssClass, value);
  };

  const messageSelectorOptions = ($select: JQuery<HTMLSelectElement>): SelectizeAdapterOptions => ({
    onOpen: () => toggleSelectClass($select, dropdownOpenCssClass, true),
    onClose: () => toggleSelectClass($select, dropdownOpenCssClass, false),
    onChange: (value) => toggleSelectClass($select, loadingCssClass, value),
    closeAfterSelect: true,
  });

  $templateEmailsSelector.data('unpersisted', {});
  mountEmailSelect($templateEmailsSelector, {
    ...messageSelectorOptions($templateEmailsSelector),
    restoreOnBackspace: true,
    create: true,
    persist: false,
    onCreate(option) {
      $templateEmailsSelector.data('ignoreChange', option.value);
      $templateEmailsSelector.data('unpersisted')[option.value] = true;
    },
  });
  toggleSelectClass($templateEmailsSelector, expandedCssClass, $saveAsTemplate.is(':checked'));
  mountEmailSelect($draftEmailsSelector, messageSelectorOptions($draftEmailsSelector));
  mountEmailSelect($sentEmailsSelector, messageSelectorOptions($sentEmailsSelector));

  mountEmailSelect($fileAttachmentsSelector.add($eventAttachmentsSelector), {
    ...multiSelectOptions,
    closeAfterSelect: false,
    onOpen() {
      $composerPanel.stop().animate({
        scrollTop: $composerPanel.prop('scrollHeight'),
      }, 2000);
    },
  });

  console.info('INITIALIZE TOOLTIPS');
  toolTipsInit($composerPanel);

  const projectId = (value?: number|string) => {
    if (value === undefined) {
      return +$projectId.val()!;
    } else {
      $projectId.val(value);
      value = +value;
      $form
        .toggleClass(projectModeCssClass, value > 0)
        .toggleClass(projectModeOffCssClass, !(value > 0));
      return value;
    }
  };
  projectId($projectId.val() as string); // ensure classes to be set

  const projectName = (value?: string) => {
    if (value === undefined) {
      return $projectName.val() as string ?? '';
    } else {
      $projectName.val(value);
      return value;
    }
  };
  const bulkTransactionId = (value?: string|number) => {
    if (value === undefined) {
      return +$bulkTransactionId.val()!;
    } else {
      $bulkTransactionId.val(value);
      return +value;
    }
  };

  // Event dispatcher, so to say
  const applyComposerControls = function<
    E extends HTMLElement,
    Operation extends ComposerOperation = ComposerOperation,
    Topic extends ComposerTopic = 'general',
  >(
    this: E,
    request: ComposerRequestData<Operation, Topic>,
    noDebug: boolean = false,
    validateLockCB: (lock: boolean) => void = (lock: boolean) => { pageBusyIcon(lock); },
  ) {

    $.fn.cafevTooltip.hide();

    const validateLock = function() {
      validateLockCB(true);
    };

    const validateUnlock = function() {
      validateLockCB(false);
    };

    // until end of validation
    validateLock();

    const url = generateComposerUrl(request);
    let post = '';
    if (request.singleItem) {
      // Only serialize the request, no need to post all data around.
      post = $.param({ [EnumPostTag.COMPOSER]: request });
    } else {
      if (request.submitAll) {
        // Everything is greedily submitted ...
        post = $form.serialize();
      } else {
        // Serialize almost everything and submit it
        post = $fieldset.serialize();
        post += '&' + $form.find(`fieldset.${EmailFormCssClasses.FORM_DATA}`).serialize();
      }
      const $this = $(this);
      if ($this.is(':button') || $this.is(':submit')) {
        post += '&' + $.param($this);
      }
      // add the request itself as data
      post += '&' + $.param({ [EnumPostTag.COMPOSER]: request });
    }

    if (!noDebug) {
      $debugOutput.html('');
    }
    $.post(url, post)
      .fail(function(xhr, textStatus, errorThrown) {
        ajaxHandleError(xhr, textStatus, errorThrown, function(data) {
          let debugText = '';
          if (data.caption !== undefined) {
            debugText += '<div class="error caption">' + data.caption + '</div>';
          }
          debugText += data.messages.join(' ');
          $debugOutput.html(debugText);
          validateUnlock();
        });
      })
      .done(function(data: ResponseData<EmailFormComposerResponse>) {
        let postponeEnable = false;
        $.fn.cafevTooltip.remove();
        if (!ajaxValidateResponse(
          data,
          ['projectId', 'projectName', 'operation', 'requestData'],
          validateUnlock,
        )) {
          return false;
        }

        projectId(data.projectId);

        const operation = data.operation;
        const topic = data.topic ?? 'general';
        const requestData = data.requestData;
        let message = Array.isArray(data.messages) ? data.messages.join(' ') : undefined;
        switch (operation) {
          case 'send':
            SelectUtils.replaceOptions($draftEmailsSelector, requestData.draftEmailOptions!);
            SelectUtils.replaceOptions($sentEmailsSelector, requestData.sentEmailOptions!);
            if (message !== undefined && data.caption !== undefined) {
              Dialogs.info(message, data.caption, undefined, true, true);
              $('body').find('.modal-wrapper--small')
                .toggleClass('.modal-wrapper--small', false)
                .toggleClass('.modal-wrapper--large', true);
            }
            break;
          case 'cancel':
            // status feed-back handled by general code.
            break;
          case 'update':
            switch (topic) {
              case Email.topicUnspecific:
                // replace the entire tab.
                $.fn.cafevTooltip.remove();
                WysiwygEditor.removeEditor($panelHolder.find(`textarea.${WYSIWYG_EDITOR}`));
                destroyMountedSelects($panelHolder[0]);
                $panelHolder.html(requestData.elementData! as string);
                $fieldset = $panelHolder.find('fieldset.email-composition.page');
                emailFormCompositionHandlers($fieldset, $form, $dialogHolder, $panelHolder);
                break;
              case 'element': {
                const elementData = requestData.elementData as Record<EnumEmailFormComposerElement, unknown>;
                for (const formElement of requestData.formElements!) {
                  switch (formElement) {
                    case 'to': {
                      let rcpts = (elementData[formElement] as string[]).join(', ');
                      const toSpan = $fieldset.find('span.email-recipients');
                      if (rcpts.length === 0) {
                        rcpts = toSpan.data('placeholder');
                      }
                      const title = toSpan.data('titleIntro') + '<br>' + rcpts;

                      toSpan.html(rcpts);
                      toSpan.attr('title', title);
                      toSpan.cafevTooltip();
                      break;
                    }
                    case 'fileAttachments': {
                      const data = elementData[formElement] as AttachmentElementData;
                      const options = data.options;
                      const fileAttachments = data.attachments;
                      const fileAttachmentsHolder = $fieldset.find('input.file-attachments');
                      fileAttachmentsHolder.val(JSON.stringify(fileAttachments));
                      $fileAttachmentsRow.toggleClass(emptySelectionCssClass, ($fileAttachmentsSelector.val() as string).length === 0);
                      $fileAttachmentsRow.toggleClass(noAttachmentsCssClass, options.length === 0);
                      SelectUtils.replaceOptions($fileAttachmentsSelector, options);
                      $panelHolder.trigger('resize', { position: 'bottom' });
                      break;
                    }
                    case 'eventAttachments': {
                      const data = elementData[formElement] as AttachmentElementData;
                      const options = data.options;
                      // const eventAttachments = requestData.elementData.attachments;
                      $eventAttachmentsRow.toggleClass(noAttachmentsCssClass, options.length === 0);
                      $eventAttachmentsRow.toggleClass(emptySelectionCssClass, ($eventAttachmentsSelector.val() as string).length === 0);
                      SelectUtils.replaceOptions($eventAttachmentsSelector, options);
                      $panelHolder.trigger('resize');
                      break;
                    }
                    default:
                      postponeEnable = true;
                      Dialogs.alert(
                        t(appName, 'Unknown form element: {formElement}', { formElement }),
                        t(appName, 'Error'),
                        validateUnlock,
                        true,
                        true,
                      );
                      break;
                  }
                }
                break; // element
              }
            }
            break; // update
          case 'validateEmailRecipients':
            // already reported by the general error-handling functions
            break;
          case 'load':
            switch (topic) {
              case 'template': {
                const dataItem = findComposerRequestInput($fieldset, 'messageDraftId');
                dataItem.val('');
                findComposerInput($fieldset, ComposerCgiKeys.REFERENCING, 'name^').remove();
                findComposerInput($fieldset, ComposerCgiKeys.IN_REPLY_TO, 'name^').val('');
                // currentTemplate.val(requestData.emailTemplateName);
                WysiwygEditor.updateEditor($messageText, requestData.messageText!);
                $fieldset.find('input.email-subject').val(requestData.subject!);

                toggleSelectClass($templateEmailsSelector, loadingCssClass, false);
                break;
              }
              case 'draft': {
                $.fn.cafevTooltip.remove();

                // replace the entire composer tab
                WysiwygEditor.removeEditor($panelHolder.find(`textarea.${WYSIWYG_EDITOR}`));
                destroyMountedSelects($panelHolder[0]);
                $panelHolder.html(requestData.composerForm!);
                $fieldset = $panelHolder.find('fieldset.email-composition.page');
                emailFormCompositionHandlers($fieldset, $form, $dialogHolder, $panelHolder);

                // replace the recipients tab as well ...
                const rcptPanelHolder = $dialogHolder.find('div#emailformrecipients');
                destroyMountedSelects(rcptPanelHolder[0]);
                rcptPanelHolder.html(requestData.recipientsForm!);
                const $rcptFieldSet = $form.find('fieldset.email-recipients.page') as JQuery<HTMLFieldSetElement>;
                emailFormRecipientsHandlers($rcptFieldSet, $form, $dialogHolder, rcptPanelHolder);

                // adjust the title of the dialog
                let dlgTitle: string;
                if ((requestData.projectId ?? 0) > 0) {
                  dlgTitle = t(appName, 'Em@il Form for {projectName}', { projectName: requestData.projectName! });
                } else {
                  dlgTitle = t(appName, 'Em@il Form');
                }
                asyncEmit(EMAIL_FORM_COMMAND, { action: 'title', title: dlgTitle });

                // update the "global" project name and id
                projectId(requestData.projectId);
                projectName(requestData.projectName);
                bulkTransactionId(requestData.bulkTransactionId);
                $donationReceiptId.val(requestData.donationReceiptId ?? 0);
                $invoiceId.val(requestData.invoiceId ?? 0);

                // Make the debug output less verbose
                delete requestData.composerForm;
                delete requestData.recipientsForm;

                // deselect menu item
                SelectUtils.deselectAll($draftEmailsSelector);
                break;
              }
              case 'sent': {
                $.fn.cafevTooltip.remove();

                // replace the entire composer tab
                WysiwygEditor.removeEditor($panelHolder.find(`textarea.${WYSIWYG_EDITOR}`));
                destroyMountedSelects($panelHolder[0]);
                $panelHolder.html(requestData.composerForm!);
                $fieldset = $panelHolder.find('fieldset.email-composition.page');
                emailFormCompositionHandlers($fieldset, $form, $dialogHolder, $panelHolder);

                // replace the recipients tab ...
                const rcptPanelHolder = $dialogHolder.find('div#emailformrecipients');
                destroyMountedSelects(rcptPanelHolder[0]);
                rcptPanelHolder.html(requestData.recipientsForm!);
                const $rcptFieldSet = $form.find('fieldset.email-recipients.page') as JQuery<HTMLFieldSetElement>;
                emailFormRecipientsHandlers($rcptFieldSet, $form, $dialogHolder, rcptPanelHolder);

                const dataItem = findComposerRequestInput($fieldset, 'messageDraftId');
                dataItem.val('');
                $saveAsTemplate.prop('checked', false).trigger('change');
                // WysiwygEditor.updateEditor($messageText, requestData.message);
                // $fieldset.find('input.email-subject').val(requestData.subject);

                // Make the debug output less verbose
                delete requestData.composerForm;
                delete requestData.recipientsForm;

                updateComposerElements($form);

                // deselect menu item
                SelectUtils.deselectAll($sentEmailsSelector);
                break;
              }
            }
            break; // load
          case 'save':
            switch (topic) {
              case 'template': {
                SelectUtils.replaceOptions($templateEmailsSelector, requestData.templateEmailOptions!);
                const persistedValue = requestData.templateMessagesSelector!;
                $templateEmailsSelector.data('unpersisted')[persistedValue] = false;
                break;
              }
              case 'draft': {
                // perhaps rather use data stuff in the future ...
                const dataItem = findComposerRequestInput($fieldset, 'messageDraftId');
                dataItem.val(requestData.messageDraftId!);
                SelectUtils.replaceOptions($draftEmailsSelector, requestData.draftEmailOptions!);
                break;
              }
            }
            break; // save
          case 'delete':
            switch (topic) {
              case 'template':
                // currentTemplate.val(requestData.emailTemplateName);
                WysiwygEditor.updateEditor($messageText, requestData.messageText!);
                SelectUtils.replaceOptions($templateEmailsSelector, requestData.templateEmailOptions!);
                break;
              case 'draft': {
                const dataItem = findComposerRequestInput($fieldset, 'messageDraftId');
                dataItem.val('');
                SelectUtils.replaceOptions($draftEmailsSelector, requestData.draftEmailOptions!);
                break;
              }
            }
            break; // delete
          default:
            postponeEnable = true;
            message = t(appName, 'Unknown request: {operation} / {topic}', { operation, topic });
            data.caption = t(appName, 'Error');
            Dialogs.alert(message, data.caption, validateUnlock, true, true);
            break;
        } // switch (operation)

        if (!noDebug) {
          let debugText = '';
          if (data.caption !== undefined) {
            debugText += '<div class="error caption">' + data.caption + '</div>';
          }
          if (message !== undefined) {
            debugText += message;
          }
          if (data.debug !== undefined) {
            debugText += '<pre>' + data.debug + '</pre>';
          }
          if (debugText !== '') {
            let addOn: string;
            addOn = JSON.stringify(Object.fromEntries(new URLSearchParams(post)), null, 2);
            addOn = $('<div></div>').text(addOn).html();
            debugText += '<pre>post = ' + addOn + '</pre>';
            addOn = JSON.stringify(requestData, null, 2);
            addOn = $('<div></div>').text(addOn).html();
            debugText += '<pre>requestData = ' + addOn + '</pre>';
            $debugOutput.html(debugText);
          }
        }

        if (!postponeEnable) {
          validateUnlock();
        }
      });
    return false;
  };

  /*************************************************************************
   *
   * Finally send the entire mess to the recipients
   */

  $sendButton
    .off('click')
    .on(
      'click',
      debounce(
        function(this: HTMLInputElement, _event: JQuery.ClickEvent) {
          const $this = $(this);

          // try to provide status feed-back for large transfers or
          // sending to many recipients. To this end we poll a special
          // data-base table. If not finished after 5 seconds, we pop-up a
          // dialog with status information.

          const progressWrapper = $dialogHolder.find('div#sendingprogresswrapper');

          type SendProgressData = {
            active: number;
            total: number;
            proto: 'imap';
          };

          const pollProgress: ProgressStatus.PollOptions['update'] = function(_id, current, target, data: SendProgressData) {
            const value = current;
            const max = target;
            const rel = value / max * 100.0;
            const progressTitle = data!.total > 1
              ? t(appName, 'Sending message {active} out of {total}', data)
              : t(appName, 'Message delivery in progress');
            progressWrapper.find('div.messagecount').html(progressTitle);
            if (data.proto !== 'imap') {
              progressWrapper.find('div.imap span.progressbar')
                .progressbar('option', 'value', 0);
            } else {
              // assume SMTP was finished, the left-over partial
              // progress-bar from too slowly-polled messages just is a
              // little bit disturbing.
              progressWrapper.find('div.smtp span.progressbar')
                .progressbar('option', 'value', 100);
            }
            progressWrapper.find('div.' + data.proto + ' span.progressbar')
              .progressbar('option', 'value', rel);
            return !(data.proto === 'imap' && rel === 100 && data.active === data.total);
          };

          // submit the progress status id with the send request to the server.
          ProgressStatus.create(0, 0, { proto: 'undefined', active: 0, total: -1 })
            .fail(ajaxHandleError)
            .done(function(data: ResponseData<ProgressResponse>) {
              if (!ajaxValidateResponse(data, ['id'])) {
                return;
              }
              const progressToken = data.id;
              let progressOpen = false;
              progressWrapper.find('span.progressbar').progressbar({ value: 0, max: 100 });
              progressWrapper.cafevDialog({
                title: t(appName, 'Message Delivery Status'),
                width: 'auto',
                height: 'auto',
                modal: true,
                closeOnEscape: false,
                resizable: false,
                dialogClass: 'emailform delivery progress no-close',
                open() {
                  progressOpen = true;
                  ProgressStatus.poll(progressToken, {
                    update: pollProgress,
                    fail(xhr, status, errorThrown) { return ajaxHandleError(xhr, status, errorThrown); },
                    interval: 500,
                  });
                },
                close() {
                  progressOpen = false;
                  ProgressStatus.poll.stop();
                  ProgressStatus.delete(progressToken);
                  progressWrapper.dialog('destroy');
                  progressWrapper.hide();
                },
              });

              applyComposerControls.call(
                $this[0],
                {
                  operation: 'send',
                  progressToken,
                  // send: 'ThePointOfNoReturn',
                  submitAll: true,
                  projectId: projectId(),
                  projectName: projectName(),
                },
                false, // noDebug
                function(lock: boolean) {
                  if (lock) {
                    pageBusyIcon(true);
                    $(window).on('beforeunload.emailform', function() {
                      return t(appName, 'Email sending is in progress. Leaving the page now will cancel the email submission.');
                    });
                    setEmailFormBlocked(true);
                  } else {
                    $(window).off('beforeunload.emailform');
                    if (progressOpen) {
                      progressWrapper.dialog('close');
                    }
                    setEmailFormBlocked(false);
                    pageBusyIcon(false);
                  }
                },
              );
            });
          return false;
        },
      ),
    );

  /*************************************************************************
   *
   * Message export to html.
   */
  $fieldset
    .find('input.submit.message-export')
    .off('click')
    .on('click', function() {

      // save a draft while entering the preview ...
      let busyCount = 2;
      pageBusyIcon(true);

      if (Email.autoSaveTimer) {
        clearTimeout(Email.autoSaveTimer);
        Email.autoSaveTimer = null;
      }
      applyComposerControls.call(
        $fieldset[0],
        {
          operation: 'save',
          topic: 'draft',
          submitAll: true,
          projectId: projectId(),
          projectName: projectName(),
          autoSave: true,
        },
        true, // noDebug
        function(lock) {
          if (lock) {
            return;
          }
          startDraftAutoSave($draftAutoSave);
          if (--busyCount <= 0) {
            pageBusyIcon(false);
          }
        },
      );

      const post = $form.serialize();

      $.post(generateComposerUrl('preview'), post)
        .fail(function(xhr, textStatus, errorThrown) {
          ajaxHandleError<EmailFormComposerResponse>(
            xhr,
            textStatus,
            errorThrown,
            {
              preProcess: (data) => {
                const requestData = data.requestData;
                const previewData: undefined|string = requestData?.previewData;
                if (previewData) {
                  // avoid showing the error summary as this add
                  // another modal which has to be closed by the user.
                  // We still show the details page, then on closing
                  // it the preview tab will open.
                  data.closeDetailsLabel = t(appName, 'Proceed to the email preview');
                }
              },
              cleanup: (data) => {
                let previewText = '';
                if (data.caption !== undefined) {
                  previewText += '<div class="error caption">' + data.caption + '</div>';
                }
                if (Array.isArray(data.messages)) {
                  previewText += data.messages.join(' ');
                }
                const requestData = data.requestData;
                const previewData: undefined|string = requestData?.previewData;
                if (previewData) {
                  previewText += previewData;
                }
                $debugOutput.html(previewText);
                $debugOutput.find('.for-dialog').addClass(hiddenCssClass);

                if (previewData) {
                  selectEmailTab('status');
                }

                $.fn.cafevTooltip.remove();

                if (requestData?.messageTextReplacements) {
                  WysiwygEditor.updateEditor($messageText, requestData.messageText!);
                }

                if (--busyCount <= 0) {
                  pageBusyIcon(false);
                }
              },
            },
          );
        })
        .done(function(data: EmailFormComposerResponse) {
          if (!ajaxValidateResponse(
            data,
            ['requestData'],
            function() {
              if (--busyCount <= 0) {
                pageBusyIcon(false);
              }
            },
          )) {
            return;
          }
          const requestData = data.requestData!;
          if (!ajaxValidateResponse(
            data.requestData,
            ['previewData'],
            function() {
              if (--busyCount <= 0) {
                pageBusyIcon(false);
              }
            },
          )) {
            return;
          }

          let previewText = '';
          if (Array.isArray(data.messages)) {
            previewText += `<div class="error hints">${data.messages.join('</div><div class="error hints">')}</div>`;
          }
          previewText += requestData.previewData;
          $debugOutput.html(previewText);

          selectEmailTab('status');

          $.fn.cafevTooltip.remove();

          if (requestData?.messageTextReplacements) {
            WysiwygEditor.updateEditor($messageText, requestData.messageText!);
          }

          if (--busyCount <= 0) {
            pageBusyIcon(false);
          }
        });
      return false;
    });

  /*************************************************************************
   *
   * Close the dialog
   */

  $fieldset
    .find('input.submit.cancel')
    .off('click')
    .on('click', function() {
      applyComposerControls.call(
        this,
        {
          operation: 'cancel',
          formStatus: 'submitted',
          singleItem: true,
          projectId: projectId(),
          projectName: projectName(),
        },
      );
      // Close the dialog in any case.
      closeEmailForm();
      return false;
    });

  /*************************************************************************
   *
   * Template handling (save, delete, load)
   */

  const autoSaveSeconds = $draftAutoSave.data('auto-save-interval') || 300;
  const autoSaveTimeout = autoSaveSeconds * 1000;

  const autoSaveHandler = () => {
    if (!$fieldset[0].isConnected) {
      return;
    }
    if (Email.autoSaveTimer || !$fieldset[0].isConnected) {
      clearTimeout(Email.autoSaveTimer);
    }
    Email.autoSaveTimer = null;
    applyComposerControls.call(
      $fieldset[0],
      {
        operation: 'save',
        topic: 'draft',
        submitAll: true,
        projectId: projectId(),
        projectName: projectName(),
        autoSave: true,
      },
      true,
      function(lock) {
        pageBusyIcon(lock);
        if (!lock) {
          // restart timer when ready
          if (Email.autoSaveTimer) {
            return;
          }
          Email.autoSaveTimer = setTimeout(autoSaveHandler, autoSaveTimeout);
        }
      },
    );
  };

  const confirmAutoSaveDelete = function(doDelete: boolean = false) {
    const draftId = parseInt('' + findComposerRequestInput($fieldset, 'messageDraftId').val());
    if (draftId <= 0) {
      console.info('DRAFT ID IS NOT THERE', { draftId });
      return;
    }
    const $draftOption = SelectUtils.options($draftEmailsSelector).filter(`option[value="${draftId}"]`);
    const autoGenerated = +($draftOption.attr('data-auto-generated') ?? 0);
    console.info('DRAFT AUTO', { autoGenerated, $draftOption });
    if (autoGenerated && (doDelete || $draftAutoSave.prop('checked'))) {
      Dialogs.confirm(
        t(appName, 'Do you want to delete the auto-save backup copy of the current message-draft (id = {id})?'
          + '<br/>'
          + 'If you answer "no" then the current message will be saved again and marked as manually saved. '
          + 'It will then linger on until you or someone else deletes it manually.', { id: draftId }),
        t(appName, 'Delete Auto-Save Draft?'),
        function(confirmed) {
          if (confirmed) {
            applyComposerControls.call(
              $fieldset[0],
              {
                operation: 'delete',
                topic: 'draft',
                messageDraftId: draftId,
                projectId: projectId(),
                projectName: projectName(),
              },
              true, // noDebug
            );
          } else {
            // perform a manual save to clear the "autoGenerated" flag
            applyComposerControls.call(
              $fieldset[0],
              {
                operation: 'save',
                topic: 'draft',
                submitAll: true,
                projectId: projectId(),
                projectName: projectName(),
                autoSave: false,
              },
              true, // noDebug
            );
          }
        },
        true,
        true,
      );
    }
  };
  Email.autoSaveDelete = confirmAutoSaveDelete;

  /** @param $element TBD. */
  function startDraftAutoSave($element: JQuery) {
    if (Email.autoSaveTimer) {
      clearTimeout(Email.autoSaveTimer);
      Email.autoSaveTimer = null;
    }
    if ($element.prop('checked')) {
      // perhaps add a popup to set the auto-save timeout
      Email.autoSaveTimer = setTimeout(autoSaveHandler, autoSaveTimeout);
    }
  }

  $draftAutoSave
    .off('change')
    .on('change', function(_event: JQuery.EventBase) {
      const $this = $(this);
      startDraftAutoSave($this);
      $.post(setPersonalUrl('emailDraftAutoSave'), {
        value: $this.prop('checked') ? autoSaveSeconds : 0,
      })
        .fail(function(xhr, status, errorThrown) {
          ajaxHandleError(xhr, status, errorThrown, () => {
            $this.prop('checked', !$this.prop('checked'));
          });
        })
        .done(function(data) {
          if (data.message) {
            const message = $this.prop('checked')
              ? t(appName, 'Draft-auto-save interval set to {seconds} seconds.', { seconds: autoSaveSeconds })
              : t(appName, 'Draft-auto-save switched off');
            notificationShow(message, { timeout: 15 });
          }
          if (!$this.prop('checked')) {
            confirmAutoSaveDelete(true);
          }
        });
      return false;
    });

  startDraftAutoSave($draftAutoSave);

  $saveAsTemplate
    .off('change')
    .on('change', function() {
      toggleSelectClass($templateEmailsSelector, expandedCssClass, $saveAsTemplate.is(':checked'));
      return false;
    });

  $fieldset
    .find('input.submit.save-message')
    .off('click')
    .on('click', function(_event) {
      // eslint-disable-next-line @typescript-eslint/no-this-alias
      const self = this;
      if ($saveAsTemplate.is(':checked')) {
        const request = { operation: 'save', topic: 'template', submitAll: true } as const;
        // We do a quick client-side validation and ask the user for ok
        // when a template with the same name is already present.
        const current = SelectUtils.selected($templateEmailsSelector);
        if (!$templateEmailsSelector.data('unpersisted')[String(current)]
            && SelectUtils.options($templateEmailsSelector).filter(function() { return $(this).html() === current; }).length > 0) {
          Dialogs.confirm(
            t(appName, 'A template with the name `{emailTemplateName}\' already exists, '
              + 'do you want to overwrite it?', { emailTemplateName: current }),
            t(appName, 'Overwrite existing template?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, request);
              }
            },
            true,
          );
        } else {
          applyComposerControls.call(self, request);
        }
      } else {
        applyComposerControls.call(
          self,
          {
            operation: 'save',
            topic: 'draft',
            submitAll: true,
            projectId: projectId(),
            projectName: projectName(),
            autoSave: false,
          },
          true, // noDebug
        );
      }
      return false;
    });

  $fieldset
    .find('input.submit.delete-message')
    .off('click')
    .on('click', function(_event) {
      // eslint-disable-next-line @typescript-eslint/no-this-alias
      const self = this;

      if ($saveAsTemplate.is(':checked')) {
        // We do a quick client-side validation and ask the user for ok.
        const current = SelectUtils.selected($templateEmailsSelector) as string;
        if (SelectUtils.options($templateEmailsSelector).filter(function() {
          return $(this).html().trim() === current;
        }).length > 0) {
          Dialogs.confirm(
            t(appName, 'Do you really want to delete the template with the name "`{emailTemplateName}"?', { emailTemplateName: current }),
            t(appName, 'Really Delete Template?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, {
                  operation: 'delete',
                  topic: 'template',
                  projectId: projectId(),
                  projectName: projectName(),
                });
              }
            },
            true,
          );
        } else {
          Dialogs.alert(
            t(appName, 'Cannot delete non-existing template `{emailTemplateName}\'', { emailTemplateName: current }),
            t(appName, 'Unknown Template'),
          );
        }
      } else {
        const draftId = +(findComposerRequestInput($fieldset, 'messageDraftId').val()! as string);

        if (draftId > 0) {
          // find the draft data in the select which we mis-use as data-storage here
          const $draftOption = SelectUtils.optionByValue($draftEmailsSelector, draftId);
          let draftMeta = '';
          if ($draftOption.length === 1) {
            const title = $draftOption.attr('title') || $draftOption.attr('data-original-title') || $draftOption.html();
            draftMeta = '<br/>' + title;
          }
          Dialogs.confirm(
            t(appName, 'Do you really want to delete the backup copy of the current message (id = {id})?', { id: draftId })
              + draftMeta,
            t(appName, 'Really Delete Draft?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, {
                  operation: 'delete',
                  topic: 'draft',
                  messageDraftId: draftId,
                  projectId: projectId(),
                  projectName: projectName(),
                });
              }
            },
            true,
            true,
          );
        }
      }
      return false;
    });

  $draftEmailsSelector
    .off('change')
    .on('change', function(_event: JQuery.EventBase) {

      const choice = $draftEmailsSelector.val()! as string;
      if (choice.match(/-1/)) {
        Dialogs.alert(
          t(appName, 'There are currently no stored draft messages available.'),
          t(appName, 'No Drafts Available'),
          function() {
            SelectUtils.deselectAll($draftEmailsSelector);
          },
        );
      } else {
        applyComposerControls.call(
          this,
          {
            operation: 'load',
            topic: 'draft',
            projectId: projectId(),
            projectName: projectName(),
          },
          false, // noDebug
          function(lock: boolean) {
            if (lock) {
              pageBusyIcon(true);
              setEmailFormBlocked(true);
            } else {
              SelectUtils.deselectAll($draftEmailsSelector);
              setEmailFormBlocked(false);
              asyncEmit(EMAIL_FORM_COMMAND, { action: 'enable-recipients' });
              pageBusyIcon(false);
            }
          },
        );
      }
      return false;
    });

  $templateEmailsSelector
    .off('change')
    .on('change', function(_event: JQuery.EventBase) {
      // console.info('CHANGE', this);
      const $this = $(this);

      if ($this.val() && $this.val() !== $this.data('ignoreChange')) {
        applyComposerControls.call(this, {
          operation: 'load',
          topic: 'template',
          projectId: projectId(),
          projectName: projectName(),
        });
      } else {
        toggleSelectClass($templateEmailsSelector, loadingCssClass, false);
        $this.removeData('ignoreChange');
      }

      return false;
    });

  $sentEmailsSelector
    .off('change')
    .on('change', function(_event: JQuery.EventBase) {

      applyComposerControls.call(
        this,
        {
          operation: 'load',
          topic: 'sent',
          projectId: projectId(),
          projectName: projectName(),
        },
        false, // noDebug
        function(lock: boolean) {
          if (lock) {
            pageBusyIcon(true);
            setEmailFormBlocked(true);
          } else {
            SelectUtils.deselectAll($sentEmailsSelector);
            setEmailFormBlocked(false);
            asyncEmit(EMAIL_FORM_COMMAND, { action: 'enable-recipients' });
            pageBusyIcon(false);
          }
        },
      );
      return false;
    });

  /*************************************************************************
   *
   * Subject and sender name. We simply trim the spaces away.
   */
  $fieldset
    .off('blur', 'input.email-subject, input.sender-name')
    .on(
      'blur',
      'input.email-subject, input.sender-name',
      function() {
        const $self = $(this);
        $self.val($self.val().trim());
        return false;
      },
    );

  /**************************************************************************
   *
   */

  $fieldset.find('input.save-from-tag')
    .off('click')
    .on('click', function() {
      const $selectedFrom = $(this).closest('td.email-from').find<HTMLInputElement>('input[type=radio]:checked');
      const sender = $selectedFrom.next().text().trim();
      const configKey = EnumPersonalSettingsKey.DEFAULT_EMAIL_FROM_ADDRESS;
      const configValue = $selectedFrom.val() as EnumFromTag;
      const message = configValue === 'personal'
        ? t(appName, 'Click "Yes" to use the selected personal sender address "{sender}" as default sender for future email communications.', { sender })
        : t(appName, 'Click "Yes" to use the selected "global" sender address "{sender}" as default sender for future email communications.', { sender });
      Dialogs.confirm(
        message,
        t(appName, 'Remember the sender choice?'),
        {
          callback(answer) {
            if (!answer) {
              return;
            }
            $.post(
              generateOcsUrl('/apps/provisioning_api/api/v1/config/users/{appName}/{configKey}', {
                appName,
                configKey,
              }),
              { configValue },
            )
              .fail(ajaxHandleError)
              .done(function(response) {
                console.debug('RESPONSE', { response });
                showSuccess(t(appName, 'Preference "{configKey}" set to "{senderTag}".', {
                  configKey,
                  senderTag: t(appName, configValue),
                }));
              });
          },
          allowHtml: true,
        },
      );
      return false;
    });

  /**************************************************************************
   *
   */
  $discloseRecipients
    .off('change')
    .on('change', function(_event: JQuery.EventBase) {
      const $this = $(this);

      if ($this.prop('checked')) {
        Dialogs.confirm(
          t(appName, 'Do you really want to disclose the bulk-message recipients?'
            + ' This may violate privacy regulations.'),
          t(appName, 'Really disclose the recipients?'),
          function(confirmed) {
            $this.prop('checked', confirmed);
          },
          true,
          true,
        );
        return false;
      }
      return true;
    });

  /**
   * Validate Cc: and Bcc: entries.
   *
   * @param _event TBD.
   *
   * @param header TBD.
   */
  const carbonCopyBlur = function(this: HTMLElement, _event: JQuery.BlurEvent, header: string) {
    const $self = $(this);
    const request = {
      operation: 'validateEmailRecipients',
      recipients: $self.val() as string|undefined,
      header,
      [header]: $self.val() as string|undefined, // remove duplicate later
      singleItem: true,
      projectId: projectId(),
      projectName: projectName(),
    } as const;
    applyComposerControls.call(
      this,
      request,
      false, // noDebug
      function(lock: boolean) {
        pageBusyIcon(lock);
        $sendButton.prop('disabled', lock);
        $self.prop('disabled', lock);
        // if (lock) {
        //   self.off('blur');
        // } else {
        //   self.on('blur', function(event) {
        //     carbonCopyBlur.call(this, event, header);
        //   });
        // }
      },
    );
    return false;
  };

  $fieldset
    .find('#carbon-copy')
    .off('blur')
    .on('blur', function(event) {
      return carbonCopyBlur.call(this, event, 'CC');
    });
  $fieldset
    .find('#blind-carbon-copy')
    .off('blur')
    .on('blur', function(event) {
      return carbonCopyBlur.call(this, event, 'BCC');
    });

  const $subjectTagContainer = $fieldset.find('.subject.tag.container');
  const $subjectTagContentDisplay = $subjectTagContainer.find('.content .display');
  const $subjectTagContentEditable = $subjectTagContainer.find('.content .editable');
  $subjectTagContainer
    .find('.button.edit-subject-tag')
    .off('click')
    .on('click', function() {
      $subjectTagContainer.removeClass(displayCssClass).addClass(editCssClass);
      return false;
    });
  $subjectTagContentEditable
    .off('blur')
    .on('blur', function() {
      $subjectTagContentDisplay.html($subjectTagContentEditable.val() as string);
      $subjectTagContainer.addClass(displayCssClass).removeClass(editCssClass);
      return false;
    });

  /*************************************************************************
   *
   * Project events attachments
   */

  $fieldset
    .find('button.attachment.events')
    .off('click')
    .on('click', function() {
      const wasVisible = $eventAttachmentsRow.is(':visible');
      const events = ($eventAttachmentsSelector.val() as undefined|string[]) ?? [];
      $eventAttachmentsRow.addClass(showSelectableCssClass);

      if (wasVisible !== $eventAttachmentsRow.is(':visible')) {
        $panelHolder.trigger('resize', { position: 'bottom' });
      }

      asyncEmit(
        PROJECT_EVENTS_LISTING,
        {
          projectName: projectName(),
        },
      ).then(() => {
        if (events.length > 0) {
          asyncEmit(LEGACY_UPDATE_EVENTS_SELECTION, {
            origin: 'EmailForm',
            projectId: projectId(),
            projectName: projectName(),
            selection: events,
          });
        }
      });

      return false;
    });

  const updateEventAttachments = (projectId: number, projectName: string, events: string[]) => {
    const requestData = {
      operation: 'update' as const,
      topic: 'element' as const,
      formElements: ['eventAttachments' as const],
      singleItem: true,
      attachedEvents: events,
      projectId,
      projectName,
    };
    applyComposerControls.call($dialogHolder[0], requestData);
  };

  // Update our selected events on request
  $dialogHolder
    .off(appName + ':events_changed')
    .on(appName + ':events_changed', function(_event, events: string[]) {
      updateEventAttachments(projectId(), projectName(), events);
      return false;
    });

  $fieldset
    .find('tr.attachments input.visibility-toggle')
    .off('click')
    .on('click', function() {
      const $this = $(this);
      const $row = $this.closest('tr');
      $row.removeClass(showSelectableCssClass).addClass(hiddenCssClass);
      $panelHolder.trigger('resize', { position: 'bottom' });
    });

  $fieldset
    .find('tr.all-attachments button.visibility-toggle')
    .off('click')
    .on('click', function() {
      const $attachmentRows = $('tr.attachments');
      if ($attachmentRows.filter(':visible').length > 0) {
        $attachmentRows.removeClass(showSelectableCssClass).addClass(hiddenCssClass);
      } else {
        $attachmentRows.addClass(showSelectableCssClass).removeClass(hiddenCssClass);
      }
      $panelHolder.trigger('resize', { position: 'bottom' });
    });

  $fieldset
    .find('input.delete-all-event-attachments')
    .off('click')
    .on('click', function() {
      const wasVisible = $eventAttachmentsRow.is(':visible');

      const numSelected = $eventAttachmentsSelector.val()!.length;
      const numOptions = $eventAttachmentsSelector.find('option').length;

      // must this be here?
      $eventAttachmentsRow.toggleClass(noAttachmentsCssClass, numOptions === 0);

      if (numSelected === 0) {
        $eventAttachmentsRow.removeClass(showSelectableCssClass);
        if (wasVisible !== $eventAttachmentsRow.is(':visible')) {
          $panelHolder.trigger('resize', { position: 'bottom' });
        }
      } else {
        // Ask for confirmation
        Dialogs.confirm(
          t(appName, 'Do you really want to delete all event attachments?'),
          t(appName, 'Really Delete Attachments?'),
          function(confirmed) {
            if (!confirmed) {
              return false;
            }
            // simply void the selection
            SelectUtils.deselectAll($eventAttachmentsSelector);
            $eventAttachmentsRow.removeClass(showSelectableCssClass);
            $eventAttachmentsRow.addClass(emptySelectionCssClass);

            if (wasVisible !== $eventAttachmentsRow.is(':visible')) {
              $panelHolder.trigger('resize', { position: 'bottom' });
            }

            return false;
          },
          true,
        );
      }

      return false;
    });

  $eventAttachmentsSelector
    .off('change')
    .on('change', function(_event: JQuery.EventBase) {
      const $this = $(this);
      const events = ($this.val() ?? []) as string[];
      $this.closest('tr')
        .toggleClass(emptySelectionCssClass, events.length === 0)
        .toggleClass(noAttachmentsCssClass, $this.find('option').length === 0);
      asyncEmit(LEGACY_UPDATE_EVENTS_SELECTION, {
        origin: 'EmailForm',
        projectId: projectId(),
        projectName: projectName(),
        selection: events,
      });
      return false;
    });

  /*************************************************************************
   *
   * File upload.
   */

  $fileAttachmentsSelector.on('change', function(_event: JQuery.EventBase) {
    const $this = $(this);
    $this.closest('tr')
      .toggleClass(emptySelectionCssClass, $this.val()!.length === 0)
      .toggleClass(noAttachmentsCssClass, $this.find('option').length === 0);
    return false;
  });

  const updateFileAttachments = () => {
    const fileAttachments = $fieldset.find('input.file-attachments').val() as string ?? '';
    const selectedAttachments = $fileAttachmentsSelector.val() as string[];

    const requestData: ComposerRequestData = {
      operation: 'update',
      topic: 'element',
      singleItem: true,
      formElements: ['fileAttachments'],
      fileAttachments, // JSON data of all files
      formStatus: 'submitted',
      projectId: projectId(),
      projectName: projectName(),
    };
    if (selectedAttachments) {
      requestData.attachedFiles = selectedAttachments;
    }
    applyComposerControls.call($fieldset[0], requestData);
    return false;
  };

  // Arguably, these should only be active if the
  // composer tab is active. Mmmh.
  fileUploadInit({
    url: generateEmailFormUrl(`${END_POINT_ATTACHMENT}/${EnumAttachmentOrigin.UPLOAD}`),
    startCallback: () => setEmailFormBlocked(true),
    doneCallback(json, _index, _container) {
      attachmentFromJSON(json);
    },
    stopCallback() {
      setEmailFormBlocked(false);
      updateFileAttachments();
    },
    dropZone: undefined, // initially disabled, enabled on tab-switch
    inputSelector: '#attachment_upload_start',
    containerSelector: '#attachment_upload_wrapper',
  });

  $fieldset
    .find('.attachment.upload')
    .off('click')
    .on('click', function() {
      $('#attachment_upload_start').trigger('click');
      return false;
    });

  $fieldset
    .find('.attachment.cloud')
    .off('click')
    .on('click', function() {
      const folderPromise = projectId() > 0
        ? $.get(generateAppUrl(`${projectsEndPoint}/${projectId()}/${GET_PROJECT_FOLDER}/${FOLDER_TYPE_PROJECT}`)).promise()
        : $.Deferred().resolve({ folder: globalState.sharedFolder }).promise();

      folderPromise
        .fail(function(xhr, status, errorThrown) {
          ajaxHandleError(xhr, status, errorThrown);
        })
        .done(function(data) {
          Dialogs.filePicker({
            title: t(appName, 'Select Attachment'),
            callback(paths) {
              cloudAttachment(paths, updateFileAttachments);
            },
            modal: true,
            multiple: true,
            startPath: data.folder,
          });
        });
    });

  $fieldset
    .find('.attachment.personal')
    .off('click')
    .on('click', function() {
      const wasVisible = $fileAttachmentsRow.is(':visible');
      $fileAttachmentsRow.addClass(showSelectableCssClass);
      if (wasVisible !== $fileAttachmentsRow.is(':visible')) {
        $panelHolder.trigger('resize', { position: 'bottom' });
      }
      return false;
    });

  $fieldset
    .find('input.delete-all-file-attachments')
    .off('click')
    .on('click', function() {
      const wasVisible = $fileAttachmentsRow.is(':visible');

      const numSelected = $fileAttachmentsSelector.val()!.length;
      const numOptions = $fileAttachmentsSelector.find('option').length;

      $fileAttachmentsRow.toggleClass(noAttachmentsCssClass, numOptions === 0);

      if (numSelected === 0) {
        $fileAttachmentsRow.removeClass(showSelectableCssClass);
        if (wasVisible !== $fileAttachmentsRow.is(':visible')) {
          $panelHolder.trigger('resize', { position: 'bottom' });
        }
      } else {
        // Ask for confirmation
        Dialogs.confirm(
          t(appName, 'Do you really want to delete all file attachments?'),
          t(appName, 'Really Delete Attachments?'),
          function(confirmed) {
            if (!confirmed) {
              return false;
            }
            // simply void the selection
            SelectUtils.deselectAll($fileAttachmentsSelector);
            $fileAttachmentsRow.removeClass(showSelectableCssClass);
            $fileAttachmentsRow.addClass(emptySelectionCssClass);

            if (wasVisible !== $fileAttachmentsRow.is(':visible')) {
              $panelHolder.trigger('resize', { position: 'bottom' });
            }
            return false;
          },
          true,
        );
      }

      return false;
    });

  /*************************************************************************
   *
   * We try to be nice with Cc: and Bcc: and even provide an
   * address-book connector
   */
  $fieldset
    .find('input.address-book-emails')
    .off('click')
    .on('click', function() {

      const $self = $(this);
      const $input = $fieldset.find($self.data('for'));

      $self.addClass(loadingCssClass);
      const cleanup = () => {
        $self.removeClass(loadingCssClass);
      };

      if (($input.val() as string).trim() !== '') {
        // We trigger validation before we pop-up, but no need to do
        // so on empty input.
        $input.trigger('blur');
      }

      const post = { freeFormRecipients: $input.val() };
      $.post(generateEmailFormUrl(`${END_POINT_CONTACTS}/${EnumEmailFormContactsOperation.LIST}`), post)
        .fail(function(xhr, status, errorThrown) {
          ajaxHandleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data: ResponseData<EmailFormListContactsResponse>) {
          if (!ajaxValidateResponse(data, ['contents'])) {
            cleanup();
            return;
          }

          const freeFormLabel = t(appName, 'Form Input');

          let $addressBookSelect: JQuery<HTMLSelectElement>;
          selectPopup(data.contents, {
            title: t(appName, 'Address Book'),
            saveText: t(appName, 'Accept'),
            select: addressBookSelectOptions(
              `${validationEndPoint}/${EnumMusicianValidationTopic.EMAIL}`,
              pmeData('email'),
              freeFormLabel,
              () => $addressBookSelect.closest('.ui-dialog')
                .find('button.save-contacts').prop('disabled', false),
            ),
            buttons: [
              {
                // "text" is documented, however, this is just the
                // "text" content of the generated button
                // node. Likewise we could as well inject HTML by
                // exchanging "text" by "html". Buggy docs: useLabel
                // and useText are no-ops.
                text: t(appName, 'Save Contacts'),
                class: 'save-contacts',
                title: t(
                  appName,
                  'Save the selected supplementary emails to the address-book for later reusal.',
                ),
                click() {
                  const $dialogHolder = $(this);
                  const $selectElement = $dialogHolder.find<HTMLSelectElement>('select');
                  console.info('SELECTED EMAIL OPTIONS', SelectUtils.selectedOptions($selectElement));
                  const selectedValues = SelectUtils.selected($selectElement)!;
                  const selectedFreeForm = selectedFreeFormContacts(
                    $selectElement[0],
                    Array.isArray(selectedValues) ? selectedValues : [selectedValues],
                  );
                  const innerPost = { addressBookCandidates: selectedFreeForm };
                  $.post(generateEmailFormUrl(`${END_POINT_CONTACTS}/${EnumEmailFormContactsOperation.SAVE}`), innerPost)
                    .fail(ajaxHandleError)
                    .done(function(_data) {
                      $.post(generateEmailFormUrl(`${END_POINT_CONTACTS}/${EnumEmailFormContactsOperation.LIST}`), post)
                        .fail(ajaxHandleError)
                        .done(function(data: ResponseData<EmailFormListContactsResponse>) {
                          if (!ajaxValidateResponse(data, ['contents'])) {
                            return;
                          }
                          const newOptions = data.contents;
                          SelectUtils.replaceOptions($selectElement, newOptions);
                          SelectUtils.selected($selectElement, selectedValues);
                          $dialogHolder.dialog('widget').find('button.save-contacts').prop('disabled', true);
                        });
                    });
                },
              },
            ],
            dialogClass: 'address-book-emails',
            position: {
              my: 'right top',
              at: 'right bottom',
              of: $self,
            },
            openCallback($selectElement) {
              $addressBookSelect = $selectElement;
              cleanup();
              if (SelectUtils.children($selectElement).filter('optgroup.free-form').length === 0) {
                $(this).dialog('widget')
                  .find('button.save-contacts').prop('disabled', true);
              }
            },
            closeCallback() {
              cleanup();
            },
            saveCallback(_$selectElement, selectedOptions) {
              let recipients = '';
              const numSelected = selectedOptions.length;
              if (numSelected > 0) {
                recipients += selectedOptions[0].text;
                for (let idx = 1; idx < numSelected; ++idx) {
                  recipients += ', ' + selectedOptions[idx].text;
                }
              }
              $input.val(recipients);
              $input.trigger('blur');
              $(this).dialog('close');
            },
            // ,closeCallback: function(selectElement) {}
          });
          return false;
        });
      return false;
    });

  /*************************************************************************
   *
   * The usual resize madness with dialog popups
   */

  $panelHolder.off('resize.' + appName);
  $panelHolder.on('resize.' + appName, function(_event, eventData) {
    //    const eventData = event.data;
    emailTabResize($dialogWidget, $panelHolder);
    if (eventData && eventData.position === 'bottom') {
      $panelHolder.scrollTop($panelHolder.prop('scrollHeight'));
    }
    return false;
  });
};

/**
 * Open the mass-email form in a popup window.
 *
 * @param post Necessary post data, either serialized or as
 * object. In principle post can be empty. For project emails the
 * following two fields are necessary:
 *
 * - projectId: the id
 * - projectName: the name of the project (obsolete: Project)
 *
 * Optional pre-selected ids for email recipients:
 *
 * - PME_sys_mrecs: array of ids of pre-selected musicians
 *
 * - selectedEvents: array of ids of events to attach.
 *
 * @param _modal Retained for legacy callers; the envelope fills the app viewport.
 *
 * @param single TBD, default false.
 *
 * @param afterInit Callback with optional boolean arg indicating the
   error status. If the success arg is undefined then the dialog is
   already open.
 */
const emailFormPopup = (post: string|JQuery.PlainObject, _modal: boolean, single: boolean, afterInit: (success?: boolean) => void = () => {}) => {

  const { promise, resolve, reject } = Promise.withResolvers<boolean>();

  const promiseCallback = <E extends Error>(arg?: boolean|E) => {
    if (arg instanceof Error) {
      afterInit(false);
      reject(arg);
    } else {
      afterInit(arg);
      resolve(arg !== undefined);
    }
  };

  if (Email.active === true) {
    console.debug('EMAIL ALREADY ACTIVE', Email);
    showEmailForm();
    promiseCallback();
    return promise;
  }

  Email.active = true;
  console.debug('EMAIL ACTIVE TRUE', Email);

  if (single === undefined) {
    single = false;
  }
  $.post(generateEmailFormUrl(END_POINT_FORM), post)
    .fail(function(xhr, status, errorThrown) {
      ajaxHandleError(xhr, status, errorThrown, function() {
        Email.active = false;
        console.debug('EMAIL ACTIVE FALSE', Email);
        promiseCallback(new Error('Error opening email dialog.'));
      });
    })
    .done(async function(data: ResponseData<EmailWebFormResponse>) {
      if (!ajaxValidateResponse(
        data,
        ['recipients', 'composer', 'formData', 'uploadAction', 'requestToken', 'uploadMaxHumanFilesize', 'wikiNamespace'],
        () => {
          Email.active = false;
          console.debug('EMAIL ACTIVE FALSE', Email);
          promiseCallback(new Error('Missing HTML content for email form.'));
        },
      )) {
        return;
      }

      let dlgTitle: string;
      if ((data.projectId ?? 0) > 0) {
        dlgTitle = t(appName, 'Em@il Form for {projectName}', { projectName: data.projectName! });
      } else {
        dlgTitle = t(appName, 'Em@il Form');
      }

      let $dialogHolder: JQuery<HTMLElement>|undefined;
      let resizeObserver: ResizeObserver|undefined;
      let removalObserver: MutationObserver|undefined;
      Email.activeTab = single ? 'composer' : 'recipients';
      const cleanup = (container: HTMLElement) => {
        asyncUnsubscribe(EMAIL_FORM_EVENT, handleEnvelopeEvent);
        resizeObserver?.disconnect();
        removalObserver?.disconnect();
        $(window).off('beforeunload.emailform');
        if (Email.autoSaveTimer) {
          clearTimeout(Email.autoSaveTimer);
          Email.autoSaveTimer = null;
        }
        Email.autoSaveDelete();
        Email.autoSaveDelete = () => {};
        $.fn.cafevTooltip.remove();
        WysiwygEditor.removeEditor($(container).find(`textarea.${WYSIWYG_EDITOR}`));
        destroyMountedSelects(container);
        const $upload = $(container).find('#attachment_upload_start');
        if ($upload.data('blueimp-fileupload')) {
          $upload.fileupload('destroy');
        }
        $(container).off();
        Email.active = false;
        Email.blocked = false;
      };
      /**
       * Coordinate the legacy widgets without importing their asset into Vue.
       *
       * @param event Envelope lifecycle or tab transition.
       */
      function handleEnvelopeEvent(event: EmailFormEvent) {
        if (event.action === 'ready') {
          $dialogHolder = $(event.container);
          return;
        }
        if (event.action === 'cleanup') {
          cleanup(event.container);
          return;
        }
        if (!$dialogHolder) {
          return;
        }
        const $emailForm = $dialogHolder.find<HTMLFormElement>('form#' + appPrefix('email-form'));
        if (event.action === 'close') {
          if (Email.blocked) {
            return;
          }
          if (Email.activeTab === 'status') {
            Dialogs.notice({
              title: t(appName, 'You will be transferred back to the "edit-message" view'),
              content: t(appName, 'You have clicked on the "close"-button while visiting the "preview" tab which normally just would close the email widget. It has been reported that this is an unexpected behaviour of the user interface, therefore you are just "transferred" back to the email-composition tab. Just click on "OK" or close this diablog.'),
              callback: () => selectEmailTab('composer'),
              allowHtml: true,
              dialogClasses: ['maximize-width'],
            });
          } else {
            $dialogHolder.find('input.submit.cancel[type="submit"]').trigger('click');
          }
          return;
        }
        if (event.action === 'before-tab-change') {
          if (event.previous === 'composer') {
            $emailForm.find('.messagetext button[data-mce-name="overflow-button"].tox-tbtn.tox-tbtn--enabled').trigger('click');
          }
          if (event.previous === 'recipients' && event.tab === 'composer') {
            updateComposerElements($emailForm, ['to', 'subjectTag']);
          }
          Email.activeTab = event.tab;
          return;
        }
        if (event.action === 'tab-changed') {
          $dialogHolder.find('#attachment_upload_start').fileupload('option', 'dropZone', event.tab === 'composer' ? $(event.panel) : null);
          if (event.tab === 'recipients') {
            emailFormRecipientsSelectControls($dialogHolder, $emailForm.find<HTMLFieldSetElement>('fieldset.email-recipients.page'));
          }
          emailTabResize($dialogHolder.closest('.emailform-envelope'), $(event.panel));
        }
      }
      asyncSubscribe(EMAIL_FORM_EVENT, handleEnvelopeEvent);
      try {
        Email.envelope = await awaitEmit(GET_VUE_COMPONENT, {
          name: EMAIL_FORM_ENVELOPE,
          propsData: {
            form: data,
            title: dlgTitle,
            single,
          },
        }) as MountableComponent<typeof EMAIL_FORM_ENVELOPE>;
        Email.host = document.createElement('div');
        const mountPoint = document.createElement('div');
        Email.host.append(mountPoint);
        const ambient = document.getElementById(appPrefix('general'));
        if (!ambient) {
          throw new Error('Missing legacy container for email form.');
        }
        ambient.append(Email.host);
        Email.envelope.mount(mountPoint);
        if (!$dialogHolder) {
          throw new Error('Missing mounted email form HTML.');
        }
        const $emailForm = $dialogHolder.find<HTMLFormElement>('form#' + appPrefix('email-form'));
        const $recipientsPanel = $dialogHolder.find('div#emailformrecipients');
        const $composerPanel = $dialogHolder.find('div#emailformcomposer');
        const $dialogWidget = $dialogHolder.closest('.emailform-envelope');
        $.fn.cafevTooltip.remove();
        emailFormRecipientsHandlers(
          $emailForm.find<HTMLFieldSetElement>('fieldset.email-recipients.page'),
          $emailForm,
          $dialogHolder,
          $recipientsPanel,
        );
        emailFormCompositionHandlers(
          $emailForm.find<HTMLFieldSetElement>('fieldset.email-composition.page'),
          $emailForm,
          $dialogHolder,
          $composerPanel,
        );
        if (single) {
          $dialogHolder.find('#attachment_upload_start').fileupload('option', 'dropZone', $composerPanel);
        }
        $dialogHolder.on('click', 'a.download-link.ajax-download', function() {
          const $this = $(this);
          fileDownload($this.attr('href')!, $this.data());
          return false;
        });
        const resize = () => {
          const panels = { recipients: $recipientsPanel, composer: $composerPanel, status: $dialogHolder!.find('#emailformdebug') };
          emailTabResize($dialogWidget, panels[Email.activeTab]);
        };
        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe($dialogWidget[0]);
        const host = Email.host;
        removalObserver = new MutationObserver(() => {
          if (!host.isConnected) {
            closeEmailForm();
          }
        });
        removalObserver.observe(document.documentElement, { childList: true, subtree: true });
        resize();
        promiseCallback(true);
      } catch (error) {
        closeEmailForm();
        asyncUnsubscribe(EMAIL_FORM_EVENT, handleEnvelopeEvent);
        Email.active = false;
        console.error('Unable to initialize email form.', error);
        notificationShow(t(appName, 'Unable to initialize the email form.'), { timeout: 15 });
        promiseCallback(error instanceof Error ? error : new Error('Unable to initialize email form.'));
      }
    });
  return promise;
};

const documentReady = function() {
};

export {
  closeEmailForm,
  documentReady,
  emailFormPopup,
  isEmailFormOpen,
  showEmailForm,
};

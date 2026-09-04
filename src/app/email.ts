/**
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
 */

import type { IOptions as SelectizeOptions } from 'selectize';
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
import type { ResponseData } from '../types/ajax/response-data.d.ts';
import type { UploadFile } from './file-upload.ts';

import { showSuccess } from '@nextcloud/dialogs';
import { translate as t } from '@nextcloud/l10n';
import { generateOcsUrl } from '@nextcloud/router';
import { emit as asyncEmit, subscribe as asyncSubscribe } from '@rotdrop/async-nextcloud-event-bus';
import actual from 'actual';
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
import { PersistentCGIKeys } from '../../build/ts-types/php-modules/PageRenderer.ts';
import { TEMPLATE as allMusiciansTemplate } from '../../build/ts-types/php-modules/PageRenderer/AllMusicians.ts';
import { TEMPLATE as projectParticipantsTemplate } from '../../build/ts-types/php-modules/PageRenderer/ProjectParticipants.ts';
import { FOLDER_TYPE_PROJECT } from '../../build/ts-types/php-modules/Service/ProjectService.ts';
import {
  LEGACY_UPDATE_EVENTS_SELECTION,
  PROJECT_EVENTS_LISTING,
} from '../event-bus-events.ts';
import { asKey } from '../toolkit/types/type-traits.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import {
  handleError as ajaxHandleError,
  validateResponse as ajaxValidateResponse,
} from './ajax.ts';
import * as Ajax from './ajax.ts';
import pageBusyIcon from './busy-icon.ts';
import { globalState, toolTipsInit } from './cafevdb.ts';
import debounce from './debounce.ts';
import * as DialogUtils from './dialog-utils.ts';
import * as Dialogs from './dialogs.ts';
import fileDownload from './file-download.ts';
import fileUploadInit from './file-upload.ts';
import { appName, appPrefix } from './globals.ts';
import $ from './jquery.ts';
import modalizer from './modalizer.ts';
import { show as notificationShow } from './notification.ts';
import { data as pmeData, token as pmeToken } from './pme-selectors.ts';
import * as ProgressStatus from './progress-status.ts';
import { personalRecordDialog as participantRecordDialog } from './project-participants.ts';
import selectPopup from './select-popup.ts';
import * as SelectUtils from './select-utils.ts';
import { setPersonalUrl } from './settings-urls.ts';
import { urlDecode } from './url-decode.ts';
import { handleMenu as handleUserManualMenu } from './user-manual.ts';
import * as WysiwygEditor from './wysiwyg-editor.ts';

import 'bootstrap4-duallistbox';
import 'selectize';
import 'selectize/dist/css/selectize.bootstrap.css';
import 'cafevdb-selectize.scss';
import './jquery-readonly.ts';
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

const selectizeOpenOnFocus = true;

const selectizeOptions = {
  plugins: ['remove_button'],
  delimiter: ',',
  persist: false,
  openOnFocus: selectizeOpenOnFocus,
  closeAfterSelect: true,
  hideSelected: false,
};

const Email = {
  topicUnspecific: 'general',
  active: false,
  autoSaveTimer: null as null|NodeJS.Timeout,
  autoSaveDelete() {},
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
  // $panelHolder.css('width', 'auto');
  // $panelHolder.css('height', 'auto');
  $panelHolder.css('max-height', 'none'); // reset in order to get auto-configuration
  const titleOffset = ($dialogWidget.find('.ui-dialog-titlebar').outerHeight(true)!
                       + $dialogWidget.find('.ui-tabs-nav').outerHeight(true)!);
  const panelHeight = $panelHolder.outerHeight(true)!;
  // const panelOffset = panelHeight - $panelHolder.height()!;
  const dialogHeight = $dialogWidget.height()!;
  // alert('outer: '+panelHeight+' dialog '+dialogHeight);
  if (panelHeight > dialogHeight - titleOffset) {
    $panelHolder.css('max-height', (dialogHeight - titleOffset /* - panelOffset */) + 'px');
  }
  // if ($panelHolder.get(0).scrollHeight > $panelHolder.outerHeight(true)) {
  //   $panelHolder.css('padding-right', '2.4em');
  // } else {
  //   $panelHolder.css('padding-right', '');
  // }
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
    $dialogHolder.tabs('option', 'active') !== 0 // visible?
      || $fieldset.find('#participation-status-filer.selectized').length > 0 // already initialized
  ) {
    return;
  }

  const $participationStatusFilter = $fieldset.find('#participation-status-filter');
  $participationStatusFilter.selectize({
    ...selectizeOptions,
    closeAfterSelect: false,
  });

  const $instrumentsFilter = $fieldset.find('#instruments-filter') as JQuery<HTMLSelectElement>;
  $instrumentsFilter.selectize({
    ...selectizeOptions,
    closeAfterSelect: false,
  });

  const $recipientsSelect = $fieldset.find('#recipients-select');
  $recipientsSelect.bootstrapDualListbox({
    // moveOnSelect: false,
    // preserveSelectionOnMove : 'all',
    moveAllLabel: t(appName, 'Move all'),
    btnMoveAllText: t(appName, 'Select all'),
    moveSelectedLabel: t(appName, 'Move selected'),
    removeSelectedLabel: t(appName, 'Remove selected'),
    removeAllLabel: t(appName, 'Remove all'),
    btnRemoveAllText: t(appName, 'Remove all'),
    nonSelectedListLabel: t(appName, 'Remaining Recipients'),
    selectedListLabel: t(appName, 'Selected Recipients'),
    infoText: '&nbsp;', // t(appName, 'Showing all {0}'),
    infoTextFiltered: '<span class="badge bg-warning">'
      + t(appName, 'Filtered')
      + '</span> {0} '
      + t(appName, 'from')
      + ' {1}',
    infoTextEmpty: t(appName, 'Empty list'),
    filterPlaceHolder: t(appName, 'Filter'),
    filterTextClear: t(appName, 'show all'),
    selectorMinimalHeight: 200,
  });
  const $dualListBoxContainer = $fieldset.find('.bootstrap-duallistbox-container');
  const dualSelect = $dualListBoxContainer.find('select');
  dualSelect.attr(
    'title',
    t(appName, 'Click on the names to move the respective person to the other box'),
  );
  dualSelect.addClass('tooltip-top');

  if ($recipientsSelect.prop('readonly')) {
    $dualListBoxContainer.find('input, select, button').readonly(true);
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

  const $recipientsSelect = $fieldset.find('select#recipients-select');
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
          $recipientsSelect.bootstrapDualListbox('refresh', true);

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

    const $otherInputs = $fieldset.find('input, select, button').not(exceptions.join(','));
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
      emailTabResize($dialogHolder.dialog('widget'), $panelHolder);
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
  const $debugOutput = $form.find('#emailformdebug');
  const $templateEmailsSelector = $fieldset.find('select.template.email-message-selector') as JQuery<HTMLSelectElement>;
  const $draftEmailsSelector = $fieldset.find('select.draft.email-message-selector') as JQuery<HTMLSelectElement>;
  const $sentEmailsSelector = $fieldset.find('select.sent.email-message-selector') as JQuery<HTMLSelectElement>;
  const $saveAsTemplate = $fieldset.find('#check-save-as-template');
  const $draftAutoSave = $fieldset.find('#check-draft-auto-save');
  const $discloseRecipients = $fieldset.find('#check-disclosed-recipients');
  const $messageText = $fieldset.find('textarea');
  const $eventAttachmentsRow = $fieldset.find('tr.event-attachments');
  const $eventAttachmentsSelector = $eventAttachmentsRow.find('select#event-attachments-selector') as JQuery<HTMLSelectElement>;
  const $fileAttachmentsRow = $fieldset.find('tr.file-attachments') as JQuery<HTMLTableRowElement>;
  const $fileAttachmentsSelector = $fileAttachmentsRow.find('select#file-attachments-selector') as JQuery<HTMLSelectElement>;
  const $sendButton = $fieldset.find('input.submit.send') as JQuery<HTMLInputElement>;
  const $dialogWidget = $dialogHolder.dialog('widget');
  const $composerPanel = $('#emailformcomposer');

  WysiwygEditor.addEditor($dialogHolder.find(`textarea.${WYSIWYG_EDITOR}`));

  const messageSelectorSelectizeOptions: Partial<SelectizeOptions> = {
    onBeforeDropdownOpen(_$dropdown: JQuery) {
      this.$wrapper.toggleClass(dropdownOpenCssClass, true);
    },
    onDropdownClose(_$dropdown: JQuery) {
      this.$wrapper.toggleClass(dropdownOpenCssClass, false);
    },
    onChange(value) {
      this.$wrapper.toggleClass(loadingCssClass, !!value);
    },
    onClear() {
      this.$wrapper.toggleClass(loadingCssClass, false);
    },
    onOptionsRefresh($dropdown) {
      $dropdown.find('[class*="tooltip-"]').cafevTooltip();
    },
    closeAfterSelect: true,
    openOnFocus: selectizeOpenOnFocus,
  };

  $templateEmailsSelector.selectize({
    ...messageSelectorSelectizeOptions,
    inputClass: appName + '-email-current-template',
    plugins: ['clear_button', 'restore_on_backspace'],
    create: true,
    persist: false,
    render: {
      option_create(data, escape) {
        return '<div class="create">' + t(appName, 'Add') + ' <strong>' + escape(data.input) + '</strong>&#x2026;</div>';
      },
    },
    onOptionAdd(value, _data) {
      this.$input.data('ignoreChange', value);
    },
    onInitialize() {
      this.$wrapper.toggleClass(expandedCssClass, $saveAsTemplate.is(':checked'));
    },
  });
  $draftEmailsSelector.selectize(messageSelectorSelectizeOptions);
  $sentEmailsSelector.selectize(messageSelectorSelectizeOptions);

  $fileAttachmentsSelector.add($eventAttachmentsSelector).selectize({
    ...selectizeOptions,
    closeAfterSelect: false,
    onDropdownOpen() {
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

                $templateEmailsSelector.next().removeClass(loadingCssClass);
                break;
              }
              case 'draft': {
                $.fn.cafevTooltip.remove();

                // replace the entire composer tab
                WysiwygEditor.removeEditor($panelHolder.find(`textarea.${WYSIWYG_EDITOR}`));
                $panelHolder.html(requestData.composerForm!);
                $fieldset = $panelHolder.find('fieldset.email-composition.page');
                emailFormCompositionHandlers($fieldset, $form, $dialogHolder, $panelHolder);

                // replace the recipients tab as well ...
                const rcptPanelHolder = $dialogHolder.find('div#emailformrecipients');
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
                $dialogHolder.dialog('option', 'title', dlgTitle);

                // update the "global" project name and id
                projectId(requestData.projectId);
                projectName(requestData.projectName);
                bulkTransactionId(requestData.bulkTransactionId);

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
                $panelHolder.html(requestData.composerForm!);
                $fieldset = $panelHolder.find('fieldset.email-composition.page');
                emailFormCompositionHandlers($fieldset, $form, $dialogHolder, $panelHolder);

                // replace the recipients tab ...
                const rcptPanelHolder = $dialogHolder.find('div#emailformrecipients');
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
              case 'template':
                SelectUtils.replaceOptions($templateEmailsSelector, requestData.templateEmailOptions!);
                break;
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
                    $(window).on('beforeunload', function() {
                      return t(appName, 'Email sending is in progress. Leaving the page now will cancel the email submission.');
                    });
                    $dialogWidget.addClass(pmeToken('table-dialog-blocked'));
                  } else {
                    $(window).off('beforeunload');
                    if (progressOpen) {
                      progressWrapper.dialog('close');
                    }
                    $dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
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
                  $dialogHolder.tabs('option', 'active', 2);
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

          $dialogHolder.tabs('option', 'active', 2);

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
      $dialogHolder.dialog('close');
      return false;
    });

  /*************************************************************************
   *
   * Template handling (save, delete, load)
   */

  const autoSaveSeconds = $draftAutoSave.data('auto-save-interval') || 300;
  const autoSaveTimeout = autoSaveSeconds * 1000;

  const autoSaveHandler = () => {
    if (Email.autoSaveTimer) {
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
      $templateEmailsSelector.next().toggleClass(expandedCssClass, $saveAsTemplate.is(':checked'));
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
        if ($templateEmailsSelector.find('option').filter(function() { return $(this).html() === current; }).length > 0) {
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
        if ($templateEmailsSelector.find('option').filter(function() {
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
              $dialogWidget.addClass(pmeToken('table-dialog-blocked'));
            } else {
              SelectUtils.deselectAll($draftEmailsSelector);
              $dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
              $dialogHolder.tabs('option', 'disabled', []);
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
        $templateEmailsSelector.next().removeClass(loadingCssClass);
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
            $dialogWidget.addClass(pmeToken('table-dialog-blocked'));
          } else {
            SelectUtils.deselectAll($sentEmailsSelector);
            $dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
            $dialogHolder.tabs('option', 'disabled', []);
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
    doneCallback(json, _index, _container) {
      attachmentFromJSON(json);
    },
    stopCallback: updateFileAttachments,
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
              return false;
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

          selectPopup(data.contents, {
            title: t(appName, 'Address Book'),
            saveText: t(appName, 'Accept'),
            selectize: {
              openOnFocus: selectizeOpenOnFocus,
              plugins: ['remove_button', 'drag_drop', 'restore_on_backspace'],
              createOnBlur: true,
              persist: true,
              // compare with pme.ts
              valueField: 'email',
              labelField: 'label',
              searchField: ['email', 'label'],
              lockOptgroupOrder: true,
              create(input, setterCallback) {
                // eslint-disable-next-line @typescript-eslint/no-this-alias
                const selectize = this;
                $.post(generateAppUrl(`${validationEndPoint}/${EnumMusicianValidationTopic.EMAIL}`), {
                  failure: 'error',
                  [pmeData('email')]: input,
                })
                  .fail(function(xhr, status, errorThrown) {
                    Ajax.handleError(xhr, status, errorThrown);
                    setterCallback(false);
                  })
                  .done(function(data) {
                    if (!data || !data.email) {
                      setterCallback(false);
                    }
                    const optgroup = freeFormLabel;
                    data.optgroup = optgroup;
                    if (selectize.optgroups[optgroup] === undefined) {
                      selectize.registerOptionGroup({
                        $order: 1,
                        label: data.optgroup,
                        value: data.optgroup,
                        disable: false,
                      });
                    }
                    const email = data.email;
                    data.label = data.details[email] ? `${data.details[email]} <${email}>` : email;
                    setterCallback(data);
                    console.info('EMAIL OPTION DATA', { data, all: selectize.options });
                    selectize
                      .$input
                      .closest('.ui-dialog.address-book-emails')
                      .find('button.save-contacts').prop('disabled', false);
                  });
              },
            },
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
                  const $selectElement = $dialogHolder.find('select');
                  const selectize = SelectUtils.getSelectize<HTMLSelectElement, string, { email: string; optgroup: string; label: string }>($selectElement)!;
                  console.info('SELECTED EMAIL OPTIONS', SelectUtils.selectedOptions($selectElement));
                  const selectedValues = SelectUtils.selected($selectElement)!;
                  const selectedFreeForm = selectize.items
                    .map((email) => selectize.options[email])
                    .filter((option) => option.optgroup === freeFormLabel)
                    .map((option) => {
                      console.info('EMAIL OPTION', option);
                      return {
                        value: option.email,
                        html: option.label,
                        text: option.label,
                      };
                    });
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
                          selectize!
                            .$input
                            .closest('.ui-dialog.address-book-emails')
                            .find('button.save-contacts').prop('disabled', true);
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
              cleanup();
              if (SelectUtils.children($selectElement).filter('optgroup.free-form').length === 0) {
                $(this).dialog('widget')
                  .find('button.save-contacts').prop('disabled', true);
              }
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
 * @param modal TBD, default true.
 *
 * @param single TBD, default false.
 *
 * @param afterInit Callback with optional boolean arg indicating the
   error status. If the success arg is undefined then the dialog is
   already open.
 */
const emailFormPopup = (post: string|JQuery.PlainObject, modal: boolean, single: boolean, afterInit: (success?: boolean) => void = () => {}) => {

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

    promiseCallback();
    return promise;
  }

  Email.active = true;
  console.debug('EMAIL ACTIVE TRUE', Email);

  if (modal === undefined) {
    modal = true;
  }
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
    .done(function(data: ResponseData<EmailWebFormResponse>) {
      const containerId = 'emailformdialog';

      if (!ajaxValidateResponse(
        data,
        ['contents'],
        () => {
          Email.active = false;
          console.debug('EMAIL ACTIVE FALSE', Email);
          promiseCallback(new Error('Missing HTML content for email form.'));
        },
      )) {
        return;
      }

      const $dialogHolder = $('<div id="' + containerId + '"></div>');
      $dialogHolder.html(data.contents);
      $('body').append($dialogHolder);

      const $emailForm = $('form#' + appPrefix('email-form')) as JQuery<HTMLFormElement>;
      const $recipientsPanel = $dialogHolder.find('div#emailformrecipients');
      const $composerPanel = $dialogHolder.find('div#emailformcomposer');

      let dlgTitle: string;
      if ((data.projectId ?? 0) > 0) {
        dlgTitle = t(appName, 'Em@il Form for {projectName}', { projectName: data.projectName! });
      } else {
        dlgTitle = t(appName, 'Em@il Form');
      }

      if (modal) {
        modalizer(true);
      }

      const position = {
        my: 'center top',
        at: 'center bottom',
        of: '#header',
      };

      $dialogHolder.cafevDialog({
        title: dlgTitle,
        position,
        width: 'auto',
        height: 'auto',
        modal: false, // modal,
        closeOnEscape: false,
        dialogClass: 'emailform custom-close',
        resizable: false,
        draggable: true,
        open() {
          $.fn.cafevTooltip.remove();
          DialogUtils.toBackButton($dialogHolder);
          DialogUtils.fullScreenButton($dialogHolder, function(_mode, when) {
            if (when === 'before') {
              WysiwygEditor.removeEditor($dialogHolder.find(`textarea.${WYSIWYG_EDITOR}`));
            }
            if (when === 'after') {
              WysiwygEditor.addEditor($dialogHolder.find(`textarea.${WYSIWYG_EDITOR}`));
            }
          });
          DialogUtils.customCloseButton($dialogHolder, function(event, _container) {
            event.stopImmediatePropagation();
            // with special greetings to Uschi ...
            const activeTab = $dialogHolder.tabs('option', 'active');
            if (activeTab >= 2) {
              Dialogs.notice({
                title: t(appName, 'You will be transferred back to the "edit-message" view'),
                content: t(appName, 'You have clicked on the "close"-button while visiting the "preview" tab which normally just would close the email widget. It has been reported that this is an unexpected behaviour of the user interface, therefore you are just "transferred" back to the email-composition tab. Just click on "OK" or close this diablog.'),
                callback: () => {
                  $dialogHolder.tabs('option', 'active', 1);
                },
                allowHtml: true,
                dialogClasses: ['maximize-width'],
              });
            } else {
              $dialogHolder.find('input.submit.cancel[type="submit"]').trigger('click');
            }
            // $dialogHolder.dialog('close');
            return false;
          });
          const $dialogWidget = $dialogHolder.dialog('widget');

          // this must come before calling emailFormRecipientsHandlers
          $dialogHolder.tabs({
            active: single ? 1 : 0,
            disabled: single ? [0] : [],
            heightStyle: 'content',
            create(_event, ui) {
              emailTabResize($dialogWidget, ui.panel);
              return true;
            },
            activate(_event, ui) {
              const newTabId = ui.newTab.attr('id');

              if (newTabId === 'emailformdebug-tab') {
                // The following is primarily for the debug
                // output in order to get the scroll-bars right
                const panel = ui.newPanel;
                let newHeight = $dialogWidget.height()! -
                    $dialogWidget.find('.ui-dialog-titlebar').outerHeight(true)!;
                newHeight -= $('#emailformtabs').outerHeight(true)!;
                newHeight -= panel.outerHeight(true)! - panel.height()!;
                panel.height(newHeight);
              } else {
                if (newTabId === 'emailformcomposer-tab') {
                  $('#attachment_upload_start').fileupload('option', 'dropZone', ui.newPanel);
                } else {
                  $('#attachment_upload_start').fileupload('option', 'dropZone', null);
                  const $recipientsFieldSet = $emailForm.find('fieldset.email-recipients.page') as JQuery<HTMLFieldSetElement>;
                  emailFormRecipientsSelectControls($dialogHolder, $recipientsFieldSet);
                }

                // At least in FF there is also a resize event,
                // but only for the composition window. Don't
                // know why.
                emailTabResize($dialogWidget, ui.newPanel);
              }

              return true;
            },
            beforeActivate(event, ui) {
              // When activating the composition window we
              // first have to update the email addresses. This
              // is cosmetics, but this entire thing is DAU
              // cosmetics stuff
              const newTabId = ui.newTab.attr('id');
              const oldTabId = ui.oldTab.attr('id');

              if (oldTabId === 'emailformcomposer-tab' && newTabId !== 'emailformcomposer-tab') {
                // close TinyMCE overflow button pane
                const $overflowButton = $emailForm.find('.messagetext button[data-mce-name="overflow-button"].tox-tbtn.tox-tbtn--enabled');
                $overflowButton.trigger('click');
              }

              if (newTabId === 'emailformhelp-tab') {
                event.preventDefault();
                return true;
              }

              ui.newPanel.css('max-height', '');
              ui.newPanel.css('height', 'auto');

              if (oldTabId !== 'emailformrecipients-tab'
                  || newTabId !== 'emailformcomposer-tab') {
                return true;
              }

              updateComposerElements($emailForm, ['to', 'subjectTag']);

              return true;
            },
          });

          handleUserManualMenu($dialogHolder);

          const $recipientsFieldSet = $emailForm.find('fieldset.email-recipients.page') as JQuery<HTMLFieldSetElement>;
          const $composerFieldSet = $emailForm.find('fieldset.email-composition.page') as JQuery<HTMLFieldSetElement>;

          // Fine, now add handlers and AJAX callbacks. We can
          // probably move some of the code above to the
          // respective tab-handler.
          emailFormRecipientsHandlers(
            $recipientsFieldSet,
            $emailForm,
            $dialogHolder,
            $recipientsPanel,
          );
          emailFormCompositionHandlers(
            $composerFieldSet,
            $emailForm,
            $dialogHolder,
            $composerPanel,
          );

          // download support
          $dialogHolder.on('click', 'a.download-link.ajax-download', function() {
            const $this = $(this);
            fileDownload($this.attr('href')!, $this.data());
            return false;
          });

          // we have to recompute the tab size for the recipients controls
          emailTabResize($dialogWidget, $recipientsPanel);

          $dialogHolder.dialog('moveToTop');
          const viewportHeight = actual('height', 'px');
          const widgetHeight = $dialogWidget.outerHeight()!;
          const difference = Math.max(0, Math.min(0.5 * (viewportHeight - widgetHeight - 50), 50));
          if (difference > 0) {
            position.at = `center bottom+${difference}`;
            console.info('EMAILL DIALOG POSITION', position);
            $dialogHolder.dialog('option', 'position', position);
          }
          $dialogWidget
            .off('resize.' + appName)
            .on('resize.' + appName, function() {
              const widgetOffset = $dialogWidget.offset();
              const viewportHeight = actual('height', 'px');
              const widgetHeight = $dialogWidget.outerHeight()!;
              const overflow = widgetHeight + widgetOffset!.top - viewportHeight;
              if (overflow > 0) {
                const difference = Math.max(0, Math.min(0.5 * (viewportHeight - widgetHeight - 50), 50));
                position.at = `center bottom+${difference}`;
                $dialogHolder.dialog('option', 'position', position);
              }
              return false;
            });
          promiseCallback(true);
        },
        close() {
          console.info('EMAIL CLOSING');
          if (Email.autoSaveTimer) {
            clearTimeout(Email.autoSaveTimer);
            Email.autoSaveTimer = null;
          }
          Email.autoSaveDelete();

          Email.autoSaveDelete = function() {};

          $.fn.cafevTooltip.remove();
          WysiwygEditor.removeEditor($dialogHolder.find(`textarea.${WYSIWYG_EDITOR}`));
          // $dialogHolder.dialog('close');
          $dialogHolder.dialog('destroy').remove();

          modalizer(false);
          Email.active = false;
          console.debug('EMAIL ACTIVE FALSE', Email);
        },
      });
    });
  return promise;
};

const documentReady = function() {
};

export {
  documentReady,
  emailFormPopup,
};

/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName, $, appPrefix } from './globals.js';
import { toolTipsInit, globalState } from './cafevdb.js';
import { handleError as ajaxHandleError, validateResponse as ajaxValidateResponse } from './ajax.js';
import pageBusyIcon from './busy-icon.js';
import * as Dialogs from './dialogs.ts';
import { personalRecordDialog as participantRecordDialog } from './project-participants.js';
import * as WysiwygEditor from './wysiwyg-editor.js';
import fileUploadInit from './file-upload.js';
import * as DialogUtils from './dialog-utils.js';
import * as ProgressStatus from './progress-status.js';
import { show as notificationShow } from './notification.ts';
import * as SelectUtils from './select-utils.js';
import * as Ajax from './ajax.js';
import { urlDecode } from './url-decode.js';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import { generateOcsUrl } from '@nextcloud/router';
import { setPersonalUrl } from './settings-urls.js';
import selectPopup from './select-popup.js';
import debounce from './debounce.js';
import modalizer from './modalizer.js';
import { handleMenu as handleUserManualMenu } from './user-manual.ts';
import fileDownload from './file-download.js';
import { showSuccess } from '@nextcloud/dialogs';
import { token as pmeToken, data as pmeData } from './pme-selectors.js';
import {
  LEGACY_UPDATE_EVENTS_SELECTION,
  PROJECT_EVENTS_LISTING,
} from '../event-bus-events.ts';
import { subscribe as asyncSubscribe, emit as asyncEmit } from '../services/async-event-bus.ts';

import 'selectize';
import 'selectize/dist/css/selectize.bootstrap.css';
require('cafevdb-selectize.scss');

const selectizeOpenOnFocus = true;

const selectizeOptions = {
  plugins: ['remove_button'],
  delimiter: ',',
  persist: false,
  openOnFocus: selectizeOpenOnFocus,
  closeAfterSelect: true,
  hideSelected: false,
};

require('./jquery-readonly.js');
require('bootstrap4-duallistbox');
require('emailform.scss');

const Email = globalState.Email = {
  topicUnspecific: 'general',
  active: false,
  autoSaveTimer: null,
  autoSaveDelete() {},
};

asyncSubscribe(LEGACY_UPDATE_EVENTS_SELECTION, (event) => {
  const $emailFormDialog = $('div#emailformdialog');
  $emailFormDialog.trigger(appName + ':events_changed', [event.selection]);
});

function generateEmailFormUrl(url, urlParams, urlOptions) {
  return generateAppUrl('communication/email/outgoing/' + url, urlParams, urlOptions);
}

function generateComposerUrl(operation, topic) {
  if (topic === undefined && operation.operation) {
    topic = operation.topic;
    operation = operation.operation;
  }
  topic = topic || Email.topicUnspecific;
  return generateEmailFormUrl('composer/{operation}/{topic}', { operation, topic });
}

function attachmentFromJSON(response, info) {
  const fileAttachmentsHolder = $(`form.${appName}-email-form fieldset.attachments input.file-attachments`);
  if (fileAttachmentsHolder === '') {
    Dialogs.alert(t(appName, 'Not called from main email-form.'), t(appName, 'Error'));
    return;
  }

  const file = { ...response };
  file.status = 'new';
  if (typeof info === 'object') {
    Object.assign(file, info);
  }
  let fileAttachments = fileAttachmentsHolder.val();

  if (fileAttachments === '') {
    fileAttachments = [file];
  } else {
    fileAttachments = $.parseJSON(fileAttachments);
    fileAttachments.push(file);
  }
  fileAttachmentsHolder.val(JSON.stringify(fileAttachments));
}

const cloudAttachment = function(paths, callback) {
  $.post(generateEmailFormUrl('attachment/cloud'), { paths })
    .fail(ajaxHandleError)
    .done(function(response) {
      for (const attachment of response) {
        attachmentFromJSON(attachment, { origin: 'cloud' });
      }
      if (typeof callback === 'function') {
        callback();
      }
    });
};

function emailTabResize(dialogWidget, panelHolder) {
  // panelHolder.css('width', 'auto');
  // panelHolder.css('height', 'auto');
  panelHolder.css('max-height', 'none'); // reset in order to get auto-configuration
  const titleOffset = (dialogWidget.find('.ui-dialog-titlebar').outerHeight(true)
                       + dialogWidget.find('.ui-tabs-nav').outerHeight(true));
  const panelHeight = panelHolder.outerHeight(true);
  const panelOffset = panelHeight - panelHolder.height();
  const dialogHeight = dialogWidget.height();
  // alert('outer: '+panelHeight+' dialog '+dialogHeight);
  if (panelHeight > dialogHeight - titleOffset) {
    panelHolder.css('max-height', (dialogHeight - titleOffset - panelOffset) + 'px');
  }
  // if (panelHolder.get(0).scrollHeight > panelHolder.outerHeight(true)) {
  //   panelHolder.css('padding-right', '2.4em');
  // } else {
  //   panelHolder.css('padding-right', '');
  // }
}

function updateComposerElements($emailForm, elements) {
  elements = elements || ['to'];
  if (!Array.isArray(elements)) {
    elements = [elements];
  }
  // we better serialize the entire form here
  let post = $emailForm.serialize();
  // place our update request
  post += '&emailComposer[request]=update';
  for (const element of elements) {
    post += '&' + 'emailComposer[formElement][]=' + element;
  }
  const url = generateComposerUrl('update', 'element');
  $.post(url, post)
    .fail(ajaxHandleError)
    .done(function(data) {
      if (!ajaxValidateResponse(data, [
        'projectId', 'projectName', 'operation', 'requestData',
      ])) {
        return;
      }

      for (const element of data.requestData.formElement) {
        switch (element.toLowerCase()) {
        case 'to': {
          const toSpan = $emailForm.find('span.email-recipients');
          let rcpts = data.requestData.elementData[element];
          const numRcpts = rcpts.length;

          rcpts = numRcpts === 0 ? toSpan.data('placeholder') : rcpts.join(', ');
          const title = toSpan.data('titleIntro') + '<br>' + rcpts;

          toSpan.cafevTooltip('dispose');
          toSpan.html(rcpts);
          toSpan.attr('title', title);
          toSpan.cafevTooltip();

          $emailForm.find('#check-disclosed-recipients').prop('disabled', numRcpts <= 1);
          break;
        }
        case 'subjecttag': {
          const subjectTag = data.requestData.elementData[element];
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
 * @param {jQuery} dialogHolder TBD.
 *
 * @param {jQuery} $fieldset TBD.
 */
const emailFormRecipientsSelectControls = function(dialogHolder, $fieldset) {

  if (dialogHolder.tabs('option', 'active') !== 0 // visible?
      || $fieldset.find('#participation-status-filer.selectized').length > 0 // already initialized
  ) {
    return;
  }

  const $participationStatusFilter = $fieldset.find('#participation-status-filter');
  $participationStatusFilter.selectize({
    ...selectizeOptions,
    closeAfterSelect: false,
  });

  const $instrumentsFilter = $fieldset.find('#instruments-filter');
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
    t(appName, 'Click on the names to move the respective person to the other box'));
  dualSelect.addClass('tooltip-top');

  if ($recipientsSelect.prop('readonly')) {
    $dualListBoxContainer.find('input, select, button').readonly(true);
  }

  toolTipsInit(dialogHolder.find('div#emailformrecipients'));
};

/**
 * Add handlers to the control elements, and call the AJAX sciplets
 * for validation to update the recipients selection tab accordingly.
 *
 * @param {jQuery} $fieldset The field-set enclosing the recipients selection part
 *
 * @param {jQuery} form TBD.
 *
 * @param {jQuery} dialogHolder The div holding the jQuery dialog for everything
 *
 * @param {jQuery} panelHolder The div enclosing the $fieldset
 *
 * @returns {boolean}
 */
const emailFormRecipientsHandlers = function($fieldset, form, dialogHolder, panelHolder) {

  emailFormRecipientsSelectControls(dialogHolder, $fieldset);

  const recipientsSelect = $fieldset.find('select#recipients-select');
  const missingAddresses = $fieldset.find('.missing-email-addresses.names');
  const missingLabel = $fieldset.find('.missing-email-addresses.label');
  const noMissingLabel = $fieldset.find('.missing-email-addresses.label.empty');
  const instrumentsFilter = $fieldset.find('.instruments-filter.' + appPrefix('container'));
  const instrumentsSelect = instrumentsFilter.find('select');
  const participationStatusFilter = $fieldset.find('.participation-status-filter.' + appPrefix('container'));
  const participationStatusSelect = participationStatusFilter.find('select');
  const filterHistoryInput = $fieldset.find('#recipients-filter-history');
  const debugOutput = form.find('#emailformdebug');
  const busyIndicator = $fieldset.find('.busy-indicator');

  let filterUpdateActive = false;

  // Apply the instruments filter
  const applyRecipientsFilter = function(event, parameters) {
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

    parameters = { ...defaultParameters, ...parameters };

    const historySnapshot = parameters.historySnapshot;
    if (!historySnapshot) {
      busyIndicator.show();
    }

    let post = $fieldset.serialize();
    if (historySnapshot) {
      post += '&' + $.param({ emailRecipients: { HistorySnapshot: 'snapshot' } });
    } else {
      post += '&' + form.find('fieldset.form-data').serialize();
      const $element = $(event.target);
      if ($element.is(':button')) {
        post += '&' + $.param($element);
      }
      // add the name of the cause for this havoc as additional parameter
      const elementNames = [...$element.attr('name').matchAll(/([^[]+)\[([^\]]+)\]/g)];
      if (elementNames.length === 1 && elementNames[0].length === 3) {
        post += '&' + elementNames[0][1] + '[userInteraction]=' + elementNames[0][2];
      }
    }
    $.post(generateEmailFormUrl('recipients-filter'), post)
      .fail(function(xhr, textStatus, errorThrown) {
        ajaxHandleError(xhr, textStatus, errorThrown, function(data) {
          parameters.cleanup();
          busyIndicator.hide();
          filterUpdateActive = false;
        });
      })
      .done(function(data) {
        const requiredResponse = historySnapshot
          ? ['filterHistory']
          : [
            'contents',
            'recipientsOptions',
            'missingEmailAddresses',
            'filterHistory',
            'instrumentsFilter',
            'participationStatusFilter',
          ];
        if (!ajaxValidateResponse(data, requiredResponse)) {
          parameters.cleanup();
          busyIndicator.hide();
          filterUpdateActive = false;
          return;
        }
        let resize = false;
        if (historySnapshot) {
          // Just update the history, but nothing else
          filterHistoryInput.val(data.filterHistory);
        } else if (data.contents !== undefined && data.contents.length > 0) {
          // replace the entire tab.
          $.fn.cafevTooltip.remove();
          panelHolder.html(data.contents);
          $fieldset = panelHolder.find('fieldset.email-recipients.page');
          emailFormRecipientsHandlers($fieldset, form, dialogHolder, panelHolder);
          resize = true;
        } else {
          // partial update
          $.fn.cafevTooltip.hide();

          // list of recipients
          recipientsSelect.html(data.recipientsOptions);
          recipientsSelect.bootstrapDualListbox('refresh', true);
          filterHistoryInput.val(data.filterHistory);

          // list of broken email addresses
          missingAddresses.html(data.missingEmailAddresses);
          if (data.missingEmailAddresses.length > 0) {
            missingLabel.removeClass('reallyhidden');
            noMissingLabel.addClass('reallyhidden');
          } else {
            missingLabel.addClass('reallyhidden');
            noMissingLabel.removeClass('reallyhidden');
          }

          // update the instruments filter
          SelectUtils.replaceOptions(instrumentsSelect, data.instrumentsFilter);

          // update the participation-status filter
          SelectUtils.replaceOptions(participationStatusSelect, data.participationStatusFilter);

          resize = true;
        }

        const filterHistory = data.filterHistory;
        if (filterHistory.historyPosition >= 0
            && filterHistory.historyPosition < filterHistory.historySize - 1) {
          // enable the undo button
          $fieldset.find('#instruments-filter-undo').prop('disabled', false);
        } else {
          $fieldset.find('#instruments-filter-undo').prop('disabled', true);
        }
        if (filterHistory.historyPosition > 0) {
          // enable the redo button as well
          $fieldset.find('#instruments-filter-redo').prop('disabled', false);
        } else {
          $fieldset.find('#instruments-filter-redo').prop('disabled', true);
        }

        if (!historySnapshot) {
          let debugText = '';
          if (data.debug !== undefined) {
            debugText = data.debug;
          }
          debugOutput.html('<pre>' + $('<div></div>').text(debugText).html() + '</pre>'
                           + '<pre>'
                           + $('<div></div>').text(data.recipientsOptions).html()
                           + '</pre>'
                           + data.missingEmailAddresses
                           + '<br/>'
                           + $('<div></div>').text(urlDecode(post)).html());
        }
        parameters.cleanup();
        if (resize) {
          panelHolder.trigger('resize', { position: 'bottom' });
        }

        busyIndicator.hide();

        filterUpdateActive = false;
      });
    return false;
  };

  /**
   * Prevent user interaction to the filter controls during loading or
   * if one of the mailing lists has been chosen as the sole
   * recipient.
   *
   * @param {boolean} state TBD.
   *
   * @param {Array} exceptions Array of CSS selectors to exclude from
   * the read-only attempt.
   */
  const readonlyFilterControls = function(state, exceptions) {

    $fieldset.toggleClass('filter-controls-disabled', state);

    exceptions = exceptions || [];
    exceptions.push('.action-menu-toggle.basic-recipients-set');

    const $otherInputs = $fieldset.find('input, select, button').not(exceptions.join(','));
    // Disable all recipient filters as they do not make any
    // sense. Sending to the mailing lists means to just send to
    // that list, further recipient choices are technically not possible.
    $otherInputs.readonly(state);

    missingAddresses.toggleClass('disabled', state);
  };

  // Attach above function to almost every sensible control :)

  // Controls :..
  const controlsContainer = $fieldset.find('.filter-controls.' + appPrefix('container'));

  instrumentsFilter
    .off('change')
    .on('change', function(event) {
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => readonlyFilterControls(false),
      });
    });

  // Member status filter
  participationStatusFilter
    .off('change')
    .on('change', function(event) {
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => readonlyFilterControls(false),
      });
    });

  // Basic recipients set (from project, except project, use mailing lists)
  const basicRecipientsSetContainer = $fieldset.find('.basic-recipients-set.' + appPrefix('container'));
  const basicRecipientsSet = basicRecipientsSetContainer.find('input[type="checkbox"], input[type="radio"]');
  const basicRecipientsSetProject = basicRecipientsSet.not('.mailing-list, .database');
  const basicRecipientsSetMailingList = basicRecipientsSet.filter('.mailing-list, .database');

  basicRecipientsSetMailingList
    .off('change')
    .on('change', function(event) {
      const $this = $(this);
      const mailingListRecipients = $this.is('.mailing-list') && $this.prop('checked');

      if (mailingListRecipients) {
        basicRecipientsSetMailingList.not(this).prop('checked', false);
        readonlyFilterControls(mailingListRecipients, ['.mailing-list', '.database']);
      } else {
        readonlyFilterControls(true);
        applyRecipientsFilter.call(this, event, {
          cleanup: () => readonlyFilterControls(false),
        });
      }
      basicRecipientsSet.filter('.mailing-list').each(function() {
        const $radio = $(this);
        basicRecipientsSetContainer.toggleClass($radio.val(), $radio.prop('checked'));
      });
      updateComposerElements(form, ['to', 'subjectTag']);
      return false;
    });

  basicRecipientsSetProject
    .off('change')
    .on('change', function(event) {
      const $this = $(this);
      basicRecipientsSetContainer.toggleClass($this.val(), $this.prop('checked'));
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => {
          updateComposerElements(form, ['to', 'subjectTag']);
          readonlyFilterControls(false);
        },
      });
    });

  // initialization
  if (basicRecipientsSet.filter('.mailing-list').prop('checked')) {
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
  recipientsSelect
    .off('change')
    .on('change', function(event) {
      applyRecipientsFilter.call(this, event, { historySnapshot: true });
    });

  // Give the user a chance to change broken or missing email
  // addresses from here.
  dialogHolder
    .off('pmedialog:changed')
    .on('pmedialog:changed', function(event) {
      readonlyFilterControls(true);
      applyRecipientsFilter.call(this, event, {
        cleanup: () => readonlyFilterControls(false),
      });
    });

  missingAddresses
    .off('click', '.personal-record')
    .on('click', '.personal-record', function(event) {
      const $this = $(this);

      const musicianId = $this.data('musicianId');
      const isParticipant = $this.data('isParticipant');

      const formData = form.find('fieldset.form-data');
      const projectId = formData.find('input[name="projectId"]').val();
      const projectName = formData.find('input[name="projectName"]').val();

      participantRecordDialog(
        musicianId, {
          table: isParticipant ? 'ProjectParticipants' : 'Musicians',
          projectId,
          projectName,
          initialValue: 'Change',
          ambientContainerSelector: '#emailformdialog',
        });

      return false;
    });

  panelHolder
    .off('resize.' + appName)
    .on('resize.' + appName, function() {
      emailTabResize(dialogHolder.dialog('widget'), panelHolder);
    });

  return false;
};

/**
 * Add handlers to the control elements, and call the AJAX scriplets
 * for validation to update the message composition tab accordingly.
 *
 * @param {jQuery} $fieldset The field-set enclosing the composition window part.
 *
 * @param {jQuery} form TBD.
 *
 * @param {jQuery} dialogHolder The div holding the jQuery dialog for everything
 *
 * @param {jQuery} panelHolder The div enclosing the $fieldset
 *
 */
const emailFormCompositionHandlers = function($fieldset, form, dialogHolder, panelHolder) {

  console.trace('COMPOSITION HANDLERS CALLED');

  const formData = form.find('fieldset.form-data');
  const $projectId = formData.find('input[name="projectId"]');
  const $projectName = formData.find('input[name="projectName"]');
  const $bulkTransactionId = formData.find('input[name="bulkTransactionId"]');
  const debugOutput = form.find('#emailformdebug');
  const $templateEmailsSelector = $fieldset.find('select.template.email-message-selector');
  const $draftEmailsSelector = $fieldset.find('select.draft.email-message-selector');
  const $sentEmailsSelector = $fieldset.find('select.sent.email-message-selector');
  const saveAsTemplate = $fieldset.find('#check-save-as-template');
  const draftAutoSave = $fieldset.find('#check-draft-auto-save');
  const discloseRecipients = $fieldset.find('#check-disclosed-recipients');
  const messageText = $fieldset.find('textarea');
  const $eventAttachmentsRow = $fieldset.find('tr.event-attachments');
  const $eventAttachmentsSelector = $eventAttachmentsRow.find('select#event-attachments-selector');
  const $fileAttachmentsRow = $fieldset.find('tr.file-attachments');
  const $fileAttachmentsSelector = $fileAttachmentsRow.find('select#file-attachments-selector');
  const sendButton = $fieldset.find('input.submit.send');
  const dialogWidget = dialogHolder.dialog('widget');
  const $composerPanel = $('#emailformcomposer');

  WysiwygEditor.addEditor(dialogHolder.find('textarea.wysiwyg-editor'));

  const messageSelectorSelectizeOptions = {
    onBeforeDropdownOpen($dropdown) { this.$wrapper.toggleClass('dropdown-open', true); },
    onDropdownClose($dropdown) { this.$wrapper.toggleClass('dropdown-open', false); },
    onChange(value) {
      this.$wrapper.toggleClass('loading', !!value);
    },
    onClear() { this.$wrapper.toggleClass('loading', false); },
    onOptionsRefresh($dropdown) { $dropdown.find('[class*="tooltip-"]').cafevTooltip(); },
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
      // eslint-disable-next-line
      option_create(data, escape) {
        return '<div class="create">' + t(appName, 'Add') + ' <strong>' + escape(data.input) + '</strong>&#x2026;</div>';
      },
    },
    onOptionAdd(value, data) {
      this.$input.data('ignoreChange', value);
    },
    onInitialize() {
      this.$wrapper.toggleClass('expanded', saveAsTemplate.is(':checked'));
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

  const projectId = function(value) {
    if (value === undefined) {
      return +$projectId.val();
    } else {
      $projectId.val(value);
      value = +value;
      form
        .toggleClass('project-mode', value > 0)
        .toggleClass('project-mode-off', !(value > 0));
      return value;
    }
  };
  projectId($projectId.val()); // ensure classes to be set

  const projectName = function(value) {
    if (value === undefined) {
      return $projectName.val();
    } else {
      $projectName.val(value);
      return value;
    }
  };
  const bulkTransactionId = function(value) {
    if (value === undefined) {
      return $bulkTransactionId.val();
    } else {
      $bulkTransactionId.val(value);
      return value;
    }
  };

  // Event dispatcher, so to say
  const applyComposerControls = function(request, validateLockCB) {

    if (request === undefined) {
      throw Error('Request is undefined');
    }

    $.fn.cafevTooltip.hide();

    if (validateLockCB === undefined) {
      validateLockCB = function(lock) {};
    }

    const validateLock = function() {
      pageBusyIcon(true);
      validateLockCB(true);
    };

    const validateUnlock = function() {
      validateLockCB(false);
      pageBusyIcon(false);
    };

    // until end of validation
    validateLock(true);

    const url = generateComposerUrl(request);
    let post = '';
    if (request.singleItem) {
      // Only serialize the request, no need to post all data around.
      post = $.param({ emailComposer: request });
    } else {
      if (request.submitAll) {
        // Everything is greedily submitted ...
        post = form.serialize();
      } else {
        // Serialize almost everything and submit it
        post = $fieldset.serialize();
        post += '&' + form.find('fieldset.form-data').serialize();
      }
      const $this = $(this);
      if ($this.is(':button') || $this.is(':submit')) {
        post += '&' + $.param($this);
      }
      // add the request itself as data
      post += '&' + $.param({ emailComposer: request });
    }

    const noDebug = request.noDebug || false;
    if (!noDebug) {
      debugOutput.html('');
    }
    $.post(url, post)
      .fail(function(xhr, textStatus, errorThrown) {
        ajaxHandleError(xhr, textStatus, errorThrown, function(data) {
          let debugText = '';
          if (data.caption !== undefined) {
            debugText += '<div class="error caption">' + data.caption + '</div>';
          }
          if (!Array.isArray(data.message)) {
            data.message = [data.message || ''];
          }
          if (data.message !== undefined) {
            debugText += data.message.join(' ');
          }
          debugOutput.html(debugText);
          validateUnlock();
        });
      })
      .done(function(data) {
        let postponeEnable = false;
        $.fn.cafevTooltip.remove();
        if (!ajaxValidateResponse(
          data,
          ['projectId', 'projectName', 'operation', 'requestData'], validateUnlock)) {
          return false;
        }

        projectId(data.projectId);

        const operation = data.operation;
        const topic = data.topic;
        const requestData = data.requestData;
        switch (operation) {
        case 'send':
          SelectUtils.replaceOptions($draftEmailsSelector, requestData.draftEmailOptions);
          SelectUtils.replaceOptions($sentEmailsSelector, requestData.sentEmailOptions);
          if (data.message !== undefined && data.caption !== undefined) {
            Dialogs.info(data.message, data.caption, undefined, true, true);
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
            WysiwygEditor.removeEditor(panelHolder.find('textarea.wysiwyg-editor'));
            panelHolder.html(requestData.elementData);
            $fieldset = panelHolder.find('fieldset.email-composition.page');
            emailFormCompositionHandlers($fieldset, form, dialogHolder, panelHolder);
            break;
          case 'element': {
            const formElements = Array.isArray(requestData.formElement)
              ? requestData.formElement
              : [requestData.formElement];
            for (const formElement of formElements) {
              const elementData = requestData.elementData[formElement];
              switch (formElement) {
              case 'to': {
                const toSpan = $fieldset.find('span.email-recipients');
                let rcpts = elementData;
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
                const options = elementData.options;
                const fileAttachments = elementData.attachments;
                const fileAttachmentsHolder = $fieldset.find('input.file-attachments');
                fileAttachmentsHolder.val(JSON.stringify(fileAttachments));
                $fileAttachmentsRow.toggleClass('empty-selection', $fileAttachmentsSelector.val().length === 0);
                $fileAttachmentsRow.toggleClass('no-attachments', options.length === 0);
                SelectUtils.replaceOptions($fileAttachmentsSelector, options);
                panelHolder.trigger('resize', { position: 'bottom' });
                break;
              }
              case 'eventAttachments': {
                const options = elementData.options;
                // const eventAttachments = requestData.elementData.attachments;
                $eventAttachmentsRow.toggleClass('no-attachments', options.length === 0);
                $eventAttachmentsRow.toggleClass('empty-selection', $eventAttachmentsSelector.val().length === 0);
                SelectUtils.replaceOptions($eventAttachmentsSelector, options);
                panelHolder.trigger('resize');
                break;
              }
              default:
                postponeEnable = true;
                Dialogs.alert(
                  t(appName, 'Unknown form element: {formElement}', { formElement }),
                  t(appName, 'Error'),
                  validateUnlock,
                  true, true);
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
            const dataItem = $fieldset.find('input[name="emailComposer[messageDraftId]"]');
            dataItem.val('');
            $fieldset.find('input[name^="emailComposer[referencing]"]').remove();
            $fieldset.find('input[name^="emailComposer[inReplyTo]"]').val('');
            // currentTemplate.val(requestData.emailTemplateName);
            WysiwygEditor.updateEditor(messageText, requestData.message);
            $fieldset.find('input.email-subject').val(requestData.subject);

            $templateEmailsSelector.next().removeClass('loading');
            break;
          }
          case 'draft': {
            $.fn.cafevTooltip.remove();

            // replace the entire composer tab
            WysiwygEditor.removeEditor(panelHolder.find('textarea.wysiwyg-editor'));
            panelHolder.html(requestData.composerForm);
            $fieldset = panelHolder.find('fieldset.email-composition.page');
            emailFormCompositionHandlers($fieldset, form, dialogHolder, panelHolder);

            // replace the recipients tab as well ...
            const rcptPanelHolder = dialogHolder.find('div#emailformrecipients');
            rcptPanelHolder.html(requestData.recipientsForm);
            const rcptFieldSet = form.find('fieldset.email-recipients.page');
            emailFormRecipientsHandlers(rcptFieldSet, form, dialogHolder, rcptPanelHolder);

            // adjust the title of the dialog
            let dlgTitle = '';
            if (requestData.projectId > 0) {
              dlgTitle = t(appName, 'Em@il Form for {projectName}', { projectName: requestData.projectName });
            } else {
              dlgTitle = t(appName, 'Em@il Form');
            }
            dialogHolder.dialog('option', 'title', dlgTitle);

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
            WysiwygEditor.removeEditor(panelHolder.find('textarea.wysiwyg-editor'));
            panelHolder.html(requestData.composerForm);
            $fieldset = panelHolder.find('fieldset.email-composition.page');
            emailFormCompositionHandlers($fieldset, form, dialogHolder, panelHolder);

            // replace the recipients tab ...
            const rcptPanelHolder = dialogHolder.find('div#emailformrecipients');
            rcptPanelHolder.html(requestData.recipientsForm);
            const rcptFieldSet = form.find('fieldset.email-recipients.page');
            emailFormRecipientsHandlers(rcptFieldSet, form, dialogHolder, rcptPanelHolder);

            const dataItem = $fieldset.find('input[name="emailComposer[messageDraftId]"]');
            dataItem.val('');
            saveAsTemplate.prop('checked', false).trigger('change');
            // WysiwygEditor.updateEditor(messageText, requestData.message);
            // $fieldset.find('input.email-subject').val(requestData.subject);

            // Make the debug output less verbose
            delete requestData.composerForm;
            delete requestData.recipientsForm;

            updateComposerElements(form);

            // deselect menu item
            SelectUtils.deselectAll($sentEmailsSelector);
            break;
          }
          }
          break; // load
        case 'save':
          switch (topic) {
          case 'template':
            SelectUtils.replaceOptions($templateEmailsSelector, requestData.templateEmailOptions);
            break;
          case 'draft': {
            // perhaps rather use data stuff in the future ...
            const dataItem = $fieldset.find('input[name="emailComposer[messageDraftId]"]');
            dataItem.val(requestData.messageDraftId);
            SelectUtils.replaceOptions($draftEmailsSelector, requestData.draftEmailOptions);
            break;
          }
          }
          break; // save
        case 'delete':
          switch (topic) {
          case 'template':
            // currentTemplate.val(requestData.emailTemplateName);
            WysiwygEditor.updateEditor(messageText, requestData.message);
            SelectUtils.replaceOptions($templateEmailsSelector, requestData.templateEmailOptions);
            break;
          case 'draft': {
            const dataItem = $fieldset.find('input[name="emailComposer[messageDraftId]"]');
            dataItem.val('');
            SelectUtils.replaceOptions($draftEmailsSelector, requestData.draftEmailOptions);
            break;
          }
          }
          break; // delete
        default:
          postponeEnable = true;
          data.message =
            t(appName, 'Unknown request: {operation} / {topic}', { operation, topic });
          data.caption = t(appName, 'Error');
          Dialogs.alert(data.message, data.caption, validateUnlock, true, true);
          break;
        } // switch (operation)

        if (!noDebug) {
          let debugText = '';
          if (data.caption !== undefined) {
            debugText += '<div class="error caption">' + data.caption + '</div>';
          }
          if (data.message !== undefined) {
            debugText += data.message;
          }
          if (data.debug !== undefined) {
            debugText += '<pre>' + data.debug + '</pre>';
          }
          if (debugText !== '') {
            let addOn;
            addOn = JSON.stringify(Object.fromEntries(new URLSearchParams(post)), null, 2);
            addOn = $('<div></div>').text(addOn).html();
            debugText += '<pre>post = ' + addOn + '</pre>';
            addOn = JSON.stringify(requestData, null, 2);
            addOn = $('<div></div>').text(addOn).html();
            debugText += '<pre>requestData = ' + addOn + '</pre>';
            debugOutput.html(debugText);
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

  sendButton
    .off('click')
    .on(
      'click',
      debounce(
        function(event) {
          const $this = $(this);

          // try to provide status feed-back for large transfers or
          // sending to many recipients. To this end we poll a special
          // data-base table. If not finished after 5 seconds, we pop-up a
          // dialog with status information.

          const progressWrapper = dialogHolder.find('div#sendingprogresswrapper');

          const pollProgress = function(id, current, target, data) {
            const value = current;
            const max = target;
            const rel = value / max * 100.0;
            let progressTitle;
            progressWrapper.find('div.messagecount').html(progressTitle);
            if (data.total > 1) {
              progressTitle =
            t(appName, 'Sending message {active} out of {total}', data);
            } else {
              progressTitle = t(appName, 'Message delivery in progress');
            }
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
            .done(function(data) {
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
                    fail(xhr, status, errorThrown) { ajaxHandleError(xhr, status, errorThrown); },
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
                $this, {
                  operation: 'send',
                  progressToken,
                  send: 'ThePointOfNoReturn',
                  submitAll: true,
                  projectId: projectId(),
                  projectName: projectName(),
                },
                function(lock) {
                  if (lock) {
                    $(window).on('beforeunload', function(event) {
                      return t(appName, 'Email sending is in progress. Leaving the page now will cancel the email submission.');
                    });
                    dialogWidget.addClass(pmeToken('table-dialog-blocked'));
                  } else {
                    $(window).off('beforeunload');
                    if (progressOpen) {
                      progressWrapper.dialog('close');
                    }
                    dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
                  }
                });
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
    .on('click', function(event) {

      const post = form.serialize();

      pageBusyIcon(true);

      $.post(generateComposerUrl('preview'), post)
        .fail(function(xhr, textStatus, errorThrown) {
          ajaxHandleError(xhr, textStatus, errorThrown, function(data) {
            let debugText = '';
            if (data.caption !== undefined) {
              debugText += '<div class="error caption">' + data.caption + '</div>';
            }
            if (data.message !== undefined) {
              debugText += data.message;
            }
            const hasPreviewMessages = data.requestData && data.requestData.previewData;
            if (hasPreviewMessages) {
              debugText += data.requestData.previewData;
            }
            debugOutput.html(debugText);
            debugOutput.find('.for-dialog').addClass('hidden');

            pageBusyIcon(false);

            if (hasPreviewMessages) {
              dialogHolder.tabs('option', 'active', 2);
            }

            $.fn.cafevTooltip.remove();
          });
        })
        .done(function(data) {
          if (!ajaxValidateResponse(
            data, ['contents'], function() {
              pageBusyIcon(false);
            })) {
            return false;
          }

          debugOutput.html(data.contents);

          pageBusyIcon(false);

          dialogHolder.tabs('option', 'active', 2);

          $.fn.cafevTooltip.remove();
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
    .on('click', function(event) {
      applyComposerControls.call(this, {
        operation: 'cancel',
        formStatus: 'submitted',
        singleItem: true,
        projectId: projectId(),
        projectName: projectName(),
      });
      // Close the dialog in any case.
      dialogHolder.dialog('close');
      return false;
    });

  /*************************************************************************
   *
   * Template handling (save, delete, load)
   */

  const autoSaveSeconds = draftAutoSave.data('auto-save-interval') || 300;
  const autoSaveTimeout = autoSaveSeconds * 1000;

  const autoSaveHandler = function() {
    if (Email.autoSaveTimer) {
      clearTimeout(Email.autoSaveTimer);
    }
    Email.autoSaveTimer = null;
    applyComposerControls(
      {
        operation: 'save',
        topic: 'draft',
        submitAll: true,
        noDebug: true,
        projectId: projectId(),
        projectName: projectName(),
        autoSave: true,
      },
      function(lock) {
        if (!lock) {
          // restart timer when ready
          if (Email.autoSaveTimer) {
            return;
          }
          Email.autoSaveTimer = setTimeout(autoSaveHandler, autoSaveTimeout);
        }
      });
  };

  const confirmAutoSaveDelete = function(doDelete) {
    const draftId = parseInt($fieldset.find('input[name="emailComposer[messageDraftId]"]').val());
    if (draftId <= 0) {
      return;
    }
    const autoGenerated = $draftEmailsSelector.find('option[value="__draft-' + draftId + '"]').data('autoGenerated') || false;
    if (autoGenerated && (doDelete || draftAutoSave.prop('checked'))) {
      Dialogs.confirm(
        t(appName,
          'Do you want to delete the auto-save backup copy of the current message-draft (id = {id})?'
          + '<br/>'
          + 'If you answer "no" then the current message will be saved again and marked as manually saved. '
          + 'It will then linger on until you or someone else deletes it manually.',
          { id: draftId }),
        t(appName, 'Delete Auto-Save Draft?'),
        function(confirmed) {
          if (confirmed) {
            applyComposerControls(
              {
                operation: 'delete',
                topic: 'draft',
                messageDraftId: draftId,
                noDebug: true,
                projectId: projectId(),
                projectName: projectName(),
              },
            );
          } else {
            // perform a manual save to clear the "autoGenerated" flag
            applyComposerControls(
              {
                operation: 'save',
                topic: 'draft',
                submitAll: true,
                noDebug: true,
                projectId: projectId(),
                projectName: projectName(),
                autoSave: false,
              });
          }
        },
        true,
        true,
      );
    }
  };
  Email.autoSaveDelete = confirmAutoSaveDelete;

  const startDraftAutoSave = function($element) {
    if (Email.autoSaveTimer) {
      clearTimeout(Email.autoSaveTimer);
      Email.autoSaveTimer = null;
    }
    if ($element.prop('checked')) {
      // perhaps add a popup to set the auto-save timeout
      Email.autoSaveTimer = setTimeout(autoSaveHandler, autoSaveTimeout);
    }
  };

  draftAutoSave
    .off('change')
    .on('change', function(event) {
      const $this = $(this);
      startDraftAutoSave($this);
      $.post(setPersonalUrl('email-draft-auto-save'), {
        value: $this.prop('checked') ? autoSaveSeconds : 0,
      })
        .fail(function(xhr, status, errorThrown) {
          ajaxHandleError(xhr, status, errorThrown, function() {
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

  startDraftAutoSave(draftAutoSave);

  saveAsTemplate
    .off('change')
    .on('change', function(event) {
      $templateEmailsSelector.next().toggleClass('expanded', saveAsTemplate.is(':checked'));
      return false;
    });

  $fieldset
    .find('input.submit.save-message')
    .off('click')
    .on('click', function(event) {
      const self = this;

      if (saveAsTemplate.is(':checked')) {
        const request = { operation: 'save', topic: 'template', submitAll: true };
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
            true);
        } else {
          applyComposerControls.call(self, request);
        }
      } else {
        applyComposerControls.call(self, {
          operation: 'save',
          topic: 'draft',
          submitAll: true,
          noDebug: true,
          projectId: projectId(),
          projectName: projectName(),
          autoSave: false,
        });
      }
      return false;
    });

  $fieldset
    .find('input.submit.delete-message')
    .off('click')
    .on('click', function(event) {
      const self = this;

      if (saveAsTemplate.is(':checked')) {
        // We do a quick client-side validation and ask the user for ok.
        const current = SelectUtils.selected($templateEmailsSelector);
        if ($templateEmailsSelector.find('option').filter(function() {
          return $(this).html().trim() === current;
        }).length > 0) {
          Dialogs.confirm(
            t(appName, 'Do you really want to delete the template with the name `{emailTemplateName}\'?',
              { emailTemplateName: current }),
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
            true);
        } else {
          Dialogs.alert(
            t(appName, 'Cannot delete non-existing template `{emailTemplateName}\'',
              { emailTemplateName: current }),
            t(appName, 'Unknown Template'));
        }
      } else {
        const draftId = $fieldset.find('input[name="emailComposer[messageDraftId]"]').val();

        if (draftId > 0) {
          // find the draft data in the select which we mis-use as data-storage here
          const $draftOption = SelectUtils.optionByValue($draftEmailsSelector, '__draft-' + draftId);
          let draftMeta = '';
          if ($draftOption.length === 1) {
            const title = $draftOption.attr('title') || $draftOption.attr('data-original-title') || $draftOption.html();
            draftMeta = '<br/>' + title;
          }
          Dialogs.confirm(
            t(appName,
              'Do you really want to delete the backup copy of the current message (id = {id})?',
              { id: draftId })
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
            true);
        }
      }
      return false;
    });

  $draftEmailsSelector
    .off('change')
    .on('change', function(event) {

      const choice = $draftEmailsSelector.val();
      if (choice.match(/__draft--1/)) {
        Dialogs.alert(
          t(appName, 'There are currently no stored draft messages available.'),
          t(appName, 'No Drafts Available'),
          function() {
            SelectUtils.deselectAll($draftEmailsSelector);
          });
      } else {
        applyComposerControls.call(
          this, {
            operation: 'load',
            topic: 'draft',
            projectId: projectId(),
            projectName: projectName(),
          },
          function(lock) {
            if (lock) {
              dialogWidget.addClass(pmeToken('table-dialog-blocked'));
            } else {
              dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
              dialogHolder.tabs('option', 'disabled', []);
            }
          });
      }
      return false;
    });

  $templateEmailsSelector
    .off('change')
    .on('change', function(event) {
      console.info('CHANGE', this);
      const $this = $(this);

      if ($this.val() && $this.val() !== $this.data('ignoreChange')) {
        applyComposerControls.call(this, {
          operation: 'load',
          topic: 'template',
          projectId: projectId(),
          projectName: projectName(),
        });
      } else {
        $templateEmailsSelector.next().removeClass('loading');
        $this.removeData('ignoreChange');
      }

      return false;
    });

  $sentEmailsSelector
    .off('change')
    .on('change', function(event) {

      applyComposerControls.call(
        this, {
          operation: 'load',
          topic: 'sent',
          projectId,
          projectName,
        },
        function(lock) {
          if (lock) {
            dialogWidget.addClass(pmeToken('table-dialog-blocked'));
          } else {
            SelectUtils.deselectAll($sentEmailsSelector);
            dialogWidget.removeClass(pmeToken('table-dialog-blocked'));
            dialogHolder.tabs('option', 'disabled', []);
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
      'blur', 'input.email-subject, input.sender-name',
      function(event) {
        const $self = $(this);
        $self.val($self.val().trim());
        return false;
      });

  /**************************************************************************
   *
   */

  $fieldset.find('input.save-from-tag')
    .off('click')
    .on('click', function(event) {
      const $selectedFrom = $(this).closest('td.email-from').find('input[type=radio]:checked');
      const sender = $selectedFrom.next().text().trim();
      const configKey = 'defaultEmailFromAddress';
      const configValue = $selectedFrom.val();
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
  discloseRecipients
    .off('change')
    .on('change', function(event) {
      const $this = $(this);

      if ($this.prop('checked')) {
        Dialogs.confirm(
          t(appName,
            'Do you really want to disclose the bulk-message recipients?'
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
   * @param {object} event TBD.
   *
   * @param {string} header TBD.
   *
   * @returns {boolean}
   */
  const carbonCopyBlur = function(event, header) {
    const $self = $(this);
    const request = {
      operation: 'validateEmailRecipients',
      recipients: $self.val(),
      header,
      singleItem: true,
      projectId: projectId(),
      projectName: projectName(),
    };
    request[header] = request.Recipients; // remove duplicate later
    applyComposerControls.call(
      this, request,
      function(lock) {
        sendButton.prop('disabled', lock);
        $self.prop('disabled', lock);
        // if (lock) {
        //   self.off('blur');
        // } else {
        //   self.on('blur', function(event) {
        //     carbonCopyBlur.call(this, event, header);
        //   });
        // }
      });
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
    .on('click', function(event) {
      $subjectTagContainer.removeClass('display').addClass('edit');
      return false;
    });
  $subjectTagContentEditable
    .off('blur')
    .on('blur', function(event) {
      $subjectTagContentDisplay.html($subjectTagContentEditable.val());
      $subjectTagContainer.addClass('display').removeClass('edit');
      return false;
    });

  /*************************************************************************
   *
   * Project events attachments
   */

  $fieldset
    .find('button.attachment.events')
    .off('click')
    .on('click', function(event) {
      const wasVisible = $eventAttachmentsRow.is(':visible');
      let events = $eventAttachmentsSelector.val();
      if (!events) {
        events = [];
      }
      $eventAttachmentsRow.addClass('show-selectable');

      if (wasVisible !== $eventAttachmentsRow.is(':visible')) {
        panelHolder.trigger('resize', { position: 'bottom' });
      }

      asyncEmit(
        PROJECT_EVENTS_LISTING, {
          projectName: projectName(),
        },
      ).then(() => {
        if (events.length > 0) {
          asyncEmit(LEGACY_UPDATE_EVENTS_SELECTION, {
            origin: 'EmailForm',
            projectgId: projectId(),
            projectName: projectName(),
            selection: events,
          });
        }
      });

      return false;
    });

  const updateEventAttachments = (projectId, projectName, events) => {
    const requestData = {
      operation: 'update',
      topic: 'element',
      formElement: 'eventAttachments',
      singleItem: true,
      attachedEvents: events,
      projectId,
      projectName,
    };
    applyComposerControls.call(dialogHolder, requestData);
  };

  // Update our selected events on request
  dialogHolder
    .off(appName + ':events_changed')
    .on(appName + ':events_changed', function(event, events) {
      updateEventAttachments(projectId(), projectName(), events);
      return false;
    });

  $fieldset
    .find('tr.attachments input.visibility-toggle')
    .off('click')
    .on('click', function(event) {
      const $this = $(this);
      const $row = $this.closest('tr');
      $row.removeClass('show-selectable').addClass('hidden');
      panelHolder.trigger('resize', { position: 'bottom' });
    });

  $fieldset
    .find('tr.all-attachments button.visibility-toggle')
    .off('click')
    .on('click', function(event) {
      const $attachmentRows = $('tr.attachments');
      if ($attachmentRows.filter(':visible').length > 0) {
        $attachmentRows.removeClass('show-selectable').addClass('hidden');
      } else {
        $attachmentRows.addClass('show-selectable').removeClass('hidden');
      }
      panelHolder.trigger('resize', { position: 'bottom' });
    });

  $fieldset
    .find('input.delete-all-event-attachments')
    .off('click')
    .on('click', function(event) {
      const wasVisible = $eventAttachmentsRow.is(':visible');

      const numSelected = $eventAttachmentsSelector.val().length;
      const numOptions = $eventAttachmentsSelector.find('option').length;

      // must this be here?
      $eventAttachmentsRow.toggleClass('no-attachments', numOptions === 0);

      if (numSelected === 0) {
        $eventAttachmentsRow.removeClass('show-selectable');
        if (wasVisible !== $eventAttachmentsRow.is(':visible')) {
          panelHolder.trigger('resize', { position: 'bottom' });
        }
      } else {
        // Ask for confirmation
        Dialogs.confirm(
          t(appName,
            'Do you really want to delete all event attachments?'),
          t(appName, 'Really Delete Attachments?'),
          function(confirmed) {
            if (!confirmed) {
              return false;
            }
            // simply void the selection
            SelectUtils.deselectAll($eventAttachmentsSelector);
            $eventAttachmentsRow.removeClass('show-selectable');
            $eventAttachmentsRow.addClass('empty-selection');

            if (wasVisible !== $eventAttachmentsRow.is(':visible')) {
              panelHolder.trigger('resize', { position: 'bottom' });
            }

            return false;
          },
          true);
      }

      return false;
    });

  $eventAttachmentsSelector
    .off('change')
    .on('change', function(event) {
      const $this = $(this);
      let events = $this.val();
      if (events.length === 0) {
        events = [];
      }
      $this.closest('tr')
        .toggleClass('empty-selection', events.length === 0)
        .toggleClass('no-attachments', $this.find('option').length === 0);
      asyncEmit(LEGACY_UPDATE_EVENTS_SELECTION, {
        origin: 'EmailForm',
        projectgId: projectId(),
        projectName: projectName(),
        selection: events,
      });
      return false;
    });

  /*************************************************************************
   *
   * File upload.
   */

  $fileAttachmentsSelector.on('change', function(event) {
    const $this = $(this);
    $this.closest('tr')
      .toggleClass('empty-selection', $this.val().length === 0)
      .toggleClass('no-attachments', $this.find('option').length === 0);
    return false;
  });

  const updateFileAttachments = function() {
    const fileAttachments = $fieldset.find('input.file-attachments').val();
    const selectedAttachments = $fileAttachmentsSelector.val();

    const requestData = {
      operation: 'update',
      topic: 'element',
      singleItem: true,
      formElement: 'fileAttachments',
      fileAttachments, // JSON data of all files
      formStatus: 'submitted',
      projectId: projectId(),
      projectName: projectName(),
    };
    if (selectedAttachments) {
      requestData.attachedFiles = selectedAttachments;
    }
    applyComposerControls.call(this, requestData);
    return false;
  };

  // Arguably, these should only be active if the
  // composer tab is active. Mmmh.
  fileUploadInit({
    url: generateEmailFormUrl('attachment/upload'),
    doneCallback(json, index, container) {
      attachmentFromJSON(json, { origin: 'upload', index });
    },
    stopCallback: updateFileAttachments,
    dropZone: null, // initially disabled, enabled on tab-switch
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
      let folderPromise;
      if (projectId() > 0) {
        folderPromise = $.get(generateAppUrl('projects/' + projectId() + '/folder/project'));
      } else {
        const deferred = $.Deferred();
        folderPromise = deferred.promise();
        deferred.resolve({ folder: globalState.sharedFolder });
      }

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
      $fileAttachmentsRow.addClass('show-selectable');
      if (wasVisible !== $fileAttachmentsRow.is(':visible')) {
        panelHolder.trigger('resize', { position: 'bottom' });
      }
      return false;
    });

  $fieldset
    .find('input.delete-all-file-attachments')
    .off('click')
    .on('click', function(event) {
      const wasVisible = $fileAttachmentsRow.is(':visible');

      const numSelected = $fileAttachmentsSelector.val().length;
      const numOptions = $fileAttachmentsSelector.find('option').length;

      $fileAttachmentsRow.toggleClass('no-attachments', numOptions === 0);

      if (numSelected === 0) {
        $fileAttachmentsRow.removeClass('show-selectable');
        if (wasVisible !== $fileAttachmentsRow.is(':visible')) {
          panelHolder.trigger('resize', { position: 'bottom' });
        }
      } else {
        // Ask for confirmation
        Dialogs.confirm(
          t(appName,
            'Do you really want to delete all file attachments?'),
          t(appName, 'Really Delete Attachments?'),
          function(confirmed) {
            if (!confirmed) {
              return false;
            }
            // simply void the selection
            SelectUtils.deselectAll($fileAttachmentsSelector);
            $fileAttachmentsRow.removeClass('show-selectable');
            $fileAttachmentsRow.addClass('empty-selection');

            if (wasVisible !== $fileAttachmentsRow.is(':visible')) {
              panelHolder.trigger('resize', { position: 'bottom' });
            }
            return false;
          },
          true);
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
    .on('click', function(event) {

      const self = $(this);
      const input = $fieldset.find(self.data('for'));

      self.addClass('loading');
      const cleanup = function() {
        self.removeClass('loading');
      };

      if (input.val().trim() !== '') {
        // We trigger validation before we pop-up, but no need to do
        // so on empty input.
        input.trigger('blur');
      }

      const post = { freeFormRecipients: input.val() };
      $.post(generateEmailFormUrl('contacts/list'), post)
        .fail(function(xhr, status, errorThrown) {
          ajaxHandleError(xhr, status, errorThrown, cleanup);
        })
        .done(function(data) {
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
              // compare with pme.js
              valueField: 'email',
              labelField: 'email',
              searchField: ['email'],
              lockOptgroupOrder: true,
              create(input, setterCallback) {
                const selectize = this;
                $.post(generateAppUrl('validate/musicians/email'), {
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
                    setterCallback(data);
                    selectize
                      .$input
                      .closest('.ui-dialog.address-book-emails')
                      .find('button.save-contacts').prop('disabled', false);
                  });
              },
            },
            buttons: [
              {
                text: t(appName, 'Save Contacts'),
                class: 'save-contacts',
                title: t(
                  appName, 'Save the selected supplementary emails to the address-book for later reusal.'),
                click() {
                  const $dialogHolder = $(this);
                  const $selectElement = $dialogHolder.find('select');
                  const selectize = $selectElement[0].selectize;
                  const selectedValues = SelectUtils.selected($selectElement);
                  const selectedFreeForm = selectize.items
                    .map(email => selectize.options[email])
                    .filter(option => option.optgroup === freeFormLabel)
                    .map(option => ({
                      value: option.email,
                      html: option.email,
                      text: option.email,
                    }),
                    );
                  const innerPost = { addressBookCandidates: selectedFreeForm };
                  $.post(generateEmailFormUrl('contacts/save'), innerPost)
                    .fail(ajaxHandleError)
                    .done(function(data) {
                      $.post(generateEmailFormUrl('contacts/list'), post)
                        .fail(ajaxHandleError)
                        .done(function(data) {
                          if (!ajaxValidateResponse(data, ['contents'])) {
                            return;
                          }
                          const newOptions = $(data.contents).html();
                          SelectUtils.replaceOptions($selectElement, newOptions);
                          SelectUtils.selected($selectElement, selectedValues);
                          selectize
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
              of: self,
            },
            openCallback(selectElement) {
              cleanup();
              if (SelectUtils.children(selectElement).filter('optgroup.free-form').length === 0) {
                $(this).dialog('widget')
                  .find('button.save-contacts').prop('disabled', true);
              }
            },
            saveCallback(selectElement, selectedOptions) {
              let recipients = '';
              const numSelected = selectedOptions.length;
              if (numSelected > 0) {
                recipients += selectedOptions[0].text;
                for (let idx = 1; idx < numSelected; ++idx) {
                  recipients += ', ' + selectedOptions[idx].text;
                }
              }
              input.val(recipients);
              input.trigger('blur');
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

  panelHolder.off('resize.' + appName);
  panelHolder.on('resize.' + appName, function(event, eventData) {
    //    const eventData = event.data;
    emailTabResize(dialogWidget, panelHolder);
    if (eventData && eventData.position === 'bottom') {
      panelHolder.scrollTop(panelHolder.prop('scrollHeight'));
    }
  });
};

/**
 * Open the mass-email form in a popup window.
 *
 * @param {object|string} post Necessary post data, either serialized or as
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
 * - eventSelect: array of ids of events to attach.
 *
 * @param {boolean} modal TBD, default true.
 *
 * @param {boolean} single TBD, default false.
 *
 * @param {Function} afterInit TBD.
 */
function emailFormPopup(post, modal, single, afterInit) {

  if (typeof afterInit !== 'function') {
    afterInit = function() {};
  }

  if (Email.active === true) {
    console.debug('EMAIL ALREADY ACTIVE', Email);
    afterInit();
    return;
  }

  Email.active = true;
  console.debug('EMAIL ACTIVE TRUE', Email);

  if (modal === undefined) {
    modal = true;
  }
  if (single === undefined) {
    single = false;
  }
  if (typeof afterInit !== 'function') {
    afterInit = function() {};
  }
  $.post(generateEmailFormUrl('form'), post)
    .fail(function(xhr, status, errorThrown) {
      ajaxHandleError(xhr, status, errorThrown, function() {
        Email.active = false;
        console.debug('EMAIL ACTIVE FALSE', Email);
        afterInit(false);
      });
    })
    .done(function(data) {
      const containerId = 'emailformdialog';

      if (!ajaxValidateResponse(
        data, ['contents'], function() {
          Email.active = false;
          console.debug('EMAIL ACTIVE FALSE', Email);
          afterInit(false);
        })) {
        return false;
      }

      afterInit(true);

      const dialogHolder = $('<div id="' + containerId + '"></div>');
      dialogHolder.html(data.contents);
      $('body').append(dialogHolder);

      const $emailForm = $('form#' + appPrefix('email-form'));
      const $recipientsPanel = dialogHolder.find('div#emailformrecipients');
      const $composerPanel = dialogHolder.find('div#emailformcomposer');

      let dlgTitle = '';
      if (data.projectId > 0) {
        dlgTitle = t(appName, 'Em@il Form for {projectName}', { projectName: data.projectName });
      } else {
        dlgTitle = t(appName, 'Em@il Form');
      }

      if (modal) {
        modalizer(true);
      }

      const position = {
        my: 'center top',
        at: 'center bottom+50',
        of: '#header',
      };

      dialogHolder.cafevDialog({
        title: dlgTitle,
        position,
        width: 'auto',
        height: 'auto',
        modal: false, // modal,
        closeOnEscape: false,
        dialogClass: 'emailform custom-close',
        resizable: false,
        open() {
          $.fn.cafevTooltip.remove();
          DialogUtils.toBackButton(dialogHolder);
          DialogUtils.fullScreenButton(dialogHolder, function(mode, when) {
            if (when === 'before') {
              WysiwygEditor.removeEditor(dialogHolder.find('textarea.wysiwyg-editor'));
            }
            if (when === 'after') {
              WysiwygEditor.addEditor(dialogHolder.find('textarea.wysiwyg-editor'));
            }
          });
          DialogUtils.customCloseButton(dialogHolder, function(event, container) {
            event.stopImmediatePropagation();
            dialogHolder.find('input.submit.cancel[type="submit"]').trigger('click');
            // dialogHolder.dialog('close');
            return false;
          });
          const dialogWidget = dialogHolder.dialog('widget');

          // this must come before calling emailFormRecipientsHandlers
          dialogHolder.tabs({
            active: single ? 1 : 0,
            disabled: single ? [0] : [],
            heightStyle: 'content',
            create(event, ui) {
              emailTabResize(dialogWidget, ui.panel);
              return true;
            },
            activate(event, ui) {
              const newTabId = ui.newTab.attr('id');

              if (newTabId === 'emailformdebug-tab') {
                // The following is primarily for the debug
                // output in order to get the scroll-bars right
                const panel = ui.newPanel;
                let newHeight = dialogWidget.height()
                    - dialogWidget.find('.ui-dialog-titlebar').outerHeight(true);
                newHeight -= $('#emailformtabs').outerHeight(true);
                newHeight -= panel.outerHeight(true) - panel.height();
                panel.height(newHeight);
              } else {
                if (newTabId === 'emailformcomposer-tab') {
                  $('#attachment_upload_start').fileupload('option', 'dropZone', ui.newPanel);
                } else {
                  $('#attachment_upload_start').fileupload('option', 'dropZone', null);
                  const $recipientsFieldSet = $emailForm.find('fieldset.email-recipients.page');
                  emailFormRecipientsSelectControls(dialogHolder, $recipientsFieldSet);
                }

                // At least in FF there is also a resize event,
                // but only for the composition window. Don't
                // know why.
                emailTabResize(dialogWidget, ui.newPanel);
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

          handleUserManualMenu(dialogHolder);

          const $recipientsFieldSet = $emailForm.find('fieldset.email-recipients.page');
          const $composerFieldSet = $emailForm.find('fieldset.email-composition.page');

          // Fine, now add handlers and AJAX callbacks. We can
          // probably move some of the code above to the
          // respective tab-handler.
          emailFormRecipientsHandlers(
            $recipientsFieldSet,
            $emailForm,
            dialogHolder,
            $recipientsPanel);
          emailFormCompositionHandlers(
            $composerFieldSet,
            $emailForm,
            dialogHolder,
            $composerPanel);

          // download support
          dialogHolder.on('click', 'a.download-link.ajax-download', function(event) {
            const $this = $(this);
            fileDownload($this.attr('href'), $this.data());
            return false;
          });

          // we have to recompute the tab size for the recipients controls
          emailTabResize(dialogWidget, $recipientsPanel);

          dialogHolder.dialog('moveToTop');
          dialogWidget.position(position);
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
          WysiwygEditor.removeEditor(dialogHolder.find('textarea.wysiwyg-editor'));
          // dialogHolder.dialog('close');
          dialogHolder.dialog('destroy').remove();

          modalizer(false);
          Email.active = false;
          console.debug('EMAIL ACTIVE FALSE', Email);
        },
      });
      return false;
    });
}

const documentReady = function() {
};

export {
  documentReady,
  emailFormPopup,
};

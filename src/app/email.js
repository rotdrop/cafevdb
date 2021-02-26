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

import { globalState, appName, $, appPrefix } from './globals.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Page from './page.js';
import * as Dialogs from './dialogs.js';
import * as ProjectParticipants from './project-participants.js';
import * as Projects from './projects.js';
import * as WysiwygEditor from './wysiwyg-editor.js';
import * as FileUpload from './file-upload.js';
import * as Legacy from '../legacy.js';
import * as DialogUtils from './dialog-utils.js';
import * as ProgressStatus from './progress-status.js';
import generateUrl from './generate-url.js';
import print_r from './print-r.js';

require('bootstrap4-duallistbox');
require('emailform.scss');

const Email = globalState.Email = {
  enabled: true,
  active: false,
};

const attachmentFromJSON = function(response, info) {
  const fileAttachHolder = $('form.cafevdb-email-form fieldset.attachments input.file-attach');
  if (fileAttachHolder === '') {
    Dialogs.alert(t(appName, 'Not called from main email-form.'), t(appName, 'Error'));
    return;
  }

  let file = response.data;
  file.status = 'new';
  if (typeof info === 'object') {
    file = $.extend(file, info);
  }
  let fileAttach = fileAttachHolder.val();
  if (fileAttach === '') {
    fileAttach = [file];
  } else {
    fileAttach = $.parseJSON(fileAttach);
    fileAttach.push(file);
  }
  fileAttachHolder.val(JSON.stringify(fileAttach));
};

const cloudAttachment = function(path, callback) {
  $.getJSON(
    OC.filePath(appName, 'ajax', 'email/cloudattachment.php'), { path },
    function(response) {
      if (response !== undefined && response.status === 'success') {
        attachmentFromJSON(response, { origin: 'cloud' });
        if (typeof callback === 'function') {
          callback();
        }
      } else {
        Dialogs.alert(response.data.message, t(appName, 'Error'));
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
  if (panelHolder.get(0).scrollHeight > panelHolder.outerHeight(true)) {
    panelHolder.css('padding-right', '2.4em');
  } else {
    panelHolder.css('padding-right', '');
  }
}

/**
 * Add some extra JS stuff for the select boxes. This has to be
 * called when the tab is actually visible because the add-on
 * libraries use the size of the original controls as base for their
 * layout.
 *
 * @param {jQuery} dialogHolder TBD.
 *
 * @param {jQuery} fieldset TBD.
 */
const emailFormRecipientsSelectControls = function(dialogHolder, fieldset) {

  if (dialogHolder.tabs('option', 'active') !== 0
      || fieldset.find('#member_status_filter_chosen').length > 0) {
    // alert('active: ' + dialogHolder.tabs('option', 'active') +
    //       ' done: ' + fieldset.find('#member_status_filter_chosen').length);
    return;
  }

  fieldset.find('#member-status-filter').chosen();
  fieldset.find('#member-status-filter').chosen();
  fieldset.find('#instruments-filter option[value="*"]').remove();
  fieldset.find('#instruments-filter option[value=""]').remove();
  fieldset.find('#instruments-filter').chosen();
  fieldset.find('#recipients-select').bootstrapDualListbox(
    {
      // moveOnSelect: false,
      // preserveSelectionOnMove : 'all',
      moveAllLabel: t(appName, 'Move all'),
      moveSelectedLabel: t(appName, 'Move selected'),
      removeSelectedLabel: t(appName, 'Remove selected'),
      removeAllLabel: t(appName, 'Remove all'),
      nonSelectedListLabel: t(appName, 'Remaining Recipients'),
      selectedListLabel: t(appName, 'Selected Recipients'),
      infoText: '&nbsp;', // t(appName, 'Showing all {0}'),
      infoTextFiltered: '<span class="badge badge-warning">'
        + t(appName, 'Filtered')
        + '</span> {0} '
        + t(appName, 'from')
        + ' {1}',
      infoTextEmpty: t(appName, 'Empty list'),
      filterPlaceHolder: t(appName, 'Filter'),
      filterTextClear: t(appName, 'show all'),
      selectorMinimalHeight: 200,
    }
  );
  const dualSelect = fieldset.find('div.bootstrap-duallistbox-container select');
  dualSelect.attr(
    'title',
    t(appName, 'Click on the names to move the respective person to the other box'));
  dualSelect.addClass('tooltip-top');

  CAFEVDB.toolTipsInit(dialogHolder.find('div#emailformrecipients'));
};

/**
 * Add handlers to the control elements, and call the AJAX sciplets
 * for validation to update the recipients selection tab accordingly.
 *
 * @param {jQuery} fieldset The field-set enclosing the recipients selection part
 *
 * @param {jQuery} form TBD.
 *
 * @param {jQuery} dialogHolder The div holding the jQuery dialog for everything
 *
 * @param {jQuery} panelHolder The div enclosing the fieldset
 *
 * @returns {bool}
 */
const emailFormRecipientsHandlers = function(fieldset, form, dialogHolder, panelHolder) {

  emailFormRecipientsSelectControls(dialogHolder, fieldset);

  const recipientsSelect = fieldset.find('select#recipients-select');
  const missingAddresses = fieldset.find('.missing-email-addresses.names');
  const missingLabel = fieldset.find('.missing-email-addresses.label');
  const noMissingLabel = fieldset.find('.missing-email-addresses.label.empty');
  const filterHistoryInput = fieldset.find('#recipients-filter-history');
  const debugOutput = form.find('#emailformdebug');

  // Apply the instruments filter
  const applyRecipientsFilter = function(event, historySnapshot) {
    event.preventDefault();

    historySnapshot = typeof historySnapshot !== 'undefined';

    let post = fieldset.serialize();
    if (historySnapshot) {
      post += '&' + $.param({ emailRecipients: { HistorySnapshot: 'snapshot' } });
    } else {
      post += '&' + form.find('fieldset.form-data').serialize();
      if ($(this).is(':button')) {
        const tmp = {};
        tmp[$(this).attr('name')] = $(this).val();
        post += '&' + $.param(tmp);
      }
    }
    $.post(
      OC.filePath(appName, 'ajax/email', 'recipients.php'),
      post,
      function(data) {
        const requiredResponse = historySnapshot
          ? ['filterHistory']
          : ['recipientsOptions', 'missingEmailAddresses', 'filterHistory'];
        if (!Ajax.validateResponse(data, requiredResponse)) {
          return false;
        }
        if (historySnapshot) {
          // Just update the history, but nothing else
          filterHistoryInput.val(data.data.filterHistory);
        } else if (typeof data.data.contents !== 'undefined' && data.data.contents.length > 0) {
          // replace the entire tab.
          $.fn.cafevTooltip.remove();
          panelHolder.html(data.data.contents);
          fieldset = panelHolder.find('fieldset.email-recipients.page');
          Email.emailFormRecipientsHandlers(fieldset, form, dialogHolder, panelHolder);
        } else {
          // Here goes the real work
          // We only need to update the select-element and the list
          // of musicians which should be possible recipients but
          // do not have an email address.
          recipientsSelect.html(data.data.recipientsOptions);
          recipientsSelect.bootstrapDualListbox('refresh', true);
          filterHistoryInput.val(data.data.filterHistory);
          missingAddresses.html(data.data.missingEmailAddresses);
          if (data.data.missingEmailAddresses.length > 0) {
            missingLabel.removeClass('reallyhidden');
            noMissingLabel.addClass('reallyhidden');
          } else {
            missingLabel.addClass('reallyhidden');
            noMissingLabel.removeClass('reallyhidden');
          }
        }

        const filterHistory = data.data.filterHistory;
        if (filterHistory.historyPosition >= 0
            && filterHistory.historyPosition < filterHistory.historySize - 1) {
          // enable the undo button
          fieldset.find('#instruments-filter-undo').prop('disabled', false);
        } else {
          fieldset.find('#instruments-filter-undo').prop('disabled', true);
        }
        if (filterHistory.historyPosition > 0) {
          // enable the redo button as well
          fieldset.find('#instruments-filter-redo').prop('disabled', false);
        } else {
          fieldset.find('#instruments-filter-redo').prop('disabled', true);
        }

        if (!historySnapshot) {
          let debugText = '';
          if (typeof data.data.debug !== 'undefined') {
            debugText = data.data.debug;
          }
          debugOutput.html('<pre>' + $('<div></div>').text(debugText).html() + '</pre>'
                           + '<pre>'
                           + $('<div></div>').text(data.data.recipientsOptions).html()
                           + '</pre>'
                           + data.data.missingEmailAddresses
                           + '</br>'
                           + $('<div></div>').text(CAFEVDB.urlDecode(post)).html());
        }
        return false;
      });
    return false;
  };

  // Attach above function to almost every sensible control :)

  // Controls :..
  const controlsContainer = fieldset.find('.filter-controls.' + appPrefix('container'));

  // Instruments filter
  const instrumentsFilter = fieldset.find('.instruments-filter.' + appPrefix('container'));
  instrumentsFilter.on('dblclick', function(event) {
    applyRecipientsFilter.call(this, event);
  });
  // // Alternatively:
  // var instrumentsFilter = fieldset.find('.instruments-filter.' + appPrefix('container') + ' select');
  // instrumentsFilter.off('change');
  // instrumentsFilter.on('change', function(event) {
  //   applyRecipientsFilter.call(this, event);
  // });

  // Member status filter
  const memberStatusFilter = fieldset.find('select.member-status-filter');
  memberStatusFilter.off('change');
  memberStatusFilter.on('change', function(event) {
    applyRecipientsFilter.call(this, event);
  });

  // Basic set
  const basicRecipientsSet = fieldset.find('.basic-recipients-set.' + appPrefix('container') + 'r input[type="checkbox"]');
  basicRecipientsSet.off('change');
  basicRecipientsSet.on('change', function(event) {
    applyRecipientsFilter.call(this, event);
  });

  // "submit" when hitting any of the control buttons
  controlsContainer.off('click', '**');
  controlsContainer.on('click', 'input', function(event) {
    applyRecipientsFilter.call(this, event);
  });

  // Record history when the select box changes. Maybe too slow, but
  // we will see.
  recipientsSelect.off('change');
  recipientsSelect.on('change', function(event) {
    applyRecipientsFilter.call(this, event, true);
  });

  // Give the user a chance to change broken or missing email
  // addresses from here.
  dialogHolder.off('pmedialog:changed');
  dialogHolder.on('pmedialog:changed', function(event) {
    applyRecipientsFilter.call(this, event);
  });

  missingAddresses.off('click', 'span.personal-record');
  missingAddresses.on('click', 'span.personal-record', function(event) {
    event.preventDefault();

    const formData = form.find('fieldset.form-data');
    const projectId = formData.find('input[name="ProjectId"]').val();
    const projectName = formData.find('input[name="ProjectName"]').val();

    ProjectParticipants.personalRecordDialog(
      $(this).data('id'),
      {
        ProjectId: projectId,
        ProjectName: projectName,
        InitialValue: 'Change',
        AmbientContainerSelector: '#emailformdialog',
      });

    return false;
  });

  panelHolder
    .off('resize')
    .on('resize', function() {
      emailTabResize(dialogHolder.dialog('widget'), panelHolder);
    });

  return false;
};

/**
 * Add handlers to the control elements, and call the AJAX sciplets
 * for validation to update the message composition tab accordingly.
 *
 * @param {jQuery} fieldset The field-set enclosing the recipients selection part
 *
 * @param {jQuery} form TBD.
 *
 * @param {jQuery} dialogHolder The div holding the jQuery dialog for everything
 *
 * @param {jQuery} panelHolder The div enclosing the fieldset
 *
 */
const emailFormCompositionHandlers = function(fieldset, form, dialogHolder, panelHolder) {

  {
    WysiwygEditor.addEditor(dialogHolder.find('textarea.wysiwyg-editor'), undefined, '20em');

    $('#cafevdb-stored-messages-selector').chosen({ disable_search_threshold: 10 });

    const composerPanel = $('#emailformcomposer');
    const fileAttachmentsSelect = composerPanel.find('#file-attachments-selector');
    fileAttachmentsSelect.chosen();
    fileAttachmentsSelect.on('chosen:showing_dropdown', function(event) {
      composerPanel.stop().animate({
        scrollTop: composerPanel.prop('scrollHeight'),
      }, 2000);
      return true;
    });
    const eventAttachmentsSelect = composerPanel.find('#event-attachments-selector');
    eventAttachmentsSelect.chosen();
    eventAttachmentsSelect.on('chosen:showing_dropdown', function(event) {
      composerPanel.stop().animate({
        scrollTop: composerPanel.prop('scrollHeight'),
      }, 2000);
      return true;
    });

    CAFEVDB.toolTipsInit(dialogHolder.find('div#emailformcomposer'));
  }

  const debugOutput = form.find('#emailformdebug');
  const storedEmailsSelector = fieldset.find('select.stored-messages-selector');
  const currentTemplate = fieldset.find('#emailCurrentTemplate');
  const saveAsTemplate = fieldset.find('#check-save-as-template');
  const messageText = fieldset.find('textarea');
  const eventAttachmentsSelector = fieldset.find('select.event-attachments');
  const fileAttachmentsSelector = fieldset.find('select.file-attachments');
  const sendButton = fieldset.find('input.submit.send');
  const dialogWidget = dialogHolder.dialog('widget');

  // Event dispatcher, so to say
  const applyComposerControls = function(event, request, validateLockCB) {
    event.preventDefault();

    if (typeof validateLockCB === 'undefined') {
      validateLockCB = function(lock) {};
    }

    const validateLock = function() {
      validateLockCB(true);
    };

    const validateUnlock = function() {
      validateLockCB(false);
    };

    // until end of validation
    validateLock(true);

    let post = '';
    if (typeof request !== 'undefined' && request.SingleItem) {
      // Only serialize the request, no need to post all data around.
      post = $.param({ emailComposer: request });
    } else {
      if (typeof request !== 'undefined' && request.SubmitAll) {
        // Everything is greedily submitted ...
        post = form.serialize();
      } else {
        // Serialize almost everything and submit it
        post = fieldset.serialize();
        post += '&' + form.find('fieldset.form-data').serialize();
      }
      if ($(this).is(':button') || $(this).is(':submit')) {
        const tmp = {};
        tmp[$(this).attr('name')] = $(this).val();
        post += '&' + $.param(tmp);
      }
      if (typeof request !== 'undefined') {
        post += '&' + $.param({ emailComposer: request });
      }
    }
    // $.post(OC.filePath(appName, 'ajax/email', 'composer.php'),
    $.ajax({
      url: OC.filePath(appName, 'ajax/email', 'composer.php'),
      type: 'POST',
      data: post,
      dataType: 'json',
      async: true,
      success(data) {
        let postponeEnable = false;
        $.fn.cafevTooltip.remove();
        if (!Ajax.validateResponse(
          data,
          ['projectId', 'projectName', 'request', 'requestData'],
          validateUnlock)) {
          if (typeof data !== 'undefined' && typeof data.data !== 'undefined') {
            let debugText = '';
            if (typeof data.data.caption !== 'undefined') {
              debugText += '<div class="error caption">' + data.data.caption + '</div>';
            }
            if (typeof data.data.message !== 'undefined') {
              debugText += data.data.message;
            }
            if (typeof data.data.debug !== 'undefined') {
              debugText += '<pre>' + data.data.debug + '</pre>';
            }
            debugOutput.html(debugText);
          }
          return false;
        }
        const request = data.data.request;
        const requestData = data.data.requestData;
        switch (request) {
        case 'send':
          storedEmailsSelector.html(requestData.storedEmailOptions);
          CAFEVDB.selectMenuReset(storedEmailsSelector);
          if (typeof data.data.message !== 'undefined'
              && typeof data.data.caption !== 'undefined') {
            Dialogs.alert(data.data.message, data.data.caption, undefined, true, true);
          }
          break;
        case 'cancel':
          // status feed-back handled by general code.
          break;
        case 'update':
          switch (requestData.formElement) {
          case 'everything':
            // replace the entire tab.
            $.fn.cafevTooltip.remove();
            WysiwygEditor.removeEditor(panelHolder.find('textarea.wysiwyg-editor'));
            panelHolder.html(requestData.elementData);
            fieldset = panelHolder.find('fieldset.email-composition.page');
            emailFormCompositionHandlers(fieldset, form, dialogHolder, panelHolder);
            break;
          case 'TO': {
            const toSpan = fieldset.find('span.email-recipients');
            let rcpts = requestData.elementData;
            if (rcpts.length === 0) {
              rcpts = toSpan.data('placeholder');
            }
            const title = toSpan.data('titleIntro') + '<br>' + rcpts;

            toSpan.html(rcpts);
            toSpan.attr('title', title);
            toSpan.cafevTooltip();
            break;
          }
          case 'FileAttachments': {
            const options = requestData.elementData.options;
            // alert('options: '+JSON.stringify(options));
            const fileAttach = requestData.elementData.fileAttach;
            fieldset.find('input.file-attach').val(JSON.stringify(fileAttach));
            fileAttachmentsSelector.html(options);
            if (options.length > 0) {
              fieldset.find('tr.file-attachments').show();
            } else {
              fieldset.find('tr.file-attachments').hide();
            }
            fileAttachmentsSelector.trigger('chosen:updated');

            panelHolder.trigger('resize');
            break;
          }
          case 'EventAttachments': {
            const options = requestData.elementData.options;
            const eventAttach = requestData.elementData.eventAttach;
            // alert('options: '+JSON.stringify(options));
            // alert('options: '+JSON.stringify(requestData.elementData.eventAttach));
            eventAttachmentsSelector.html(options);

            if (/* options.length */ eventAttach.length > 0) {
              fieldset.find('tr.event-attachments').show();
            } else {
              fieldset.find('tr.event-attachments').hide();
            }
            eventAttachmentsSelector.trigger('chosen:updated');
            panelHolder.trigger('resize');

            break;
          }
          default:
            postponeEnable = true;
            Dialogs.alert(
              t(appName, 'Unknown form element: {FormElement}', { FormElement: requestData.formElement }),
              t(appName, 'Error'),
              validateUnlock,
              true, true);
            break;
          }
          break;
        case 'validateEmailRecipients':
          // already reported by the general error-handling functions
          break;
        case 'setTemplate': {
          const dataItem = fieldset.find('input[name="emailComposer[MessageDraftId]"]');
          dataItem.val(-1);
          currentTemplate.val(requestData.templateName);
          WysiwygEditor.updateEditor(messageText, requestData.message);
          fieldset.find('input.email-subject').val(requestData.subject);
          CAFEVDB.selectMenuReset(storedEmailsSelector);
          break;
        }
        case 'saveTemplate':
          storedEmailsSelector.html(requestData.storedEmailOptions);
          CAFEVDB.selectMenuReset(storedEmailsSelector);
          break;
        case 'deleteTemplate':
          currentTemplate.val(requestData.templateName);
          WysiwygEditor.updateEditor(messageText, requestData.message);
          storedEmailsSelector.html(requestData.storedEmailOptions);
          CAFEVDB.selectMenuReset(storedEmailsSelector);
          break;
        case 'saveDraft': {
          // perhaps rather use data stuff in the future ...
          storedEmailsSelector.html(requestData.storedEmailOptions);
          CAFEVDB.selectMenuReset(storedEmailsSelector);
          const dataItem = fieldset.find('input[name="emailComposer[MessageDraftId]"]');
          dataItem.val(requestData.messageDraftId);
          break;
        }
        case 'loadDraft': {
          $.fn.cafevTooltip.remove();

          // replace the entire composer tab
          WysiwygEditor.removeEditor(panelHolder.find('textarea.wysiwyg-editor'));
          panelHolder.html(requestData.composerForm);
          fieldset = panelHolder.find('fieldset.email-composition.page');
          emailFormCompositionHandlers(fieldset, form, dialogHolder, panelHolder);

          // replace the recipients tab as well ...
          const rcptPanelHolder = dialogHolder.find('div#emailformrecipients');
          rcptPanelHolder.html(requestData.recipientsForm);
          const rcptFieldSet = form.find('fieldset.email-recipients.page');
          emailFormRecipientsHandlers(rcptFieldSet, form, dialogHolder, rcptPanelHolder);

          // adjust the title of the dialog
          let dlgTitle = '';
          if (requestData.ProjectId >= 0) {
            dlgTitle = t(appName, 'Em@il Form for {ProjectName}', { ProjectName: requestData.ProjectName });
          } else {
            dlgTitle = t(appName, 'Em@il Form');
          }
          dialogHolder.dialog('option', 'title', dlgTitle);

          // update the "global" project name and id
          const formData = form.find('fieldset.form-data');
          formData.find('input[name="ProjectId"]').val(requestData.ProjectId);
          formData.find('input[name="ProjectName"]').val(requestData.ProjectName);
          formData.find('input[name="DebitNoteId"]').val(requestData.DebitNoteId);

          // Make the debug output less verbose
          delete requestData.composerForm;
          delete requestData.recipientsForm;

          // deselect menu item
          CAFEVDB.selectMenuReset(storedEmailsSelector);

          break;
        }
        case 'deleteDraft': {
          const dataItem = fieldset.find('input[name="emailComposer[MessageDraftId]"]');
          dataItem.val(-1);
          storedEmailsSelector.html(requestData.storedEmailOptions);
          CAFEVDB.selectMenuReset(storedEmailsSelector);
          break;
        }
        default:
          postponeEnable = true;
          data.data.message =
            t(appName, 'Unknown request: {Request}', { Request: request });
          data.data.caption = t(appName, 'Error');
          Dialogs.alert(data.data.message, data.data.caption, validateUnlock, true, true);
          break;
        }

        let debugText = '';
        if (typeof data.data.caption !== 'undefined') {
          debugText += '<div class="error caption">' + data.data.caption + '</div>';
        }
        if (typeof data.data.message !== 'undefined') {
          debugText += data.data.message;
        }
        if (typeof data.data.debug !== 'undefined') {
          debugText += '<pre>' + data.data.debug + '</pre>';
        }
        if (debugText !== '') {
          let addOn;
          addOn = print_r(CAFEVDB.queryData(post, true), true);
          addOn = $('<div></div>').text(addOn).html();
          debugText += '<pre>post = ' + addOn + '</pre>';
          addOn = print_r(requestData, true);
          addOn = $('<div></div>').text(addOn).html();
          debugText += '<pre>requestData = ' + addOn + '</pre>';
          debugOutput.html(debugText);
        }

        if (!postponeEnable) {
          validateUnlock();
        }
        return false;
      },
    });
    return false;
  };

  /*************************************************************************
   *
   * Finally send the entire mess to the recipients
   */
  sendButton
    .off('click')
    .on('click', function(event) {
      event.stopImmediatePropagation();

      // try to provide status feed-back for large transfers or
      // sending to many recipients. To this end we poll a special
      // data-base table. If not finished after 5 seconds, we pop-up a
      // dialog with status information.

      const initialTimeout = 3000;
      const pollingTimeout = 800;
      let progressTimer;
      const progressWrapper = dialogHolder.find('div#sendingprogresswrapper');
      let progressOpen = false;

      // @todo: first create the progress status and get its id, then
      // submit the progress status id with the send request to the server.
      const progressPromise = ProgressStatus.create(0, 0);

      const pollProgress = function() {
        $.post(
          OC.filePath(appName, 'ajax/email', 'progress.php'),
          { ProgressId: 0 },
          function(data) {
            let stop = false;
            if (progressOpen
                && typeof data !== 'undefined' && typeof data.progress !== 'undefined') {
              const progress = data.progress;
              const value = progress.current;
              const max = progress.target;
              const rel = value / max * 100.0;
              const tagData = $.parseJSON(progress.tag);
              let progressTitle;
              if (tagData.total > 1) {
                progressTitle =
                  t(appName, 'Sending message {active} out of {total}', tagData);
              } else {
                progressTitle = t(appName, 'Message delivery in progress');
              }
              progressWrapper.find('div.messagecount').html(progressTitle);
              if (tagData.proto === 'smtp') {
                progressWrapper.find('div.imap span.progressbar')
                  .progressbar('option', 'value', 0);
              }
              progressWrapper.find('div.' + tagData.proto + ' span.progressbar')
                .progressbar('option', 'value', rel);
              if (tagData.proto === 'imap' && rel === 100 && tagData.active === tagData.total) {
                stop = true;
              }
            }
            if (!stop) {
              progressTimer = setTimeout(pollProgress, pollingTimeout);
            }
          });
      };

      const initialTimer = setTimeout(function() {
        // alert('count: '+progressWrapper.length);
        // alert('count: '+progressWrapper.length);
        progressWrapper.show();
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
          },
          close() {
            progressOpen = false;
            // progressWrapper.find('span.progressbar').progressbar('destroy');
            progressWrapper.dialog('destroy');
            progressWrapper.hide();
          },
        });
        progressTimer = setTimeout(pollProgress, pollingTimeout);
      }, initialTimeout);

      applyComposerControls.call(
        this, event, {
          Request: 'send',
          Send: 'ThePointOfNoReturn',
          SubmitAll: true,
        },
        function(lock) {
          if (lock) {
            $(window).on('beforeunload', function(event) {
              return t(appName, 'Email sending is in progress. Leaving the page now will cancel the email submission.');
            });
            dialogWidget.addClass('pme-table-dialog-blocked');
          } else {
            $(window).off('beforeunload');
            clearTimeout(initialTimer);
            clearTimeout(progressTimer);
            if (progressOpen) {
              progressWrapper.dialog('close');
            }
            dialogWidget.removeClass('pme-table-dialog-blocked');
          }
        });
      return false;
    });

  /*************************************************************************
   *
   * Message export to html.
   */
  fieldset.find('input.submit.message-export')
    .off('click')
    .on('click', function(event) {

      let formPost = form.serialize();
      const post = {};
      post[$(this).attr('name')] = 'whatever';
      formPost += '&' + $.param(post);

      Page.busyIcon(true);

      $.post(
        OC.filePath(appName, 'ajax/email', 'preview.php'),
        formPost,
        function(data) {
          if (!Ajax.validateResponse(
            data, ['contents'], function() {
              Page.busyIcon(false);
            })) {
            return false;
          }

          debugOutput.html(data.data.contents);

          Page.busyIcon(false);

          dialogHolder.tabs('option', 'active', 2);

          $.fn.cafevTooltip.remove();

          return true;
        });
      return false;
    });

  // fieldset.find('input.submit.message-blah-export').
  //   off('click').
  //   on('click', function(event) {

  //   Page.busyIcon(true);

  //   var action = OC.filePath(appName, 'ajax/email', 'exporter.php');
  //   var formPost = form.serialize();
  //   var post = {};
  //   post[$(this).attr('name')] = "whatever";
  //   post['DownloadCookie'] = generateId();
  //   formPost += '&'+$.param(post);
  //   $.fileDownload(action, {
  //     httpMethod: 'POST',
  //     data: formPost,
  //     cookieName: 'email_preview_download',
  //     cookieValue: post['DownloadCookie'],
  //     cookiePath: oc_webroot,
  //     successCallback: function() {
  //       Page.busyIcon(false);
  //     },
  //     failCallback: function(responseHtml, url, error) {
  //       Dialogs.alert(t(appName, 'Unable to export message(s):')+
  //                        ' '+
  //                        responseHtml,
  //                        t(appName, 'Error'),
  //                        function() { Page.busyIcon(false); },
  //                        true, true);
  //     }
  //   });
  //   return false;
  // });

  /*************************************************************************
   *
   * Close the dialog
   */

  fieldset.find('input.submit.cancel')
    .off('click')
    .on('click', function(event) {
      applyComposerControls.call(this, event, {
        Request: 'cancel',
        Cancel: 'DoesNotMatter',
        FormStatus: 'submitted',
        SingleItem: true,
      });
      // Close the dialog in any case.
      dialogHolder.dialog('close');
      return false;
    });

  /*************************************************************************
   *
   * Template handling (save, delete, load)
   */

  saveAsTemplate
    .off('change')
    .on('change', function(event) {
      event.preventDefault();
      currentTemplate.prop('disabled', !saveAsTemplate.is(':checked'));
      return false;
    });

  fieldset.find('input.submit.save-message')
    .off('click')
    .on('click', function(event) {
      const self = this;

      event.preventDefault();

      if (saveAsTemplate.is(':checked')) {
        // We do a quick client-side validation and ask the user for ok
        // when a template with the same name is already present.
        const current = currentTemplate.val();
        if (storedEmailsSelector.find('option[value="' + current + '"]').length > 0) {
          Dialogs.confirm(
            t(appName, 'A template with the name `{TemplateName}\' already exists, '
              + 'do you want to overwrite it?', { TemplateName: current }),
            t(appName, 'Overwrite existing template?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, event, { Request: 'saveTemplate' });
              }
            },
            true);
        } else {
          applyComposerControls.call(self, event, { Request: 'saveTemplate' });
        }
      } else {
        applyComposerControls.call(self, event, { Request: 'saveDraft', SubmitAll: true });
      }
      return false;
    });

  fieldset.find('input.submit.delete-message')
    .off('click')
    .on('click', function(event) {
      const self = this;
      event.preventDefault();

      if (saveAsTemplate.is(':checked')) {
        // We do a quick client-side validation and ask the user for ok.
        const current = currentTemplate.val();
        if (storedEmailsSelector.find('option[value="' + current + '"]').length > 0) {
          Dialogs.confirm(
            t(appName, 'Do you really want to delete the template with the name `{TemplateName}\'?',
              { TemplateName: current }),
            t(appName, 'Really Delete Template?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, event, { Request: 'deleteTemplate' });
              }
            },
            true);
        } else {
          Dialogs.alert(
            t(appName, 'Cannot delete non-existing template `{TemplateName}\'',
              { TemplateName: current }),
            t(appName, 'Unknown Template'));
        }
      } else {
        const draft = fieldset.find('input[name="emailComposer[MessageDraftId]"]').val();

        if (draft >= 0) {
          Dialogs.confirm(
            t(appName,
              'Do you really want to delete the backup copy of the current message (id = {Id})?',
              { Id: draft }),
            t(appName, 'Really Delete Draft?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, event, {
                  Request: 'deleteDraft',
                  messageDraftId: draft,
                });
              }
            },
            true);
        }
      }
      return false;
    });

  storedEmailsSelector
    .off('change')
    .on('change', function(event) {

      event.preventDefault();

      const choice = storedEmailsSelector.val();
      if (choice.match(/^__draft-[0-9]+$/)) {
        applyComposerControls.call(
          this, event, { Request: 'loadDraft' },
          function(lock) {
            if (lock) {
              dialogWidget.addClass('pme-table-dialog-blocked');
            } else {
              dialogWidget.removeClass('pme-table-dialog-blocked');
              dialogHolder.tabs('option', 'disabled', []);
            }
          });
      } else if (choice.match(/__draft--1/)) {
        Dialogs.alert(
          t(appName, 'There are currently no stored draft messages available.'),
          t(appName, 'No Drafts Available'),
          function() {
            CAFEVDB.selectMenuReset(storedEmailsSelector);
          });
      } else {
        applyComposerControls.call(this, event, { Request: 'setTemplate' });
      }
      return false;
    });

  /*************************************************************************
   *
   * Subject and sender name. We simply trim the spaces away. Could also do this in JS.
   */
  fieldset
    .off('blur', 'input.email-subject, input.sender-name')
    .on(
      'blur', 'input.email-subject, input.sender-name',
      function(event) {
        event.stopImmediatePropagation();
        const self = $(this);
        self.val(self.val().trim());
        return false;
      });

  /**
   * Validate Cc: and Bcc: entries.
   *
   * @param {Object} event TBD.
   *
   * @param {String} header TBD.
   *
   * @returns {bool}
   */
  const carbonCopyBlur = function(event, header) {
    const self = $(this);
    event.stopImmediatePropagation();
    const request = {
      Request: 'validateEmailRecipients',
      Recipients: $(this).val(),
      Header: header,
      SingleItem: true,
    };
    request[header] = request.Recipients; // remove duplicate later
    applyComposerControls.call(
      this, event, request,
      function(lock) {
        sendButton.prop('disabled', lock);
        self.prop('disabled', lock);
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

  fieldset.find('#carbon-copy').off('blur').on('blur', function(event) {
    return carbonCopyBlur.call(this, event, 'CC');
  });
  fieldset.find('#blind-carbon-copy').off('blur').on('blur', function(event) {
    return carbonCopyBlur.call(this, event, 'BCC');
  });

  /*************************************************************************
   *
   * Project events attachments
   */

  fieldset.find('button.attachment.events').off('click').on('click', function(event) {
    const formData = form.find('fieldset.form-data');
    const projectId = formData.find('input[name="ProjectId"]').val();
    const projectName = formData.find('input[name="ProjectName"]').val();
    let events = eventAttachmentsSelector.val();
    if (!events) {
      events = [];
    }
    Projects.eventsPopup(
      {
        ProjectId: projectId,
        ProjectName: projectName,
        EventSelect: events,
      },
      false /* only move to top if already open */);
    return false;
  });

  // Update our selected events on request
  dialogHolder.off('cafevdb:events_changed');
  dialogHolder.on('cafevdb:events_changed', function(event, events) {
    const formData = form.find('fieldset.form-data');
    const projectId = formData.find('input[name="ProjectId"]').val();
    const projectName = formData.find('input[name="ProjectName"]').val();
    const requestData = {
      Request: 'update',
      FormElement: 'EventAttachments',
      SingleItem: true,
      ProjectId: projectId,
      ProjectName: projectName,
      AttachedEvents: events,
    };
    applyComposerControls.call(this, event, requestData);
    return false;
  });

  fieldset
    .find('input.delete-all-event-attachments')
    .off('click')
    .on('click', function(event) {

      // Ask for confirmation
      Dialogs.confirm(
        t(appName,
          'Do you really want to delete all event attachments?'),
        t(appName, 'Really Delete Attachments?'),
        function(confirmed) {
          if (!confirmed) {
            return false;
          }
          // simply void the attachment list
          eventAttachmentsSelector.val('');
          eventAttachmentsSelector.trigger('change');
          eventAttachmentsSelector.trigger('chosen:updated');
          fieldset.find('tr.event-attachments').hide();
          return false;
        },
        true);

      return false;
    });

  eventAttachmentsSelector.off('change').on('change', function(event) {
    const eventDialog = $('.cafevdb-project-events #events');
    let events = $(this).val();
    if (!events) {
      events = [];
      fieldset.find('tr.event-attachments').hide();
    }
    eventDialog.trigger('cafevdb:events_changed', [events]);

    return false;
  });

  /*************************************************************************
   *
   * File upload.
   */

  const updateFileAttachments = function() {
    const fileAttach = fieldset.find('input.file-attach').val();
    const selectedAttachments = fileAttachmentsSelector.val();

    const requestData = {
      Request: 'update',
      FormElement: 'FileAttachments',
      FileAttach: fileAttach, // JSON data of all files
      SingleItem: true,
      FormStatus: 'submitted',
    };
    if (selectedAttachments) {
      requestData.AttachedFiles = selectedAttachments;
    }
    applyComposerControls.call(this, $.Event('click'), requestData);
    return false;
  };

  // Arguably, these should only be active if the
  // composer tab is active. Mmmh.
  FileUpload.init({
    doneCallback(json) {
      attachmentFromJSON(json, { origin: 'local' });
    },
    stopCallback: updateFileAttachments,
    dropZone: null, // initially disabled, enabled on tab-switch
    inputSelector: '#attachment_upload_start',
    containerSelector: '#attachment_upload_wrapper',
  });

  fieldset.find('.attachment.upload').off('click');
  fieldset.find('.attachment.upload').on('click', function() {
    $('#attachment_upload_start').trigger('click');
  });

  fieldset.find('.attachment.cloud')
    .off('click')
    .on('click', function() {
      Dialogs.filePicker(
        t(appName, 'Select Attachment'),
        function(path) {
          cloudAttachment(path, updateFileAttachments);
          return false;
        },
        false, '', true);
    });

  fieldset
    .find('input.delete-all-file-attachments')
    .off('click')
    .on('click', function(event) {

      // Ask for confirmation
      Dialogs.confirm(
        t(appName,
          'Do you really want to delete all file attachments?'),
        t(appName, 'Really Delete Attachments?'),
        function(confirmed) {
          if (!confirmed) {
            return false;
          }
          // simply void the attachment list and issue an update.
          fieldset.find('input.file-attach').val('{}');
          updateFileAttachments();
          return false;
        },
        true);

      return false;
    });

  /*************************************************************************
   *
   * We try to be nice with Cc: and Bcc: and even provide an
   * address-book connector
   */
  const addressBookButton = fieldset.find('input.address-book-emails');
  addressBookButton
    .off('click')
    .on('click', function(event) {
      event.preventDefault();

      const self = $(this);
      const input = fieldset.find(self.data('for'));

      if (input.val().trim() !== '') {
        // We trigger validation before we pop-up, but no need to do
        // so on empty input.
        input.trigger('blur');
      }

      const post = { FreeFormRecipients: input.val() };
      $.post(
        OC.filePath(appName, 'ajax/email', 'addressbook.php'),
        post,
        function(data) {
          if (!Ajax.validateResponse(data, [
            'contents',
          ])) {
            return false;
          }
          CAFEVDB.chosenPopup(data.data.contents, {
            title: t(appName, 'Address Book'),
            saveText: t(appName, 'Accept'),
            buttons: [
              {
                text: t(appName, 'Save Contacts'),
                class: 'save-contacts',
                title: t(
                  appName, 'Save the selected supplementary emails to the address-book for later reusal.'),
                click() {
                  const dialogHolder = $(this);
                  const selectElement = dialogHolder.find('select');
                  // We are interested in all selected options
                  // inside the first options group
                  const selectedFreeForm = [];
                  selectElement.find('optgroup.free-form option:selected').each(function(idx) {
                    const self = $(this);
                    selectedFreeForm[idx] = {
                      value: self.val(),
                      html: self.html(),
                      text: self.text(),
                    };
                  });
                  const innerPost = { AddressBookCandidates: selectedFreeForm };
                  $.post(
                    OC.filePath(appName, 'ajax/email', 'savecontacts.php'),
                    innerPost,
                    function(data) {
                      if (!Ajax.validateResponse(data, [])) {
                        return false;
                      }
                      $.post(
                        OC.filePath(appName, 'ajax/email', 'addressbook.php'),
                        post,
                        function(data) {
                          if (!Ajax.validateResponse(data, ['contents'])) {
                            return false;
                          }
                          const newOptions = $(data.data.contents).html();
                          selectElement.html(newOptions);
                          selectElement.trigger('chosen:updated');
                          if (selectElement.find('optgroup.free-form').length === 0) {
                            dialogHolder.dialog('widget')
                              .find('button.save-contacts').prop('disabled', true);
                          }
                          return false;
                        });
                      return false;
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
              if (selectElement.find('optgroup.free-form').length === 0) {
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

  panelHolder.off('resize');
  panelHolder.on('resize', function() {
    emailTabResize(dialogWidget, panelHolder);
  });
};

/**
 * Open the mass-email form in a popup window.
 *
 * @param {Object|String} post Necessary post data, either serialized or as
 * object. In principle post can be empty. For project emails the
 * following two fields are necessary:
 *
 * - ProjectId: the id
 * - ProjectName: the name of the project (obsolete: Project)
 *
 * Optional pre-selected ids for email recipients:
 *
 * - PME_sys_mrecs: array of ids of pre-selected musicians
 *
 * - EventSelect: array of ids of events to attach.
 *
 * @param {bool} modal TBD.
 *
 * @param {bool} single TBD.
 *
 * @param {Function} afterInit TBD.
 */
function emailFormPopup(post, modal, single, afterInit) {
  const self = this;

  if (this.active === true) {
    return;
  }

  this.active = true;

  if (modal === undefined) {
    modal = true;
  }
  if (single === undefined) {
    single = false;
  }
  if (typeof afterInit !== 'function') {
    afterInit = function() {};
  }
  $.post(generateUrl('communication/email/outgoing/form'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown, function() {
        self.active = false;
        afterInit(false);
      });
    })
    .done(function(data) {
      const containerId = 'emailformdialog';

      if (!Ajax.validateResponse(
        data, ['contents'], function() {
          self.active = false;
          afterInit(false);
        })) {
        return false;
      }

      afterInit(true);

      const dialogHolder = $('<div id="' + containerId + '"></div>');
      dialogHolder.html(data.contents);
      $('body').append(dialogHolder);

      const emailForm = $('form#cafevdb-email-form');
      const recipientsPanel = dialogHolder.find('div#emailformrecipients');
      const composerPanel = dialogHolder.find('div#emailformcomposer');

      let dlgTitle = '';
      if (data.projectId >= 0) {
        dlgTitle = t(appName, 'Em@il Form for {ProjectName}', { ProjectName: data.projectName });
      } else {
        dlgTitle = t(appName, 'Em@il Form');
      }

      if (modal) {
        CAFEVDB.modalizer(true);
      }

      let recipientsAlertText;
      if (data.projectId >= 0) {
        recipientsAlertText = t(appName, 'Email will be sent with an open recipients list!');
      } else {
        recipientsAlertText = t(appName, 'Email will be sent with a hidden recipients list!');
      }
      Dialogs.alert(recipientsAlertText, t(appName, 'Notice'), undefined, true);

      dialogHolder.cafevDialog({
        title: dlgTitle,
        position: {
          my: 'middle top',
          at: 'middle bottom+50px',
          of: '#header',
        },
        width: 'auto',
        height: 'auto',
        modal: false, // modal,
        closeOnEscape: false,
        dialogClass: 'emailform custom-close',
        resizable: false,
        open() {
          $.fn.cafevTooltip.remove();
          DialogUtils.toBackButton(dialogHolder);
          DialogUtils.customCloseButton(dialogHolder, function(event, container) {
            console.info('Custom Close Button');
            event.stopImmediatePropagation();
            dialogHolder.find('input.submit.cancel[type="submit"]').trigger('click');
            // dialogHolder.dialog('close');
            return false;
          });
          const dialogWidget = dialogHolder.dialog('widget');
          // if (false && single) {
          //   dialogHolder.find('li#emailformrecipients-tab').prop('disabled', true);
          //   dialogHolder.find('li#emailformrecipients-tab a').prop('disabled', true);
          // }

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
                  const recipientsFieldSet = emailForm.find('fieldset.email-recipients.page');
                  emailFormRecipientsSelectControls(dialogHolder, recipientsFieldSet);
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

              ui.newPanel.css('max-height', '');
              ui.newPanel.css('height', 'auto');

              if (oldTabId !== 'emailformrecipients-tab'
                  || newTabId !== 'emailformcomposer-tab') {
                return true;
              }

              // we better serialize the entire form here
              let post = emailForm.serialize();
              // place our update request
              post += '&emailComposer[Request]=update&emailComposer[FormElement]=TO';
              $.post(
                OC.filePath(appName, 'ajax/email', 'composer.php'),
                post,
                function(data) {
                  if (!Ajax.validateResponse(data, [
                    'projectId', 'projectName', 'request', 'requestData',
                  ])) {
                    return false;
                  }
                  // could check whether formElement is indeed 'TO' ...
                  const toSpan = emailForm.find('span.email-recipients');
                  let rcpts = data.requestData.elementData;

                  if (rcpts.length === 0) {
                    rcpts = toSpan.data('placeholder');
                  }
                  const title = toSpan.data('titleIntro') + '<br>' + rcpts;

                  toSpan.html(rcpts);
                  toSpan.attr('title', title);
                  toSpan.cafevTooltip();
                  return false;
                });
              return true;
            },
          });

          const recipientsFieldSet = emailForm.find('fieldset.email-recipients.page');
          const composerFieldSet = emailForm.find('fieldset.email-composition.page');

          // Fine, now add handlers and AJAX callbacks. We can
          // probably move some of the code above to the
          // respective tab-handler.
          emailFormRecipientsHandlers(
            recipientsFieldSet,
            emailForm,
            dialogHolder,
            recipientsPanel);
          emailFormCompositionHandlers(
            composerFieldSet,
            emailForm,
            dialogHolder,
            composerPanel);
        },
        close() {
          $.fn.cafevTooltip.remove();
          WysiwygEditor.removeEditor(dialogHolder.find('textarea.wysiwyg-editor'));
          dialogHolder.dialog('close');
          dialogHolder.dialog('destroy').remove();

          // Also close all other open dialogs.
          CAFEVDB.modalizer(false);
          self.active = false;
        },
      });
      return false;
    });
};

const documentReady = function() {

  $('button.eventattachments.edit').click(function(event) {
    event.preventDefault();

    // Edit existing event
    const post = [];
    const type = {};
    type.name = 'id';
    type.value = $(this).val();
    post.push(type);
    $('#dialog_holder').load(
      OC.filePath('calendar', 'ajax/event', 'edit.form.php'),
      post, function() {
        $('input[name="delete"]').prop('disabled', true);
        Legacy.Calendar.UI.startEventDialog();
      });

    return false;
  });

};

export {
  documentReady,
  emailFormPopup,
  attachmentFromJSON,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

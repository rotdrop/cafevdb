/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016 Claus-Justus Heine <himself@claus-justus-heine.de>
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
CAFEVDB.Email = CAFEVDB.Email || {};

(function(window, $, Email, undefined) {
  'use strict';
  Email.enabled = true;
  Email.active = false;

  Email.attachmentFromJSON = function (response, info) {
    var fileAttachHolder = $('form.cafevdb-email-form fieldset.attachments input.file-attach');
    if (fileAttachHolder == '') {
      OC.dialogs.alert(t('cafevdb', 'Not called from main email-form.'),
                       t('cafevdb', 'Error'));
      return;
    }

    var file = response.data;
    file.status = 'new';
    if (typeof info == 'object') {
      file = $.extend(file, info);
    }
    var fileAttach = fileAttachHolder.val();
    if (fileAttach == '') {
      fileAttach = [ file ];
    } else {
      fileAttach = $.parseJSON(fileAttach);
      fileAttach.push(file);
    }
    fileAttachHolder.val(JSON.stringify(fileAttach));
  };

  Email.owncloudAttachment = function(path, callback) {
    $.getJSON(OC.filePath('cafevdb', 'ajax', 'email/owncloudattachment.php'),
              {'path':path},
              function(response) {
                if (response != undefined && response.status == 'success') {
                  CAFEVDB.Email.attachmentFromJSON(response, { 'origin': 'owncloud'});
                  if (typeof callback == 'function') {
                    callback();
                  }
                } else {
	          OC.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
                }
              });
  };

  Email.tabResize = function (dialogWidget, panelHolder) {
    //panelHolder.css('width', 'auto');
    //panelHolder.css('height', 'auto');
    panelHolder.css('max-height', 'none'); // reset in order to get auto-configuration
    var titleOffset = (dialogWidget.find('.ui-dialog-titlebar').outerHeight(true)
                      +
                       dialogWidget.find('.ui-tabs-nav').outerHeight(true));
    var panelHeight = panelHolder.outerHeight(true);
    var panelOffset = panelHeight - panelHolder.height();
    var dialogHeight = dialogWidget.height();
    //alert('outer: '+panelHeight+' dialog '+dialogHeight);
    if (panelHeight > dialogHeight - titleOffset) {
      panelHolder.css('max-height', (dialogHeight-titleOffset-panelOffset)+'px');
    }
    if (panelHolder.get(0).scrollHeight > panelHolder.outerHeight(true)) {
      panelHolder.css('padding-right', '2.4em');
    } else {
      panelHolder.css('padding-right', '');
    }
  };

  /**Add some extra JS stuff for the select boxes. This has to be
   * called when the tab is actually visible because the add-on
   * libraries use the size of the original controls as base for their
   * layout.
   */
  Email.emailFormRecipientsSelectControls = function(dialogHolder, fieldset) {

    if (dialogHolder.tabs('option', 'active') != 0 ||
        fieldset.find('#member_status_filter_chosen').length > 0) {
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
        moveAllLabel: t('cafevdb', 'Move all'),
        moveSelectedLabel: t('cafevdb', 'Move selected'),
        removeSelectedLabel: t('cafevdb', 'Remove selected'),
        removeAllLabel: t('cafevdb', 'Remove all'),
        nonSelectedListLabel: t('cafevdb', 'Remaining Recipients'),
        selectedListLabel   : t('cafevdb', 'Selected Recipients'),
        infoText            : '&nbsp;', // t('cafevdb', 'Showing all {0}'),
        infoTextFiltered    : '<span class="label label-warning">'
                            + t('cafevdb', 'Filtered')
                            + '</span> {0} '
                            + t('cafevdb', 'from')
                            +' {1}',
        infoTextEmpty       : t('cafevdb', 'Empty list'),
        filterPlaceHolder   : t('cafevdb', 'Filter'),
        filterTextClear     : t('cafevdb', 'show all')
      }
    );
    var dualSelect = fieldset.find('div.bootstrap-duallistbox-container select');
    dualSelect.attr(
      'title',
      t('cafevdb', 'Click on the names to move the respective person to the other box'));
    dualSelect.addClass('tooltip-top');

    CAFEVDB.toolTipsInit(dialogHolder.find('div#emailformrecipients'));
  };

  /**Add handlers to the control elements, and call the AJAX sciplets
   * for validation to update the recipients selection tab accordingly.
   *
   * @param fieldset The field-set enclosing the recipients selection part
   *
   * @param dialogHolder The div holding the jQuery dialog for everything
   *
   * @param panelHolder The div enclosing the fieldset
   *
   */
  Email.emailFormRecipientsHandlers = function(fieldset, form, dialogHolder, panelHolder) {
    var Email = this;

    Email.emailFormRecipientsSelectControls(dialogHolder, fieldset);

    var recipientsSelect   = fieldset.find('select#recipients-select');
    var missingAddresses   = fieldset.find('.missing-email-addresses.names');
    var missingLabel       = fieldset.find('.missing-email-addresses.label');
    var noMissingLabel     = fieldset.find('.missing-email-addresses.label.empty');
    var filterHistoryInput = fieldset.find('#recipients-filter-history');
    var debugOutput        = form.find('#emailformdebug');

    // Apply the instruments filter
    var applyRecipientsFilter = function(event, historySnapshot) {
      event.preventDefault();

      historySnapshot = typeof historySnapshot != 'undefined';

      var post = fieldset.serialize();
      if (historySnapshot) {
        post += '&'+$.param({ emailRecipients: { HistorySnapshot: 'snapshot' }});
      } else {
        post += '&'+form.find('fieldset.form-data').serialize();
        if ($(this).is(':button')) {
          var tmp = {};
          tmp[$(this).attr('name')] = $(this).val();
          post += '&'+$.param(tmp);
        }
      }
      $.post(OC.filePath('cafevdb', 'ajax/email', 'recipients.php'),
             post,
             function(data) {
               var requiredResponse = historySnapshot
                                    ? [ 'filterHistory' ]
                                    : [ 'recipientsOptions', 'missingEmailAddresses', 'filterHistory' ];
               if (!CAFEVDB.ajaxErrorHandler(data, requiredResponse)) {
                 return false;
               }
               if (historySnapshot) {
                 // Just update the history, but nothing else
                 filterHistoryInput.val(data.data.filterHistory);
               } else if (typeof data.data.contents != 'undefined' && data.data.contents.length > 0) {
                 // replace the entire tab.
                 $.fn.cafevTooltip.remove();
                 panelHolder.html(data.data.contents);
                 fieldset = panelHolder.find('fieldset.email-recipients.page');
                 Email.emailFormRecipientsHandlers(fieldset,
                                                   form,
                                                   dialogHolder,
                                                   panelHolder);
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

               var filterHistory = data.data.filterHistory;
               if (filterHistory.historyPosition >= 0 &&
                   filterHistory.historyPosition < filterHistory.historySize-1) {
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
                 var debugText = '';
                 if (typeof data.data.debug != 'undefined') {
                   debugText = data.data.debug;
                 }
                 debugOutput.html('<pre>'+$('<div></div>').text(debugText).html()+'</pre>'+
                                  '<pre>'+$('<div></div>').text(data.data.recipientsOptions).html()+'</pre>'+
                                  data.data.missingEmailAddresses+'</br>'+
                                  $('<div></div>').text(CAFEVDB.urldecode(post)).html())
               }
               return false;
             });
      return false;
    };

    // Attach above function to almost every sensible control :)

    // Controls :..
    var controlsContainer = fieldset.find('.filter-controls.container');

    // Instruments filter
    if (true) {
      var instrumentsFilter = fieldset.find('.instruments-filter.container');
      instrumentsFilter.on('dblclick', function(event) {
        applyRecipientsFilter.call(this, event);
      });
    } else {
      var instrumentsFilter = fieldset.find('.instruments-filter.container select');
      instrumentsFilter.off('change');
      instrumentsFilter.on('change', function(event) {
        applyRecipientsFilter.call(this, event);
      });
    }

    // Member status filter
    var memberStatusFilter = fieldset.find('select.member-status-filter');
    memberStatusFilter.off('change');
    memberStatusFilter.on('change', function(event) {
      applyRecipientsFilter.call(this, event);
    });

    // Basic set
    var basicRecipientsSet = fieldset.find('.basic-recipients-set.container input[type="checkbox"]');
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

      var formData = form.find('fieldset.form-data');
      var projectId = formData.find('input[name="ProjectId"]').val();
      var projectName = formData.find('input[name="ProjectName"]').val();

      CAFEVDB.Instrumentation.personalRecordDialog(
        $(this).data('id'),
        {
          ProjectId: projectId,
          ProjectName: projectName,
          InitialValue: 'Change',
          AmbientContainerSelector: '#emailformdialog'
        });

      return false;
    });

    panelHolder.
      off('resize').
      on('resize', function() {
      Email.tabResize(dialogHolder.dialog('widget'), panelHolder);
    });

    return false;
  };

  /**Add handlers to the control elements, and call the AJAX sciplets
   * for validation to update the message composition tab accordingly.
   *
   * @param fieldset The field-set enclosing the recipients selection part
   *
   * @param dialogHolder The div holding the jQuery dialog for everything
   *
   * @param panelHolder The div enclosing the fieldset
   *
   */
  Email.emailFormCompositionHandlers = function(fieldset, form, dialogHolder, panelHolder) {
    var Email = this;

    {
      CAFEVDB.addEditor(dialogHolder.find('textarea.wysiwygeditor'), undefined, '20em');

      $('#cafevdb-stored-messages-selector').chosen({ disable_search_threshold: 10 });

      var composerPanel = $('#emailformcomposer');
      var fileAttachmentsSelect = composerPanel.find('#file-attachments-selector');
      fileAttachmentsSelect.chosen();
      fileAttachmentsSelect.on('chosen:showing_dropdown', function(event) {
        composerPanel.stop().animate({
          scrollTop: composerPanel.prop('scrollHeight')
        }, 2000);
        return true;
      });
      var eventAttachmentsSelect = composerPanel.find('#event-attachments-selector');
      eventAttachmentsSelect.chosen();
      eventAttachmentsSelect.on('chosen:showing_dropdown', function(event) {
        composerPanel.stop().animate({
          scrollTop: composerPanel.prop('scrollHeight')
        }, 2000);
        return true;
      });

      CAFEVDB.toolTipsInit(dialogHolder.find('div#emailformcomposer'));
    }

    var debugOutput = form.find('#emailformdebug');
    var storedEmailsSelector = fieldset.find('select.stored-messages-selector');
    var currentTemplate = fieldset.find('#emailCurrentTemplate');
    var saveAsTemplate = fieldset.find('#check-save-as-template');
    var messageText = fieldset.find('textarea');
    var eventAttachmentsSelector = fieldset.find('select.event-attachments');
    var fileAttachmentsSelector = fieldset.find('select.file-attachments');
    var sendButton = fieldset.find('input.submit.send');
    var dialogWidget = dialogHolder.dialog('widget');

    // Event dispatcher, so to say
    var applyComposerControls = function(event, request, validateLockCB) {
      event.preventDefault();

      if (typeof validateLockCB == 'undefined') {
        validateLockCB = function(lock) {};
      }

      var validateLock = function() {
        validateLockCB(true)
      };

      var validateUnlock = function() {
        validateLockCB(false)
      };

      // until end of validation
      validateLock(true);

      var post = '';
      if (typeof request != 'undefined' && request.SingleItem) {
        // Only serialize the request, no need to post all data around.
        post = $.param({ emailComposer: request });
      } else {
        if (typeof request != 'undefined' && request.SubmitAll) {
          // Everything is greedily submitted ...
          post = form.serialize();
        } else {
          // Serialize almost everything and submit it
          post = fieldset.serialize();
          post += '&'+form.find('fieldset.form-data').serialize();
        }
        if ($(this).is(':button') || $(this).is(':submit')) {
          var tmp = {};
          tmp[$(this).attr('name')] = $(this).val();
          post += '&'+$.param(tmp);
        }
        if (typeof request != 'undefined') {
          post += '&'+$.param({ emailComposer: request });
        }
      }
      //$.post(OC.filePath('cafevdb', 'ajax/email', 'composer.php'),
      $.ajax({ url: OC.filePath('cafevdb', 'ajax/email', 'composer.php'),
               type: 'POST',
               data: post,
               dataType: 'json',
               async: true,
               success: function(data) {
               var postponeEnable = false;
               $.fn.cafevTooltip.remove();
               if (!CAFEVDB.ajaxErrorHandler(
                 data,
                 [ 'projectId', 'projectName', 'request', 'requestData' ],
                 validateUnlock)) {
                 if (typeof data != 'undefined' && typeof data.data != 'undefined') {
                   var debugText = '';
                   if (typeof data.data.caption != 'undefined') {
                     debugText += '<div class="error caption">'+data.data.caption+'</div>';
                   }
                   if (typeof data.data.message != 'undefined') {
                     debugText += data.data.message;
                   }
                   if (typeof data.data.debug != 'undefined') {
                     debugText += '<pre>'+data.data.debug+'</pre>';
                   }
                   debugOutput.html(debugText);
                 }
                 return false;
               }
               var request = data.data.request;
               var requestData = data.data.requestData;
               switch (request) {
               case 'send':
                 storedEmailsSelector.html(requestData.storedEmailOptions);
                 CAFEVDB.selectMenuReset(storedEmailsSelector);
                 if (typeof data.data.message != 'undefined' &&
                     typeof data.data.caption != 'undefined') {
                   OC.dialogs.alert(data.data.message, data.data.caption,
                                    undefined, true, true);
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
                   CAFEVDB.removeEditor(panelHolder.find('textarea.wysiwygeditor'));
                   panelHolder.html(requestData.elementData);
                   fieldset = panelHolder.find('fieldset.email-composition.page');
                   Email.emailFormCompositionHandlers(fieldset,
                                                      form,
                                                      dialogHolder,
                                                      panelHolder);
                   break;
                 case 'TO':
                   var toSpan = fieldset.find('span.email-recipients');
                   var rcpts = requestData.elementData;
                   if (rcpts.length == 0) {
                     rcpts = toSpan.data('placeholder');
                   }
                   var title = toSpan.data('titleIntro')+'<br>'+rcpts;

                   toSpan.html(rcpts);
                   toSpan.attr('title', title);
                   toSpan.cafevTooltip();
                   break;
                 case 'FileAttachments': {
                   var options = requestData.elementData.options;
                   //alert('options: '+JSON.stringify(options));
                   var fileAttach = requestData.elementData.fileAttach;
                   fieldset.find('input.file-attach').val(JSON.stringify(fileAttach));
                   fileAttachmentsSelector.html(options);
                   if (options.length > 0) {
                     fieldset.find('tr.file-attachments').show();
                   } else {
                     fieldset.find('tr.file-attachments').hide();
                   }
                   fileAttachmentsSelector.trigger("chosen:updated");

                   panelHolder.trigger('resize');
                   break;
                 }
                 case 'EventAttachments': {
                   var options = requestData.elementData.options;
                   var eventAttach = requestData.elementData.eventAttach;
                   //alert('options: '+JSON.stringify(options));
                   //alert('options: '+JSON.stringify(requestData.elementData.eventAttach));
                   eventAttachmentsSelector.html(options);

                   if (/*options.length*/ eventAttach.length > 0) {
                     fieldset.find('tr.event-attachments').show();
                   } else {
                     fieldset.find('tr.event-attachments').hide();
                   }
                   eventAttachmentsSelector.trigger("chosen:updated");
                   panelHolder.trigger('resize');

                   break;
                 }
                 default:
                   postponeEnable = true;
                   OC.dialogs.alert(t('cafevdb',
                                      'Unknown form element: {FormElement}',
                                      { FormElement: requestData.formElement }),
                                    t('cafevdb', 'Error'),
                                    validateUnlock,
                                    true, true);
                   break;
                 }
                 break;
               case 'validateEmailRecipients':
                 // already reported by the general error-handling functions
                 break;
               case 'setTemplate': {
                 var dataItem = fieldset.find('input[name="emailComposer[MessageDraftId]"]');
                 dataItem.val(-1);
                 currentTemplate.val(requestData.templateName);
                 CAFEVDB.updateEditor(messageText, requestData.message);
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
                 CAFEVDB.updateEditor(messageText, requestData.message);
                 storedEmailsSelector.html(requestData.storedEmailOptions);
                 CAFEVDB.selectMenuReset(storedEmailsSelector);
                 break;
               case 'saveDraft': {
                 // perhaps rather use data stuff in the future ...
                 storedEmailsSelector.html(requestData.storedEmailOptions);
                 CAFEVDB.selectMenuReset(storedEmailsSelector);
                 var dataItem = fieldset.find('input[name="emailComposer[MessageDraftId]"]');
                 dataItem.val(requestData.messageDraftId);
                 break;
               }
               case 'loadDraft': {
                 $.fn.cafevTooltip.remove();
                 { // replace the entire composer tab
                   CAFEVDB.removeEditor(panelHolder.find('textarea.wysiwygeditor'));
                   panelHolder.html(requestData.composerForm);
                   fieldset = panelHolder.find('fieldset.email-composition.page');
                   Email.emailFormCompositionHandlers(fieldset,
                                                      form,
                                                      dialogHolder,
                                                      panelHolder);
                 }
                 { // replace the recipients tab as well ...
                   var rcptPanelHolder = dialogHolder.find('div#emailformrecipients');
                   rcptPanelHolder.html(requestData.recipientsForm);
                   var rcptFieldSet = form.find('fieldset.email-recipients.page');
                   Email.emailFormRecipientsHandlers(rcptFieldSet,
                                                     form,
                                                     dialogHolder,
                                                     rcptPanelHolder);
                 }

                 { // adjust the title of the dialog
                   var dlgTitle = '';
                   if (requestData.ProjectId >= 0) {
                     dlgTitle = t('cafevdb', 'Em@il Form for {ProjectName}',
                                  { ProjectName: requestData.ProjectName });
                   } else {
                     dlgTitle = t('cafevdb', 'Em@il Form');
                   }
                   dialogHolder.dialog('option', 'title', dlgTitle);
                 }

                 { // update the "global" project name and id
                   var formData = form.find('fieldset.form-data');
                   formData.find('input[name="ProjectId"]').val(requestData.ProjectId);
                   formData.find('input[name="ProjectName"]').val(requestData.ProjectName)
                   formData.find('input[name="DebitNoteId"]').val(requestData.DebitNoteId)
                 }

                 // Make the debug output less verbose
                 delete requestData.composerForm;
                 delete requestData.recipientsForm;

                 // deselect menu item
                 CAFEVDB.selectMenuReset(storedEmailsSelector);

                 break;
               }
               case 'deleteDraft': {
                 var dataItem = fieldset.find('input[name="emailComposer[MessageDraftId]"]');
                 dataItem.val(-1);
                 storedEmailsSelector.html(requestData.storedEmailOptions);
                 CAFEVDB.selectMenuReset(storedEmailsSelector);
                 break;
               }
               default:
                 postponeEnable = true;
                 data.data.message =
                   t('cafevdb', 'Unknown request: {Request}', { Request: request });
                 data.data.caption = t('cafevdb', 'Error')
                 OC.dialogs.alert(data.data.message, data.data.caption,
                                  validateUnlock,
                                  true, true);
                 break;
               };

               var debugText = '';
               if (typeof data.data.caption != 'undefined') {
                 debugText += '<div class="error caption">'+data.data.caption+'</div>';
               }
               if (typeof data.data.message != 'undefined') {
                 debugText += data.data.message;
               }
               if (typeof data.data.debug != 'undefined') {
                 debugText += '<pre>'+data.data.debug+'</pre>';
               }
               if (debugText != '') {
                 var addOn;
                 addOn = CAFEVDB.print_r(CAFEVDB.queryData(post, true), true)
                 addOn = $('<div></div>').text(addOn).html();
                 debugText += '<pre>post = '+addOn+'</pre>';
                 addOn = CAFEVDB.print_r(requestData, true);
                 addOn = $('<div></div>').text(addOn).html();
                 debugText += '<pre>requestData = '+addOn+'</pre>';
                 debugOutput.html(debugText);
               }

               if (!postponeEnable) {
                 validateUnlock();
               }
               return false;
               }
             }
            );
      return false;
    };

    /*************************************************************************
     *
     * Finally send the entire mess to the recipients
     */
    sendButton.off('click').on('click', function(event) {
      event.stopImmediatePropagation();

      // try to provide status feed-back for large transfers or
      // sending to many recipients. To this end we poll a special
      // data-base table. If not finished after 5 seconds, we pop-up a
      // dialog with status information.

      var initialTimeout = 3000;
      var pollingTimeout = 800;
      var progressTimer;
      var initialTimer;
      var progressWrapper = dialogHolder.find('div#sendingprogresswrapper');
      var progressOpen = false;

      var pollProgress = function() {
        $.post(OC.filePath('cafevdb', 'ajax/email', 'progress.php'),
               {ProgressId: 0 },
               function(data) {
                 var stop = false;
                 if (progressOpen &&
                     typeof data != 'undefined' && typeof data.progress != 'undefined') {
                   var progress = data.progress;
                   var value = progress.current;
                   var max = progress.target;
                   var rel = value / max * 100.0;
                   var tagData = $.parseJSON(progress.tag);
                   var progressTitle;
                   if (tagData.total > 1) {
                     progressTitle =
                       t('cafevdb', 'Sending message {active} out of {total}', tagData);
                   } else {
                     progressTitle = t('cafevdb', 'Message delivery in progress');
                   }
                   progressWrapper.find('div.messagecount').html(progressTitle);
                   if (tagData.proto == 'smtp') {
                     progressWrapper.find('div.imap span.progressbar').
                       progressbar('option', 'value', 0);
                   }
                   progressWrapper.find('div.'+tagData.proto+' span.progressbar').
                     progressbar('option', 'value', rel);
                   if (tagData.proto == 'imap' && rel == 100 && tagData.active == tagData.total) {
                     stop = true;
                   }
                 }
                 if (!stop) {
                   progressTimer = setTimeout(pollProgress, pollingTimeout);
                 }
               });
      };

      initialTimer = setTimeout(function() {
                       //alert('count: '+progressWrapper.length);
                       //alert('count: '+progressWrapper.length);
                       progressWrapper.show();
                       progressWrapper.find('span.progressbar').progressbar({value:0, max:100});
                       progressWrapper.cafevDialog({
                         title: t('cafevdb', 'Message Delivery Status'),
                         width: 'auto',
                         height: 'auto',
                         modal: true,
                         closeOnEscape: false,
                         resizable: false,
                         dialogClass: 'emailform delivery progress no-close',
                         open: function() {
                           progressOpen = true;
                         },
                         close: function() {
                           progressOpen = false;
                           //progressWrapper.find('span.progressbar').progressbar('destroy');
                           progressWrapper.dialog('destroy');
                           progressWrapper.hide();
                         }
                       });
                       progressTimer = setTimeout(pollProgress, pollingTimeout);
                     }, initialTimeout);

      applyComposerControls.call(this, event,
                                 {
                                   'Request': 'send',
                                   'Send': 'ThePointOfNoReturn',
                                   'SubmitAll': true
                                 },
                                 function(lock) {
                                   if (lock) {
                                     $(window).on('beforeunload', function(event) {
                                       return t('cafevdb', 'Email sending is in progress. Leaving the page now will cancel the email submission.');
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
    fieldset.find('input.submit.message-export').
      off('click').
      on('click', function(event) {

      var formPost = form.serialize();
      var post = {};
      post[$(this).attr('name')] = "whatever";
      formPost += '&'+$.param(post);

      CAFEVDB.Page.busyIcon(true);

      $.post(OC.filePath('cafevdb', 'ajax/email', 'preview.php'),
             formPost,
             function(data) {
               if (!CAFEVDB.ajaxErrorHandler(
                 data, [ 'contents' ], function() {
                                         CAFEVDB.Page.busyIcon(false);
                                       })) {
                 return false;
               }

               debugOutput.html(data.data.contents);

               CAFEVDB.Page.busyIcon(false);

               dialogHolder.tabs('option', 'active', 2);

               $.fn.cafevTooltip.remove();

               return true;
             });
      return false;
    });

    // fieldset.find('input.submit.message-blah-export').
    //   off('click').
    //   on('click', function(event) {

    //   CAFEVDB.Page.busyIcon(true);

    //   var action = OC.filePath('cafevdb', 'ajax/email', 'exporter.php');
    //   var formPost = form.serialize();
    //   var post = {};
    //   post[$(this).attr('name')] = "whatever";
    //   post['DownloadCookie'] = CAFEVDB.makeId();
    //   formPost += '&'+$.param(post);
    //   $.fileDownload(action, {
    //     httpMethod: 'POST',
    //     data: formPost,
    //     cookieName: 'email_preview_download',
    //     cookieValue: post['DownloadCookie'],
    //     cookiePath: oc_webroot,
    //     successCallback: function() {
    //       CAFEVDB.Page.busyIcon(false);
    //     },
    //     failCallback: function(responseHtml, url, error) {
    //       OC.dialogs.alert(t('cafevdb', 'Unable to export message(s):')+
    //                        ' '+
    //                        responseHtml,
    //                        t('cafevdb', 'Error'),
    //                        function() { CAFEVDB.Page.busyIcon(false); },
    //                        true, true);
    //     }
    //   });
    //   return false;
    // });


    /*************************************************************************
     *
     * Close the dialog
     */

    fieldset.find('input.submit.cancel').off('click').
      on('click', function(event) {
      applyComposerControls.call(this, event, {
        'Request': 'cancel',
        'Cancel': 'DoesNotMatter',
        'FormStatus': 'submitted',
        'SingleItem': true
      });
      // Close the dialog in any case.
      dialogHolder.dialog('close');
      return false;
    });

    /*************************************************************************
     *
     * Template handling (save, delete, load)
     */

    saveAsTemplate.
      off('change').
      on('change', function(event) {
      event.preventDefault();
      currentTemplate.prop('disabled', !saveAsTemplate.is(':checked'));
      return false;
    });

    fieldset.find('input.submit.save-message').
      off('click').
      on('click', function(event) {
      var self = this;

      event.preventDefault();

      if (saveAsTemplate.is(':checked')) {
        // We do a quick client-side validation and ask the user for ok
        // when a template with the same name is already present.
        var current = currentTemplate.val();
        if (storedEmailsSelector.find('option[value="'+current+'"]').length > 0) {
          OC.dialogs.confirm(
            t('cafevdb', 'A template with the name `{TemplateName}\' already exists, '+
              'do you want to overwrite it?', {TemplateName: current}),
            t('cafevdb', 'Overwrite existing template?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, event, { 'Request': 'saveTemplate' });
              }
            },
            true);
        } else {
          applyComposerControls.call(self, event, { 'Request': 'saveTemplate' });
        }
      } else {
        applyComposerControls.call(self, event, { 'Request': 'saveDraft',
                                                  'SubmitAll': true
                                                });
      }
      return false;
    });

    fieldset.find('input.submit.delete-message').
      off('click').
      on('click', function(event) {
      var self = this;
      event.preventDefault();

      if (saveAsTemplate.is(':checked')) {
        // We do a quick client-side validation and ask the user for ok.
        var current = currentTemplate.val();
        if (storedEmailsSelector.find('option[value="'+current+'"]').length > 0) {
          OC.dialogs.confirm(
            t('cafevdb',
              'Do you really want to delete the template with the name `{TemplateName}\'?',
              {TemplateName: current}),
            t('cafevdb', 'Really Delete Template?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, event, { 'Request': 'deleteTemplate' });
              }
            },
            true);
        } else {
          OC.dialogs.alert(t('cafevdb',
                             'Cannot delete non-existing template `{TemplateName}\'',
                             {TemplateName: current}),
                           t('cafevdb', 'Unknown Template'));
        }
      } else {
        var draft = fieldset.find('input[name="emailComposer[MessageDraftId]"]').val();

        if (draft >= 0) {
          OC.dialogs.confirm(
            t('cafevdb',
              'Do you really want to delete the backup copy of the current message (id = {Id})?',
              {Id: draft}),
            t('cafevdb', 'Really Delete Draft?'),
            function(confirmed) {
              if (confirmed) {
                applyComposerControls.call(self, event, {
                  'Request': 'deleteDraft',
	          'messageDraftId': draft
                });
              }
            },
            true);
        }
      }
      return false;
    });

    storedEmailsSelector.off('change');
    storedEmailsSelector.on('change', function(event) {
      var self = this;

      event.preventDefault();

      var choice = storedEmailsSelector.val();
      if (choice.match(/^__draft-[0-9]+$/)) {
        applyComposerControls.call(this, event, { 'Request': 'loadDraft' },
                                   function(lock) {
                                     if (lock) {
                                       dialogWidget.addClass('pme-table-dialog-blocked');
                                     } else {
                                       dialogWidget.removeClass('pme-table-dialog-blocked');
                                       dialogHolder.tabs('option', 'disabled', []);
                                     }
                                   });
      } else if (choice.match(/__draft--1/)) {
        OC.dialogs.alert(t('cafevdb',
                           'There are currently no stored draft messages available.'),
                           t('cafevdb', 'No Drafts Available'),
                         function() {
                           CAFEVDB.selectMenuReset(storedEmailsSelector);
                         });
      } else {
        applyComposerControls.call(this, event, { 'Request': 'setTemplate' });
      }

      return false;
    });

    /*************************************************************************
     *
     * Subject and sender name. We simply trim the spaces away. Could also do this in JS.
     */
    fieldset.off('blur', 'input.email-subject, input.sender-name').
      on('blur', 'input.email-subject, input.sender-name',
                function(event) {
                  event.stopImmediatePropagation();
                  var self = $(this);
                  self.val(self.val().trim());
                  return false;
                });

    /*************************************************************************
     *
     * Validate Cc: and Bcc: entries.
     */
    var carbonCopyBlur = function(event, header) {
      var self = $(this);
      event.stopImmediatePropagation();
      var request =  { 'Request': 'validateEmailRecipients',
                       'Recipients': $(this).val(),
                       'Header': header,
                       'SingleItem': true };
      request[header] = request.Recipients; // remove duplicate later
      applyComposerControls.call(this, event, request,
                                 function(lock) {
                                   sendButton.prop('disabled', lock);
                                   if (true) {
                                     self.prop('disabled', lock);
                                   } else {
                                     if (lock) {
                                       self.off('blur');
                                     } else {
                                       self.on('blur', function(event) {
                                         carbonCopyBlur.call(this, event, header);
                                       });
                                     }
                                   }
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
      var formData = form.find('fieldset.form-data');
      var projectId = formData.find('input[name="ProjectId"]').val();
      var projectName = formData.find('input[name="ProjectName"]').val();
      var events = eventAttachmentsSelector.val();
      if (!events) {
        events = [];
      }
      CAFEVDB.Projects.eventsPopup({ 'ProjectId': projectId,
                                     'ProjectName': projectName,
                                     'EventSelect': events},
                                   false /* only move to top if already open */);
      return false;
    });

    // Update our selected events on request
    dialogHolder.off('cafevdb:events_changed');
    dialogHolder.on('cafevdb:events_changed', function(event, events) {
      var formData = form.find('fieldset.form-data');
      var projectId = formData.find('input[name="ProjectId"]').val();
      var projectName = formData.find('input[name="ProjectName"]').val();
      var requestData = { 'Request': 'update',
                          'FormElement': 'EventAttachments',
                          'SingleItem': true,
                          'ProjectId': projectId,
                          'ProjectName': projectName,
                          'AttachedEvents': events };
      applyComposerControls.call(this, event, requestData);
      return false;
    });

    fieldset.find('input.delete-all-event-attachments').
      off('click').on('click', function(event) {

      // Ask for confirmation
      OC.dialogs.confirm(
        t('cafevdb',
          'Do you really want to delete all event attachments?'),
        t('cafevdb', 'Really Delete Attachments?'),
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
      var eventDialog = $('.cafevdb-project-events #events');
      var events = $(this).val();
      if (!events) {
        events = [];
        fieldset.find('tr.event-attachments').hide();
      }
      eventDialog.trigger('cafevdb:events_changed', [ events ]);

      return false;
    });

    /*************************************************************************
     *
     * File upload.
     */

    var updateFileAttachments = function() {
      var fileAttach = fieldset.find('input.file-attach').val();
      var selectedAttachments = fileAttachmentsSelector.val();

      var requestData = { 'Request': 'update',
                          'FormElement': 'FileAttachments',
                          'FileAttach': fileAttach, // JSON data of all files
                          'SingleItem': true,
                          'FormStatus': 'submitted' };
      if (selectedAttachments) {
        requestData.AttachedFiles = selectedAttachments;
      }
      applyComposerControls.call(this, $.Event('click'), requestData);
      return false;
    };

    // Arguably, these should only be active if the
    // composer tab is active. Mmmh.
    CAFEVDB.FileUpload.init({
      doneCallback: function (json) {
        CAFEVDB.Email.attachmentFromJSON(json, { 'origin': 'local' });
      },
      stopCallback: updateFileAttachments,
      dropZone: null, // initially disabled, enabled on tab-switch
      inputSelector: '#attachment_upload_start',
      containerSelector: '#attachment_upload_wrapper'
    });

    fieldset.find('.attachment.upload').off('click');
    fieldset.find('.attachment.upload').on('click', function() {
      $('#attachment_upload_start').trigger('click');
    });

    fieldset.find('.attachment.owncloud').off('click');
    fieldset.find('.attachment.owncloud').on('click', function() {
      OC.dialogs.filepicker(t('cafevdb', 'Select Attachment'),
                            function(path) {
                              CAFEVDB.Email.owncloudAttachment(path, updateFileAttachments);
                              return false;
                            },
                            false, '', true)
    });

    fieldset.find('input.delete-all-file-attachments').
      off('click').on('click', function(event) {

      // Ask for confirmation
      OC.dialogs.confirm(
        t('cafevdb',
          'Do you really want to delete all file attachments?'),
        t('cafevdb', 'Really Delete Attachments?'),
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
    var addressBookButton = fieldset.find('input.address-book-emails');
    addressBookButton.off('click');
    addressBookButton.on('click', function(event) {
      event.preventDefault();

      var self = $(this);
      var input = fieldset.find(self.data('for'));

      if (input.val().trim() != '') {
        // We trigger validation before we pop-up, but no need to do
        // so on empty input.
        input.trigger('blur');
      }

      var post = { 'FreeFormRecipients': input.val() };
      $.post(OC.filePath('cafevdb', 'ajax/email', 'addressbook.php'),
             post,
             function(data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [
                 'contents'
               ])) {
                 return false;
               }
               CAFEVDB.chosenPopup(data.data.contents, {
                 title: t('cafevdb', 'Address Book'),
                 saveText: t('cafevdb', 'Accept'),
                 buttons: [
                   { text: t('cafevdb', 'Save Contacts'),
                     'class': 'save-contacts',
                     title: t('cafevdb',
                              'Save the selected supplementary emails to the address-book for later reusal.'),
                     click: function() {
                       var dialogHolder = $(this);
                       var selectElement = dialogHolder.find('select');
                       // We are interested in all selected options
                       // inside the first options group
                       var selectedFreeForm = [];
                       selectElement.find('optgroup.free-form option:selected').each(function(idx) {
                         var self = $(this);
                         selectedFreeForm[idx] = { 'value': self.val(),
                                                   'html' : self.html(),
                                                   'text' : self.text() };
                       });
                       var innerPost = { 'AddressBookCandidates': selectedFreeForm };
                       $.post(OC.filePath('cafevdb', 'ajax/email', 'savecontacts.php'),
                              innerPost,
                              function(data) {
                                if (!CAFEVDB.ajaxErrorHandler(data, [])) {
                                  return false;
                                }
                                $.post(OC.filePath('cafevdb', 'ajax/email', 'addressbook.php'),
                                       post,
                                       function(data) {
                                         if (!CAFEVDB.ajaxErrorHandler(data, ['contents'])) {
                                           return false;
                                         }
                                         var newOptions = $(data.data.contents).html();
                                         selectElement.html(newOptions);
                                         selectElement.trigger('chosen:updated');
                                         if(selectElement.find('optgroup.free-form').length == 0) {
                                           dialogHolder.dialog('widget').
                                             find('button.save-contacts').prop('disabled', true);
                                         }
                                         return false;
                                       });
                                return false;
                              });
                     }
                   }
                 ],
                 dialogClass: 'address-book-emails',
                 position: { my: "right top",
                             at: "right bottom",
                             of: self },
                 openCallback: function(selectElement) {
                   if(selectElement.find('optgroup.free-form').length == 0) {
                     $(this).dialog('widget').
                       find('button.save-contacts').prop('disabled', true);
                   }
                 },
                 saveCallback: function(selectElement, selectedOptions) {
                   var recipients = '';
                   var numSelected = selectedOptions.length;
                   if (numSelected > 0) {
                     recipients += selectedOptions[0].text;
                     var idx;
                     for (idx = 1; idx < numSelected; ++idx) {
                       recipients += ', '+selectedOptions[idx].text;
                     }
                   }
                   input.val(recipients);
                   input.trigger('blur');
                   $(this).dialog('close');
                                     }
                 /*,closeCallback: function(selectElement) {}*/
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
      Email.tabResize(dialogWidget, panelHolder);
    });
  };

  /**Open the mass-email form in a popup window.
   *
   * @param post Necessary post data, either serialized or as
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
   */
  Email.emailFormPopup = function(post, modal, single, afterInit) {
    var self = this;

    if (this.active === true) {
      return;
    }

    this.active = true;

    if (typeof modal === 'undefined' || modal === undefined) {
      modal = true;
    }
    if (typeof single === 'undefined' || single === undefined) {
      single = false;
    }
    if (typeof afterInit != 'function') {
      afterInit = function() {};
    }
    var Email = this;
    $.post(OC.filePath('cafevdb', 'ajax/email', 'emailform.php'),
           post,
           function(data) {
             var containerId = 'emailformdialog';
             var dialogHolder;

             if (!CAFEVDB.ajaxErrorHandler(
               data, ['contents'], function() {
                                     self.active = false;
                                     afterInit(false);
                                   })) {
               return false;
             }

             afterInit(true);

             dialogHolder = $('<div id="'+containerId+'"></div>');
             dialogHolder.html(data.data.contents);
             $('body').append(dialogHolder);

             var emailForm = $('form#cafevdb-email-form');
             var recipientsPanel = dialogHolder.find('div#emailformrecipients');
             var composerPanel = dialogHolder.find('div#emailformcomposer');

             var dlgTitle = '';
             if (data.data.projectId >= 0) {
               dlgTitle = t('cafevdb', 'Em@il Form for {ProjectName}',
                            { ProjectName: data.data.projectName });
             } else {
               dlgTitle = t('cafevdb', 'Em@il Form');
             }

             if (modal) {
               CAFEVDB.modalizer(true);
             }

	     var recipientsAlertText;
	     if (data.data.projectId >= 0) {
	       recipientsAlertText = t('cafevdb', 'Email will be sent with an open recipients list!');
	     } else {
	       recipientsAlertText = t('cafevdb', 'Email will be sent with a hidden recipients list!');
	     }
	     OC.dialogs.alert(recipientsAlertText, t('cafevdb', 'Notice'), undefined, true);

             var popup = dialogHolder.cafevDialog({
               title: dlgTitle,
               position: { my: "middle top",
                           at: "middle bottom+50px",
                           of: "#header" },
               width: 'auto',
               height: 'auto',
               modal: false, // modal,
               closeOnEscape: false,
               dialogClass: 'emailform custom-close',
               resizable: false,
               open: function() {
                 $.fn.cafevTooltip.remove();
                 CAFEVDB.dialogToBackButton(dialogHolder);
                 CAFEVDB.dialogCustomCloseButton(dialogHolder, function(event, container) {
                   event.stopImmediatePropagation();
                   dialogHolder.find('input.submit.cancel[type="submit"]').trigger('click');
                   //dialogHolder.dialog('close');
                   return false;
                 });
                 var dialogWidget = dialogHolder.dialog('widget');
                 if (false && single) {
                   dialogHolder.find('li#emailformrecipients-tab').prop('disabled', true);
                   dialogHolder.find('li#emailformrecipients-tab a').prop('disabled', true);
                 }

                 dialogHolder.tabs({
                   active: single ? 1 : 0,
                   disabled: single ? [0] : [],
                   heightStyle: 'content',
                   create: function(event, ui) {
                     Email.tabResize(dialogWidget, ui.panel);
                     return true;
                   },
                   activate: function(event, ui) {
                     var newTabId = ui.newTab.attr('id');

                     if (newTabId == 'emailformdebug-tab') {
                       // The following is primarily for the debug
                       // output in order to get the scroll-bars right
                       var panel = ui.newPanel;
                       var newHeight = dialogWidget.height()
                                     - dialogWidget.find('.ui-dialog-titlebar').outerHeight(true);
                       newHeight -= $('#emailformtabs').outerHeight(true);
                       newHeight -= panel.outerHeight(true) - panel.height();
                       panel.height(newHeight);
                     } else {
                       if (newTabId == 'emailformcomposer-tab') {
                         $('#attachment_upload_start').fileupload('option', 'dropZone', ui.newPanel);
                       } else {
                         $('#attachment_upload_start').fileupload('option', 'dropZone', null);
                         var recipientsFieldSet = emailForm.find('fieldset.email-recipients.page');
                         Email.emailFormRecipientsSelectControls(dialogHolder, recipientsFieldSet);
                       }

                       // At least in FF there is also a resize event,
                       // but only for the composition window. Don't
                       // know why.
                       Email.tabResize(dialogWidget, ui.newPanel);
                     }

                     return true;
                   },
                   beforeActivate: function(event, ui) {
                     // When activating the composition window we
                     // first have to update the email addresses. This
                     // is cosmetics, but this entire thing is DAU
                     // cosmetics stuff
                     var newTabId = ui.newTab.attr('id');
                     var oldTabId = ui.oldTab.attr('id');

                     ui.newPanel.css('max-height', '');
                     ui.newPanel.css('height', 'auto');

                     if (oldTabId != 'emailformrecipients-tab' ||
                         newTabId != 'emailformcomposer-tab') {
                       return true;
                     }

                     // we better serialize the entire form here
                     var post = emailForm.serialize();
                     // place our update request
                     post += '&emailComposer[Request]=update&emailComposer[FormElement]=TO';
                     $.post(OC.filePath('cafevdb', 'ajax/email', 'composer.php'),
                            post,
                            function(data) {
                              if (!CAFEVDB.ajaxErrorHandler(data, [
                                'projectId', 'projectName', 'request', 'requestData'
                              ])) {
                                return false;
                              }
                              // could check whether formElement is indeed 'TO' ...
                              var toSpan = emailForm.find('span.email-recipients');
                              var rcpts = data.data.requestData.elementData;

                              if (rcpts.length == 0) {
                                rcpts = toSpan.data('placeholder');
                              }
                              var title = toSpan.data('titleIntro')+'<br>'+rcpts;

                              toSpan.html(rcpts);
                              toSpan.attr('title', title);
                              toSpan.cafevTooltip();
                              return false;
                            });
                     return true;
                   }
                 });

                 var recipientsFieldSet = emailForm.find('fieldset.email-recipients.page');
                 var composerFieldSet = emailForm.find('fieldset.email-composition.page');

                 // Fine, now add handlers and AJAX callbacks. We can
                 // probably move some of the code above to the
                 // respective tab-handler.
                 Email.emailFormRecipientsHandlers(recipientsFieldSet,
                                                   emailForm,
                                                   dialogHolder,
                                                   recipientsPanel);
                 Email.emailFormCompositionHandlers(composerFieldSet,
                                                    emailForm,
                                                    dialogHolder,
                                                    composerPanel);
               },
               close: function() {
                 $.fn.cafevTooltip.remove();
                 CAFEVDB.removeEditor(dialogHolder.find('textarea.wysiwygeditor'));
                 dialogHolder.dialog('close');
                 dialogHolder.dialog('destroy').remove();

                 // Also close all other open dialogs.
                 CAFEVDB.modalizer(false);
                 self.active = false;
               }
             });
             return false;
           });
  };

})(window, jQuery, CAFEVDB.Email);

$(document).ready(function(){

  $('button.eventattachments.edit').click(function(event) {
    event.preventDefault();

    // Edit existing event
    post = Array();
    var type = new Object();
    type['name']  = 'id';
    type['value'] = $(this).val();
    post.push(type);
    $('#dialog_holder').load(
      OC.filePath('calendar',
                  'ajax/event',
                  'edit.form.php'),
      post, function () {
        $('input[name="delete"]').attr('disabled','disabled');
        Calendar.UI.startEventDialog();
      });

    return false;
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

/**Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2013 Claus-Justus Heine <himself@claus-justus-heine.de>
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
  Email.emailFormRecipientsHandlers = function(fieldset, form, dialogHolder, panelHolder, layoutCB) {
    var Email = this;

    layoutCB();

    var recipientsSelect   = fieldset.find('select#recipients-select');
    var missingAddresses   = fieldset.find('.missing-email-addresses.names');
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
                 $('.tipsy').remove();
                 panelHolder.html(data.data.contents);
                 fieldset = panelHolder.find('fieldset.email-recipients.page');
                 Email.emailFormRecipientsHandlers(fieldset,
                                                   form,
                                                   dialogHolder,
                                                   panelHolder,
                                                   layoutCB);
               } else {
                 // Here goes the real work
                 // We only need to update the select-element and the list
                 // of musicians which should be possible recipients but
                 // do not have an email address.
                 recipientsSelect.html(data.data.recipientsOptions);
                 recipientsSelect.bootstrapDualListbox('refresh', true);
                 filterHistoryInput.val(data.data.filterHistory);
                 missingAddresses.html(data.data.missingEmailAddresses);
               }

               var filterHistory = $.parseJSON(data.data.filterHistory);
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
      instrumentsFilter.on('dblclick', applyRecipientsFilter);
    } else {
      var instrumentsFilter = fieldset.find('.instruments-filter.container select');
      instrumentsFilter.off('change');
      instrumentsFilter.on('change', applyRecipientsFilter);
    }

    // Member status filter
    var memberStatusFilter = fieldset.find('select.member-status-filter');
    memberStatusFilter.off('change');
    memberStatusFilter.on('change', applyRecipientsFilter);

    // Basic set
    var basicRecipientsSet = fieldset.find('.basic-recipients-set.container input[type="checkbox"]');
    basicRecipientsSet.off('change');
    basicRecipientsSet.on('change', applyRecipientsFilter);

    // "submit" when hitting any of the control buttons
    controlsContainer.off('click', '**');
    controlsContainer.on('click', 'input', applyRecipientsFilter);

    // Record history when the select box changes. Maybe too slow, but
    // we will see.
    recipientsSelect.off('change');
    recipientsSelect.on('change', function(event) {
      applyRecipientsFilter(event, true);
    });

    // Give the user a chance to change broken or missing email
    // addresses from here.
    dialogHolder.off('pmedialog:changed');
    dialogHolder.on('pmedialog:changed', applyRecipientsFilter);

    missingAddresses.off('click', 'span.personal-record');
    missingAddresses.on('click', 'span.personal-record', function(event) {
      event.preventDefault();

      var tableOptions = {
        AmbientContainerSelector: '#emailformdialog',
        DialogHolderCSSId: 'edit-musician-dialog', 
        headervisibility: CAFEVDB.headervisibility,
        // Now special options for the dialog popup
        InitialViewOperation: false,
        InitialName: 'PME_sys_operation',
        InitialValue: 'Change',
        ReloadName: 'PME_sys_operation',
        ReloadValue: 'Change',
        PME_sys_operation: 'Change?PME_sys_rec='+$(this).data('id'),
        PME_sys_rec: $(this).data('id'),
        ModalDialog: true,
        modified: false
      };

      var formData = form.find('fieldset.form-data');
      var projectId = formData.find('input[name="ProjectId"]').val();
      var projectName = formData.find('input[name="Project"]').val();
      tableOptions.ProjectId = projectId;
      tableOptions.Project = projectName;
      if (projectId >= 0) {
        tableOptions.Table = projectName+'View';
        tableOptions.Template = 'detailed-instrumenation'
        tableOptions.DisplayClass = 'DetailedInstrumentation';
      } else {
        tableOptions.Table = 'Musiker';
        tableOptions.Template = 'all-musicians';
        tableOptions.DisplayClass = 'Musicians';
      }
      //alert('Data: '+JSON.stringify(tableOptions));
      PHPMYEDIT.tableDialogOpen(tableOptions);
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
  Email.emailFormCompositionHandlers = function(fieldset, form, dialogHolder, panelHolder, layoutCB) {
    var Email = this;

    layoutCB();

    var debugOutput = form.find('#emailformdebug');
    var templateSelector = fieldset.find('select.email-template-selector');
    var currentTemplate = fieldset.find('#emailCurrentTemplate');
    var messageText = fieldset.find('textarea');
    var eventAttachmentsSelector = fieldset.find('select.event-attachments');
    var fileAttachmentsSelector = fieldset.find('select.file-attachments');    
    var sendButton = fieldset.find('input.submit.send');

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
        // Serialize almost everything and submit it
        post = fieldset.serialize();
        post += '&'+form.find('fieldset.form-data').serialize();
        if ($(this).is(':button') || $(this).is(':submit')) {
          var tmp = {};
          tmp[$(this).attr('name')] = $(this).val();
          post += '&'+$.param(tmp);
        }
        if (typeof request != 'undefined') {
          post += '&'+$.param({ emailComposer: request });
        }
      }
      $.post(OC.filePath('cafevdb', 'ajax/email', 'composer.php'),
             post,
             function(data) {
               var postponeEnable = false;
               $('.tipsy').remove();
               if (!CAFEVDB.ajaxErrorHandler(
                 data,
                 [ 'projectId', 'projectName', 'request', 'requestData' ],
                 validateUnlock)) {
                 return false;
               }
               var request = data.data.request;
               var requestData = data.data.requestData;
               switch (request) {
               case 'cancel':
                 break;
               case 'update':
                 switch (requestData.formElement) {
                 case 'everything':
                   // replace the entire tab.
                   $('.tipsy').remove();
                   CAFEVDB.removeEditor(panelHolder.find('textarea.wysiwygeditor'));
                   panelHolder.html(requestData.elementData);
                   fieldset = panelHolder.find('fieldset.email-composition.page');
                   Email.emailFormCompositionHandlers(fieldset,
                                                      form,
                                                      dialogHolder,
                                                      panelHolder,
                                                      layoutCB);
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
                   CAFEVDB.applyTipsy(toSpan);
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
                 // The request-data is an array of broken
                 // address. However, we do not replace them. Only
                 // inform the user about the mess.
                 var faultyRecipients = requestData.brokenRecipients;
                 var faultyItems = '';
                 var idx;
                 for (idx = 0; idx < faultyRecipients.length; ++idx) {
                   faultyItems += '<li>'+faultyRecipients[idx]+'</li>';
                 }
                 if (faultyItems != '') {
                   postponeEnable = true;
                   OC.dialogs.alert('<div class="error syntax contents">'+
                                    t('cafevdb',
                                      'The following email addresses appear to be invalid. '+
                                      'The email will not be sent out before these errors are corrected.')+
                                    '<ul>'+faultyItems+'</ul>'+
                                    '</div>',
                                    t('cafevdb', 'Invalid Email Addresses'),
                                    validateUnlock,
                                    true, true);
                 }
                 break;
               case 'setTemplate':
                 currentTemplate.val(requestData.templateName);
                 CAFEVDB.updateEditor(messageText, requestData.message);
                 templateSelector.find('option').prop('selected', false);
                 templateSelector.trigger("chosen:updated");
                 break;
               case 'saveTemplate':
                 if (!requestData.errorStatus) {
                   templateSelector.html(requestData.templateOptions);
                   templateSelector.find('option').prop('selected', false);
                   templateSelector.trigger("chosen:updated");
                 } else {
                   var errorList = '';
                   var failed;
                   var idx;
                   failed = requestData.errorDiagnostics.MemberErrors;
                   if (failed.length > 0) {
                     errorList += '<div class="error contents substitutions members">';
                     errorList += ('<span class="error heading">'+
                                   t('cafevdb', 'Failed individual substitutions')+
                                   '</span>');
                     errorList += '<ul>';
                     for (idx = 0; idx < failed.length; ++idx) {
                       errorList += '<li><span class="error item contents substitutions">'+failed[idx]+'</span></li>';
                     }
                     errorList += '</ul></div>';
                   }
                   failed = requestData.errorDiagnostics.GlobalErrors;
                   if (failed.length > 0) {
                     errorList += '<div class="error contents substitutions global">';
                     errorList += ('<span class="error heading">'+
                                   t('cafevdb', 'Failed global substitutions')+
                                   '</span>');
                     errorList += '<ul>';
                     for (idx = 0; idx < failed.length; ++idx) {
                       errorList += '<li><span class="error item contents substitutions">'+failed[idx]+'</span></li>';
                     }
                     errorList += '</ul></div>';
                   }
                   failed = requestData.errorDiagnostics.SpuriousErrors;
                   if (failed.length > 0) {
                     errorList += '<div class="error contents substitutions spurious">';
                     errorList += ('<span class="error heading">'+
                                   t('cafevdb', 'Failed spurious substitutions')+
                                   '</span>');
                     errorList += '<ul>';
                     for (idx = 0; idx < failed.length; ++idx) {
                       errorList += '<li><span class="error item contents substitutions">'+failed[idx]+'</span></li>';
                     }
                     errorList += '</ul></div>';
                   }
                   postponeEnable = true;
                   OC.dialogs.alert('<div class="error contents template">'+
                                    t('cafevdb',
                                      'Template not saved due to template validation errors,'+
                                      'the following variable substitutions could not be resolved:')+
                                    errorList+
                                    t('cafevdb',
                                      'Please understand that the software is really `picky\': '+
                                      'names have to match exactly. Please use only capital letters for variable names.'),
                                    t('cafevdb', 'Template could not be saved'),
                                    validateUnlock,
                                    true, true);
                 }
                 break;
               case 'deleteTemplate':
                 currentTemplate.val(requestData.templateName);
                 CAFEVDB.updateEditor(messageText, requestData.message);
                 templateSelector.html(requestData.templateOptions);
                 templateSelector.find('option').prop('selected', false);
                 templateSelector.trigger("chosen:updated");               
                 break;
               default:
                 postponeEnable = true;
                 OC.dialogs.alert(t('cafevdb',
                                    'Unknown request: {Request}',
                                    { Request: request }),
                                  t('cafevdb', 'Error'),
                                  validateUnlock,
                                  true, true);
                 break;
               };

               var debugText = '';
               if (typeof data.data.debug != 'undefined') {
                 debugText = data.data.debug;
               }
               debugOutput.html('<pre>'+debugText+'</pre>'+
                                JSON.stringify(requestData)+
                                $('<div></div>').text(CAFEVDB.urldecode(post)).html());
               if (!postponeEnable) {
                 validateUnlock();
               }
               return false;
             });
      return false;
    };

    /*************************************************************************
     * 
     * Finally send the entire mess to the recipients
     */
    sendButton.off('click').on('click', function(event) {
      event.stopImmediatePropagation();
      applyComposerControls.call(this, event, {
        'Request': 'send',
        'Send': 'ThePointOfNoReturn'
      });
      return false;
    });

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

    fieldset.find('input.submit.save-template').off('click');
    fieldset.find('input.submit.save-template').on('click', function(event) {
      var self = this;

      event.preventDefault();
      // We do a quick client-side validation and ask the user for ok
      // when a template with the same name is already present.
      var current = currentTemplate.val();
      if (templateSelector.find('option[value="'+current+'"]').length > 0) {
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
      return false;
    });

    fieldset.find('input.submit.delete-template').off('click');
    fieldset.find('input.submit.delete-template').on('click', function(event) {
      var self = this;
      event.preventDefault();
      // We do a quick client-side validation and ask the user for ok.
      var current = currentTemplate.val();
      if (templateSelector.find('option[value="'+current+'"]').length > 0) {
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
      return false;
    });

    templateSelector.off('change');
    templateSelector.on('change', function(event) {
      applyComposerControls.call(this, event, { 'Request': 'setTemplate' });
      return false;
    });
    
    /*************************************************************************
     * 
     * Subject and sender name. We simply trim the spaces away. Could also do this in JS.
     */
    fieldset.off('blur', 'input.email-subject, input.sender-name');
    fieldset.on('blur', 'input.email-subject, input.sender-name',
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
    var carbonCopy = fieldset.find('#carbon-copy, #blind-carbon-copy');
    var carbonCopyBlur = function(event) {
      var self = $(this);
      event.stopImmediatePropagation();
      applyComposerControls.call(this, event, { 'Request': 'validateEmailRecipients',
                                                'Recipients': $(this).val(),
                                                'SingleItem': true
                                              },
                                 function(lock) {
                                   sendButton.prop('disabled', lock);
                                   if (lock) {
                                     self.off('blur');
                                   } else {
                                     self.on('blur', carbonCopyBlur);
                                   }
                                 });
      return false;
    };

    carbonCopy.off('blur').on('blur', function(event) {
      return carbonCopyBlur.call(this, event);
    });

    /*************************************************************************
     * 
     * Project events attachments
     */

    fieldset.find('button.attachment.events').off('click').on('click', function(event) {
      var formData = form.find('fieldset.form-data');
      var projectId = formData.find('input[name="ProjectId"]').val();
      var projectName = formData.find('input[name="Project"]').val();
      var events = eventAttachmentsSelector.val();
      if (!events) {
        events = [];
      }
      CAFEVDB.Projects.eventsPopup({ 'ProjectId': projectId,
                                     'Project': projectName,
                                     'EventSelect': events});
      return false;
    });

    // Update our selected events on request
    dialogHolder.off('cafevdb:events_changed');
    dialogHolder.on('cafevdb:events_changed', function(event, events) {
      var formData = form.find('fieldset.form-data');
      var projectId = formData.find('input[name="ProjectId"]').val();
      var projectName = formData.find('input[name="Project"]').val();
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
      inputSelector: '#file_upload_start' });
    
    fieldset.find('.attachment.upload').off('click');
    fieldset.find('.attachment.upload').on('click', function() {
      $('#file_upload_start').trigger('click');
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
                       selectElement.find('optgroup:first option:selected').each(function(idx) {
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
      var dialogWidget = dialogHolder.dialog('widget');
      var titleOffset = (dialogWidget.find('.ui-dialog-titlebar').outerHeight(true)
                        +
                         dialogWidget.find('.ui-tabs-nav').outerHeight(true));
      var panelHeight = panelHolder.outerHeight(true);
      var panelOffset = panelHeight - panelHolder.height();
      var dialogHeight = dialogWidget.height();
      if (panelHeight > dialogHeight - titleOffset) {

        panelHolder.css('max-height', (dialogHeight-titleOffset-panelOffset)+'px');
      }
      if (panelHolder.get(0).scrollHeight > panelHolder.outerHeight(true)) {
        panelHolder.css('padding-right', '2.4em');
      } else {
        panelHolder.css('padding-right', '');
      }
      return true;
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
  Email.emailFormPopup = function(post, modal) {
    if (typeof modal == 'undefined') {
      modal = true;
    }
    var Email = this;
    $.post(OC.filePath('cafevdb', 'ajax/email', 'emailform.php'),
           post,
           function(data) {
             var containerId = 'emailformdialog';
             var dialogHolder;

             if (!CAFEVDB.ajaxErrorHandler(data, ['contents'])) {
               return false;
             }

             dialogHolder = $('<div id="'+containerId+'"></div>');
             dialogHolder.html(data.data.contents);
             $('body').append(dialogHolder);

             var dlgTitle = '';
             if (data.data.projectId >= 0) {
               dlgTitle = t('cafevdb', 'Em@il Form for {ProjectName}',
                            { ProjectName: data.data.projectName });
             } else {
               dlgTitle = t('cafevdb', 'Em@il Form');
             }
             var popup = dialogHolder.dialog({
               title: dlgTitle,
               position: { my: "middle top",
                           at: "middle bottom+50px",
                           of: "#header" },
               width: 'auto',
               height: 'auto',
               modal: modal,
               closeOnEscape: false,
               dialogClass: 'emailform custom-close',
               resizable: false,
               open: function() {
                 $('.tipsy').remove();
                 CAFEVDB.dialogToBackButton(dialogHolder);
                 CAFEVDB.dialogCustomCloseButton(dialogHolder, function(event, container) {
                   event.stopImmediatePropagation();
                   dialogHolder.find('input.submit.cancel[type="submit"]').trigger('click');
                   //dialogHolder.dialog('close');
                   return false;
                 });
                 var dialogWidget = dialogHolder.dialog('widget');
                 dialogHolder.tabs({
                   active: 0,
                   heightStyle: 'content',
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
                     }

                     if (newTabId == 'emailformcomposer-tab') {
                       $('#file_upload_start').fileupload('option', 'dropZone', ui.newPanel);
                     } else {
                       $('#file_upload_start').fileupload('option', 'dropZone', null);
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

                     ui.newPanel.css('height','auto');

                     if (oldTabId != 'emailformrecipients-tab' || newTabId != 'emailformcomposer-tab') {
                       return true;
                     }

                     // we better serialize the entire form here
                     var emailForm = $('form#cafevdb-email-form');
                     var post = emailForm.serialize();
                     post += '&emailComposer[Request]=update&emailComposer[FormElement]=TO'; // place our update request
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
                              CAFEVDB.applyTipsy(toSpan);
                              return false;
                            });
                     return true;
                   }
                 });
                 var emailForm = $('form#cafevdb-email-form');

                 var layoutRecipientsFilter = function() {
                   var emailForm = $('form#cafevdb-email-form');
                   emailForm.find('#member-status-filter').chosen();
                   emailForm.find('#member-status-filter').chosen();
                   emailForm.find('#instruments-filter option[value="*"]').remove();
                   emailForm.find('#instruments-filter option[value=""]').remove();
                   emailForm.find('#instruments-filter').chosen();
                   emailForm.find('#recipients-select').bootstrapDualListbox(
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
                   var dualSelect = emailForm.find('div.bootstrap-duallistbox-container select');
                   dualSelect.attr(
                     'title',
                     t('cafevdb', 'Click on the names to move the respective person to the other box'));
                   dualSelect.addClass('tipsy-s');

                   CAFEVDB.tipsy(dialogHolder.find('div#emailformrecipients'));
                 };
                 
                 var layoutMessageComposer = function() {
                   CAFEVDB.addEditor(dialogHolder.find('textarea.wysiwygeditor'), undefined, '20em');
                   $('#cafevdb-email-template-selector').chosen({ disable_search_threshold: 10 });
                   
                   var composerPanel = $('#emailformcomposer');
                   var fileAttachmentsSelect = composerPanel.find('#file-attachments-selector');
                   fileAttachmentsSelect.chosen();
                   fileAttachmentsSelect.on('chosen:showing_dropdown', function(event) {
                     composerPanel.stop().animate({
                       scrollTop: composerPanel.prop('scrollHeight')
                     }, 2000);
                   });
                   var eventAttachmentsSelect = composerPanel.find('#event-attachments-selector');
                   eventAttachmentsSelect.chosen();
                   eventAttachmentsSelect.on('chosen:showing_dropdown', function(event) {
                     composerPanel.stop().animate({
                       scrollTop: composerPanel.prop('scrollHeight')
                     }, 2000);
                   });
                   
                   CAFEVDB.tipsy(dialogHolder.find('div#emailformcomposer'));
                 }

                 // Fine, now add handlers and AJAX callbacks. We can
                 // probably move some of the code above to the
                 // respective tab-handler.
                 Email.emailFormRecipientsHandlers(emailForm.find('fieldset.email-recipients.page'),
                                                   emailForm,
                                                   dialogHolder,
                                                   dialogHolder.find('div#emailformrecipients'),
                                                   layoutRecipientsFilter);
                 Email.emailFormCompositionHandlers(emailForm.find('fieldset.email-composition.page'),
                                                    emailForm,
                                                    dialogHolder,
                                                    dialogHolder.find('div#emailformcomposer'),
                                                    layoutMessageComposer);
               },
               close: function() {
                 $('.tipsy').remove();
                 CAFEVDB.removeEditor(dialogHolder.find('textarea.wysiwygeditor'));
                 dialogHolder.dialog('close');
                 dialogHolder.dialog('destroy').remove();
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

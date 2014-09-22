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
  Email.numAttached = 0;

  Email.submitReloadForm = function() {
    // Simply submit the mess in order to let PHP do the update
    var emailForm = $('form.cafevdb-email-form');
    $('<input />').attr('type', 'hidden')
      .attr('name', 'writeMail')
      .attr('value', 'reload')
      .appendTo(emailForm);
    emailForm.submit();
  };

  Email.attachmentFromJSON = function (response) {
    var emailForm = $('form.cafevdb-email-form');
    if (emailForm == '') {
      OC.dialogs.alert(t('cafevdb', 'Not called from main email-form.'),
                       t('cafevdb', 'Error'));
      return;
    }

    var file = response.data;

    var k = ++Email.numAttached;
    // Fine. Attach some hidden inputs to the main form and submit it.
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-'+k+'][name]')
      .attr('value', file.name)
      .appendTo(emailForm);
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-'+k+'][type]')
      .attr('value', file.type)
      .appendTo(emailForm);
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-'+k+'][size]')
      .attr('value', file.size)
      .appendTo(emailForm);
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-'+k+'][tmp_name]')
      .attr('value', file.tmp_name)
      .appendTo(emailForm);
  };
  Email.owncloudAttachment = function(path) {
    $.getJSON(OC.filePath('cafevdb', 'ajax', 'email/owncloudattachment.php'),
              {'path':path},
              function(response) {
                if (response != undefined && response.status == 'success') {
                  CAFEVDB.Email.attachmentFromJSON(response);
                  CAFEVDB.Email.submitReloadForm();
                } else {
	          OC.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
                }
              });
  };
  /**Collapse the somewhat lengthy text at the head of the email page.
   */
  Email.collapsePageHeader = function () {
    var pfx    = '#'+CAFEVDB.name+'-email-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'header');
    var body   = $(pfx+'body');
    var button = $(pfx+'header-box #viewtoggle');

    box.removeClass('expanded').addClass('collapsed');
    header.removeClass('expanded').addClass('collapsed');
    body.removeClass('expanded').addClass('collapsed');
    button.removeClass('expanded').addClass('collapsed');

    CAFEVDB.broadcastHeaderVisibility('collapsed');
  };
  /**Expand the somewhat lengthy text at the head of the email page.
   */
  Email.expandPageHeader = function() {
    var pfx    = '#'+CAFEVDB.name+'-email-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'header');
    var body   = $(pfx+'body');    
    var button = $(pfx+'header-box #viewtoggle');
    
    box.addClass('expanded').removeClass('collapsed');
    header.addClass('expanded').removeClass('collapsed');
    body.addClass('expanded').removeClass('collapsed');
    button.addClass('expanded').removeClass('collapsed');

    CAFEVDB.broadcastHeaderVisibility('expanded');
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
    var missingAddresses   = fieldset.find('#missing-email-addresses-wrapper');
    var filterHistoryInput = fieldset.find('#recipients-filter-history');
    var debugOutput        = form.find('#emailformdebug');

    // Apply the instruments filter
    var applyRecipientsFilter = function(event) {
      event.preventDefault();
      
      var post = fieldset.serialize();
      post += '&'+form.find('fieldset.form-data').serialize();
      if ($(this).is(':button')) {
        var tmp = {};
        tmp[$(this).attr('name')] = $(this).val();
        post += '&'+$.param(tmp);
      }
      $.post(OC.filePath('cafevdb', 'ajax/email', 'recipients.php'),
             post,
             function(data) {
               if (!CAFEVDB.ajaxErrorHandler(data, [
                 'recipientsOptions', 'missingEmailAddresses', 'filterHistory'
               ])) {
                 return false;
               }
               if (typeof data.data.contents != 'undefined' && data.data.contents.length > 0) {
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

               var debugText = '';
               if (typeof data.data.debug != 'undefined') {
                 debugText = data.data.debug;
               }
               debugOutput.html('<pre>'+$('<div></div>').text(debugText).html()+'</pre>'+
                                '<pre>'+$('<div></div>').text(data.data.recipientsOptions).html()+'</pre>'+
                                data.data.missingEmailAddresses+'</br>'+
                                $('<div></div>').text(CAFEVDB.urldecode(post)).html())
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

    fieldset.find('input[name="cancel"]').off('click');
    fieldset.find('input[name="cancel"]').on('click', function(event) {
      dialogHolder.dialog('close');
      return false;
    });
   
  };    

  /**Open the mass-email form in a popup window
   */
  Email.emailFormPopup = function(post) {
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
               modal: true,
               closeOnEscape: false,
               dialogClass: 'emailform custom-close',
               resizable: false,
               open: function() {
                 $('.tipsy').remove();
                 CAFEVDB.dialogToBackButton(dialogHolder);
                 CAFEVDB.dialogCustomCloseButton(dialogHolder, function(event, container) {
                   event.stopImmediatePropagation();
                   dialogHolder.dialog('close');
                   return false;
                 });
                 var dialogWidget = dialogHolder.dialog('widget');
                 dialogHolder.tabs({
                   active: 0,
                   heightStyle: 'content',
                   activate: function(event, ui) {
                     var panel = ui.newPanel;
                     var newHeight = dialogWidget.height()
                                   - dialogWidget.find('.ui-dialog-titlebar').outerHeight(true);
                     newHeight -= $('#emailformtabs').outerHeight(true);
                     newHeight -= panel.outerHeight(true) - panel.height();
                     panel.height(newHeight);
                   }
                 });
                 CAFEVDB.addEditor(dialogHolder.find('textarea.wysiwygeditor'), undefined, '20em');
                 $('#cafevdb-email-template-selector').chosen({ disable_search_threshold: 10,
                                                                width: '10em' });
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
                                                    dialogHolder.find('div#emailformmessage'));

                 CAFEVDB.tipsy(dialogHolder);
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

  CAFEVDB.FileUpload.init(
    function (json) {
      CAFEVDB.Email.attachmentFromJSON(json);
    },
    function () {
      CAFEVDB.Email.submitReloadForm();
    });

  if ($('#emailrecipients #writeMail').length) {

    qf.elements.dualselect.init('DualSelectMusicians', true);

    $('#memberStatusFilter').chosen();

    $('#memberStatusFilter').change(function(event) {
      event.preventDefault();
      $('#emailrecipients').submit();
    });

    $('#selectedUserGroup-fromProject').click(function(event) {
      event.preventDefault();
      $('#emailrecipients').submit();
    });
    
    $('#selectedUserGroup-exceptProject').click(function(event) {
      event.preventDefault();
      $('#emailrecipients').submit();
    });
  } else {
    $('#cafevdb-email-template-selector').chosen({ disable_search_threshold: 10});

  
    $('#cafevdb-email-template-selector').change(function(event) {
      event.preventDefault();
      $('#cafevdb-email-form').submit();
    });
  }

  $('div.chosen-container').tipsy({gravity:'se', fade:true});
  $('li.active-result').tipsy({gravity:'w', fade:true});
  $('label').tipsy({gravity:'ne', fade:true});
 
  //$('#InstrumentenFilter-0').chosen();

  $('#cafevdb-email-header-box .viewtoggle').click(function(event) {
    event.preventDefault();

    var pfx    = 'div.'+CAFEVDB.name+'-email-';
    var box    = $(pfx+'header-box');
    var header = $(pfx+'header');
    var body   = $(pfx+'body');    

    if (CAFEVDB.headervisibility == 'collapsed') {
      CAFEVDB.Email.expandPageHeader();
    } else {
      CAFEVDB.Email.collapsePageHeader();
    }

    return false;
  });

  $('input[type=button].upload,button.attachment.upload').click(function() {
    $('#file_upload_start').trigger('click');
  });

  $('input[type=button].owncloud,button.attachment.owncloud').click(function() {
    OC.dialogs.filepicker(t('cafevdb', 'Select Attachment'),
                          CAFEVDB.Email.owncloudAttachment, false, '', true)
  });
  
  if (false) {
  $('#file_upload_start').change(function(){
    CAFEVDB.Email.uploadAttachments(this.files);
  });
  }

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

  $('input.alertdata.cafevdb-email-error').each(function(index) {
    var title = $(this).attr('name');
    var text  = $(this).attr('value');
    OC.dialogs.alert(text, title);
    $('#cafevdb-email-error').append('<u>'+title+'</u><br/>'+text+'<br/>');
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

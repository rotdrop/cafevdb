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

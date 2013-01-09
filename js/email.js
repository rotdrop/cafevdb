CAFEVDB.Email = {
  enabled:true,
  attachmentFromJSON:function (response) {
    var emailForm = $('form.cafevdb-email-form');
    if (emailForm == '') {
      OC.dialogs.alert(t('cafevdb', 'Not called from main email-form.'),
                       t('cafevdb', 'Error'));
      return;
    }

    var file = response.data;

    // Fine. Attach some hidden inputs to the main form and submit it.
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-1][name]')
      .attr('value', file.name)
      .appendTo(emailForm);
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-1][type]')
      .attr('value', file.type)
      .appendTo(emailForm);
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-1][size]')
      .attr('value', file.size)
      .appendTo(emailForm);
    $('<input />').attr('type', 'hidden')
      .attr('name', 'fileAttach[-1][tmp_name]')
      .attr('value', file.tmp_name)
      .appendTo(emailForm);
    
    // Simply submit the mess in order to let PHP do the update
    $('<input />').attr('type', 'hidden')
      .attr('name', 'writeMail')
      .attr('value', 'reload')
      .appendTo(emailForm);
    
    emailForm.submit();
  },
  uploadAttachments:function(filelist) {
    if(!this.enabled) {
      return;
    }
    if (!filelist) {
      OC.dialogs.alert(t('cafevdb', 'No files selected for upload.'),
                       t('cafevdb', 'Error'));
      return;
    }

    var file = filelist[0];
    var target = $('#file_upload_target');
    var form = $('#file_upload_form');
    var totalSize=0;
    if (file.size > $('#max_upload').val()) {
      OC.dialogs.alert(t('cafevdb',
                         'The file you are trying to upload exceeds the maximum size for file uploads on this server.'),
                       t('cafevdb', 'Error'));
      return;
    } else {
      target.load(function() {
        var response = jQuery.parseJSON(target.contents().text());
        if (response != undefined && response.status == 'success') {
          CAFEVDB.Email.attachmentFromJSON(response);
        } else {
          OC.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
        }
      });
      form.submit();
    }
  },
  owncloudAttachment:function(path) {
    $.getJSON(OC.filePath('cafevdb', 'ajax', 'email/owncloudattachment.php'),
              {'path':path},
              function(response) {
                if (response != undefined && response.status == 'success') {
                  CAFEVDB.Email.attachmentFromJSON(response);
                } else {
	          OC.dialogs.alert(response.data.message, t('cafevdb', 'Error'));
                }
              });
  },
  /**Collapse the somewhat lengthy text at the head of the email page.
   */
  collapsePageHeader:function () {
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
  },
  /**Expand the somewhat lengthy text at the head of the email page.
   */
  expandPageHeader:function() {
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
  }

};

$(document).ready(function(){

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
  
  $('#file_upload_start').change(function(){
    CAFEVDB.Email.uploadAttachments(this.files);
  });

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

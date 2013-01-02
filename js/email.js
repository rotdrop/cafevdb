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
                         'The file you are trying to upload exceed '
                         +
                         'the maximum size for file uploads on this server.'),
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
    var box    = $('div[class$="-email-header-box"]');
    var header = $('div[class$="-email-header"]');
    var body   = $('div[class$="-email-body"]');    
    
    if (box.data('CAFEVDBheaderboxheight') === undefined) {
      box.data('CAFEVDBheaderboxheight', box.css('height'));
      box.data('CAFEVDBheaderheight', header.css('height'));
      box.data('CAFEVDBbodypadding', body.css('padding-top'));
    }
    box.css('height','4ex');
    header.css('height','3ex');
    body.css('padding-top', '12ex');
    box.data('CAFEVDBheadermodheight', box.css('height'));
    $('input[name="headervisibility"]').each(function (idx) {
      $(this).val('collapsed');
    });
    $('#viewtoggle-img').attr(
      'src', OC.filePath('', 'core/img/actions', 'download.svg'));
  },
  /**Expand the somewhat lengthy text at the head of the email page.
   */
  expandPageHeader:function() {
    var box    = $('div[class$="-email-header-box"]');
    var header = $('div[class$="-email-header"]');
    var body   = $('div[class$="-email-body"]');    
    
    var boxheight = box.data('CAFEVDBheaderboxheight');
    var height    = box.data('CAFEVDBheaderheight');
    var padding   = box.data('CAFEVDBbodypadding');
    box.css('height', boxheight);
    header.css('height', height);
    body.css('padding-top', padding);
    $('input[name="headervisibility"]').each(function (idx) {
      $(this).val('expanded');
    });
    $('#viewtoggle-img').attr(
      'src', OC.filePath('', 'core/img/actions', 'delete.svg'));
  }
};

$(document).ready(function(){

  if (headervisibility == 'collapsed') {
    CAFEVDB.Email.collapsePageHeader();
  }

  $('div[class$="-email-header-box"] :button.viewtoggle').click(function(event) {
    event.preventDefault();
    var box    = $('div[class$="-email-header-box"]');
    var header = $('div[class$="-email-header"]');
    var body   = $('div[class$="-email-body"]');    

    if (box.data('CAFEVDBheaderboxheight') === undefined) {
      CAFEVDB.Email.collapsePageHeader();
    } else if (box.css('height') == box.data('CAFEVDBheadermodheight')) {
      CAFEVDB.Email.expandPageHeader();
    } else {
      CAFEVDB.Email.collapsePageHeader();
    }
    return false;
  });

  $('input[type=button].upload').click(function() {
    $('#file_upload_start').trigger('click');
  });

  $('input[type=button].owncloud').click(function() {
    OC.dialogs.filepicker(t('cafevdb', 'Select Attachment'),
                          CAFEVDB.Email.owncloudAttachment, false, '', true)
  });
  
  $('#file_upload_start').change(function(){
    CAFEVDB.Email.uploadAttachments(this.files);
  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

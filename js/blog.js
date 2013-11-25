var Blog = {
  author: 'unknown',
  blogId: -1,
  inReply: -1,
  text: '',
  priority: false,
  popup: false,
  reader: '',
  editWindow: function(data) {
    if (data.status == "success") {
      $('#dialog_holder').html(data.data.content);
      Blog.author   = data.data.author;
      Blog.blogId   = data.data.blogId;
      Blog.inReply  = data.data.inReply;
      Blog.text     = data.data.text;
      Blog.priority = data.data.priority;
      Blog.popup    = data.data.popup;
      Blog.reader   = data.data.reader;
    } else {
      OC.dialogs.alert(data.data.message,
                       t('cafevdb', 'Error'));
      if ($('#blogedit').dialog('isOpen')) {
        $('#blogedit').dialog('close');
      }
      return false;
    }
    $('div.debug').html(data.data.debug);
    $('div.debug').show();
    
    var popup = $('#blogedit').dialog({
      title: t('cafevdb', 'Edit Blog Entry'),
      modal: true,
      closeOnEscape: false,
      position: { my: "center center",
                  at: "center center",
                  of: window,
                  offset: "0 0" },
      width: 'auto',
      height: 'auto',
      open : function () { 
        $(".ui-dialog-titlebar-close").hide();
        
        $('button').tipsy({gravity:'ne', fade:true});
        $('input').tipsy({gravity:'ne', fade:true});
        $('label').tipsy({gravity:'ne', fade:true});

        if (CAFEVDB.toolTips) {
          $.fn.tipsy.enable();
        } else {
          $.fn.tipsy.disable();
        }
        
        $('#blogedit #blogcancel').click(Blog.cancel);
        $('#blogedit #blogsubmit').click(Blog.submit);

        $('#blogtextarea').val(Blog.text);

        //$('#blogtextarea').tinymce(myTinyMCE.config);
        //$('#blogtextarea').ckeditor(function() {}, {enterMode:CKEDITOR.ENTER_P});
        CAFEVDB.addEditor('#blogtextarea');
      },
      close : function(event, ui) {
        //$('#blogtextarea').tinymce().remove();
        //$('#blogtextarea').ckeditor().remove();
        CAFEVDB.removeEditor('#blogtextarea');
        $(this).dialog('destroy').remove();
      }
    });
    return true;
  },
  cancel: function(event) {
    event.preventDefault();
    //$('#blogtextarea').tinymce().save();
    if ($('#blogtextarea').val() == Blog.text) {
      $('#blogedit').dialog('close').remove();
    } else {
      OC.dialogs.confirm(t('cafevdb', 'The message content has been changed and will be lost if you press `Yes\''),
                         t('cafevdb', 'Really cancel current entry?'),
                         function (decision) {
                           if (decision) {
                             $('#blogedit').dialog('close').remove();
                           }
                         },
                         true);
    }
    return false;
  },
  submit: function(event) {   
    event.preventDefault();
    //$('#blogtextarea').tinymce().save();
    var popupValue = 0;
    if ($('#blogpopupset').attr('checked')) {
      popupValue = 1;
    } else if ($('#blogpopupclear').attr('checked')) {
      popupValue = -1;
    }
    if ($('#blogreaderclear').attr('checked')) {
      clearReaderValue = 1;
    } else {
      clearReaderValue = 0;
    }

    $.post(OC.filePath('cafevdb','ajax/blog','modifyentry.php'),
           {
             action: Blog.blogId >= 0 ? 'modify' : 'create',
             blogId: Blog.blogId,
             inReply: Blog.inReply,
             text: $('#blogtextarea').val(),
             priority: $('#blogpriority').val(),
             popup: popupValue,
             clearReader: clearReaderValue,
           }, function (data) {
             if (data.status == 'success') {
               $('#blogedit').dialog('close').remove();
               $('#blogform').submit();
               return true;
             } else {
               OC.dialogs.alert(data.data.message,
                                t('cafevdb', 'Error'));
               return true;
             }
           }, 'json');
    return false;
  }
};

$(document).ready(function() {

  $('#blogeditform').submit(function () { return false; });

  $('#blogform #blognewentry').click(function(event) {
    event.preventDefault();
    var post = $('#blogform').serializeArray();
    $.post(OC.filePath('cafevdb','ajax/blog','editentry.php'),
           post,
           Blog.editWindow, 'json');
    return false;
  });

  $('#blogentryactions button.reply').click(function(event) {
    event.preventDefault();
    $.post(OC.filePath('cafevdb','ajax/blog','editentry.php'),
           { blogId: -1,
             inReply: $(this).val() },
           Blog.editWindow, 'json');
    return false;
  });

  $('#blogentryactions button.edit').click(function(event) {
    event.preventDefault();
    $.post(OC.filePath('cafevdb','ajax/blog','editentry.php'),
           { blogId: $(this).val() ,
             inReply: -1
           },
           Blog.editWindow, 'json');
    return false;
  });

  $('#blogentryactions button.delete').click(function(event) {
    event.preventDefault();
    var blogId = $(this).val();
    OC.dialogs.confirm(t('cafevdb', 'The entire message thread will be deleted if you press `Yes\''),
                       t('cafevdb', 'Really delete the entry?'),
                       function (decision) {
                         if (decision) {
                           $.post(OC.filePath('cafevdb','ajax/blog','modifyentry.php'),
                                  { action: 'delete',
                                    blogId: blogId },
                                  function (data) {
                                    if (data.status == 'success') {
                                      $('#blogform').submit();
                                      return true;
                                    } else {
                                      OC.dialogs.alert(data.data.message,
                                                       t('cafevdb', 'Error'));
                                      return true;
                                    }
                                  }, 'json');
                         }
                       },
                       true);
    return false;
  });

  $('#blogentryactions button.raise').click(function(event) {
    event.preventDefault();
    var id = $(this).val();
    var prio = $('#blogpriority'+id).val();
    $.post(OC.filePath('cafevdb','ajax/blog','modifyentry.php'),
           { action: 'modify',
             text: '',
             blogId: id,
             priority: +prio+1,
             popup: false,
             inReply: -1
           },
           function (data) {
             if (data.status == 'success') {
               $('#blogform').submit();
               return true;
             } else {
               OC.dialogs.alert(data.data.message,
                                t('cafevdb', 'Error'));
               return true;
             }
           }, 'json');
    return false;
  });

  $('#blogentryactions button.lower').click(function(event) {
    event.preventDefault();
    var id = $(this).val();
    var prio = $('#blogpriority'+id).val();
    $.post(OC.filePath('cafevdb','ajax/blog','modifyentry.php'),
           { action: 'modify',
             text: '',
             blogId: id,
             priority: +prio-1,
             popup: false,
             inReply: -1
           },
           function (data) {
             if (data.status == 'success') {
               $('#blogform').submit();
               return true;
             } else {
               OC.dialogs.alert(data.data.message,
                                t('cafevdb', 'Error'));
               return true;
             }
           }, 'json');
    return false;
  });

  $('div.blogentrypopup').each(function(index) {
    var thisBlogId = $(this).find('input.blogentrypopupid').val();
    if (thisBlogId === false || thisBlogId ==='') {
      thisBlogId = -1;
    }
    $(this).dialog({
      dialogClass: 'no-close',
      title: t('cafevdb', 'One-time Blog Popup'),
      modal: true,
      closeOnEscape: false,
      position: { my: "center center",
                  at: "center center",
                  of: window,
                  offset: "0 0" },
      width: 'auto',
      height: 'auto',
      buttons: [ { text: t('cafevdb', 'I have read this popup, please bother me no more!'),
                   title: t('cafevdb', 'Mark this popup as read; the popup will not show up again.'),
                   click: function() {
                     $.post(OC.filePath('cafevdb', 'ajax/blog', 'modifyentry.php'),
                            {
                              action: 'markread',
                              blogId: thisBlogId
                            },
                            function (data) {
                              if (data.status == 'success') {
                                // no need to submit the form
                                //$('#blogform').submit();
                                return true;
                              } else {
                                OC.dialogs.alert(data.data.message,
                                                 t('cafevdb', 'Error'));
                                return true;
                              }
                            }, 'json');
                     $(this).dialog("close").remove();
                 }
                 } ],
      open : function () {
        $(".ui-dialog-titlebar-close").hide();
        
        $('button').tipsy({gravity:'ne', fade:true});
        $('input').tipsy({gravity:'ne', fade:true});
        $('label').tipsy({gravity:'ne', fade:true});

        if (CAFEVDB.toolTips) {
          $.fn.tipsy.enable();
        } else {
          $.fn.tipsy.disable();
        }
      },
      close : function(event, ui) {
        $('.tipsy').remove();
        $(this).dialog('destroy').remove();
      },
    });
  });
});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***


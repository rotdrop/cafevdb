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

CAFEVDB = CAFEVDB || {};

(function(window, $, CAFEVDB, undefined) {
  'use strict';
  var Blog = function() {};

  Blog.author   = 'unknown';
  Blog.blogId   = -1;
  Blog.inReply  = -1;
  Blog.text     = '';
  Blog.priority = false;
  Blog.popup    = false;
  Blog.reader   = '';
  Blog.popupPosition = { my: "middle top+5%",
                         at: "middle bottom",
                         of: '#controls',
                         offset: "0 0" };
  Blog.editWindow = function(data) {
    var blog = Blog;

    if (data.status == "success") {
      $('#dialog_holder').html(data.data.content);
      CAFEVDB.Blog.author   = data.data.author;
      CAFEVDB.Blog.blogId   = data.data.blogId;
      CAFEVDB.Blog.inReply  = data.data.inReply;
      CAFEVDB.Blog.text     = data.data.text;
      CAFEVDB.Blog.priority = data.data.priority;
      CAFEVDB.Blog.popup    = data.data.popup;
      CAFEVDB.Blog.reader   = data.data.reader;
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
      position: blog.popupPosition,
      width: 'auto',
      height: 'auto',
      resizable: false,
      open : function () { 
        var self = this;

        $(".ui-dialog-titlebar-close").hide();
        
        $('button').tipsy({gravity:'ne', fade:true});
        $('input').tipsy({gravity:'ne', fade:true});
        $('label').tipsy({gravity:'ne', fade:true});

        if (CAFEVDB.toolTips) {
          $.fn.tipsy.enable();
        } else {
          $.fn.tipsy.disable();
        }
        
        $('#blogedit #blogcancel').click(CAFEVDB.Blog.cancel);
        $('#blogedit #blogsubmit').click(CAFEVDB.Blog.submit);

        $('#blogtextarea').val(CAFEVDB.Blog.text);

        //$('#blogtextarea').tinymce(myTinyMCE.config);
        //$('#blogtextarea').ckeditor(function() {}, {enterMode:CKEDITOR.ENTER_P});
        CAFEVDB.addEditor('#blogtextarea', function() {
          $(self).dialog('option', 'position', blog.popupPosition);
        });
      },
      close : function(event, ui) {
        //$('#blogtextarea').tinymce().remove();
        //$('#blogtextarea').ckeditor().remove();
        CAFEVDB.removeEditor('#blogtextarea');
        $(this).dialog('destroy').remove();
      }
    });
    return true;
  };
  Blog.cancel = function(event) {
    event.preventDefault();
    //$('#blogtextarea').tinymce().save();
    if ($('#blogtextarea').val() == CAFEVDB.Blog.text) {
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
  };
  Blog.submit = function(event) {   
    event.preventDefault();
    //$('#blogtextarea').tinymce().save();
    var popupValue = 0;
    if ($('#blogpopupset').attr('checked') !== undefined) {
      popupValue = 1;
    } else if ($('#blogpopupclear').attr('checked') !== undefined) {
      popupValue = -1;
    }
    var clearReaderValue = 0;
    if ($('#blogreaderclear').attr('checked') !== undefined) {
      clearReaderValue = 1;
    } else {
      clearReaderValue = 0;
    }

    $.post(OC.filePath('cafevdb','ajax/blog','modifyentry.php'),
           {
             action: CAFEVDB.Blog.blogId >= 0 ? 'modify' : 'create',
             blogId: CAFEVDB.Blog.blogId,
             inReply: CAFEVDB.Blog.inReply,
             text: $('#blogtextarea').val(),
             priority: $('#blogpriority').val(),
             popup: popupValue,
             clearReader: clearReaderValue,
           }, function (data) {
             if (data.status == 'success') {
               $('#blogedit').dialog('close').remove();
               $('#blogthreads').html(data.data.contents);
               CAFEVDB.Blog.popupMessages();
               return true;
             } else {
               OC.dialogs.alert(data.data.message,
                                t('cafevdb', 'Error'));
               return true;
             }
           }, 'json');
    return false;
  };

  Blog.popupMessages = function() {
    var blogThreads = $('#blogthreads');
    
    blogThreads.find('div.blogentrypopup').each(function(index) {
      var thisBlogId = $(this).find('input.blogentrypopupid').val();
      if (thisBlogId === false || thisBlogId ==='') {
        thisBlogId = -1;
      }
      $(this).dialog({
        dialogClass: 'no-close',
        title: t('cafevdb', 'One-time Blog Popup'),
        modal: true,
        closeOnEscape: false,
        position: CAFEVDB.Blog.popupPosition,
        width: 'auto',
        height: 'auto',
        resizable: false,
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
  };

  CAFEVDB.Blog = Blog;

})(window, jQuery, CAFEVDB);



$(document).ready(function() {

  $('#blogeditform').submit(function () { return false; });

  $('#blogform #blognewentry').click(function(event) {
    event.preventDefault();
    var post = $('#blogform').serializeArray();
    $.post(OC.filePath('cafevdb','ajax/blog','editentry.php'),
           post,
           CAFEVDB.Blog.editWindow, 'json');
    return false;
  });

  // Use delegate handlers for dynamic content
  var blogThreads = $('#blogthreads');

  blogThreads.on('click',
                 '#blogentryactions button.reply',
                 function(event) {
                   event.preventDefault();
                   $.post(OC.filePath('cafevdb','ajax/blog','editentry.php'),
                          { blogId: -1,
                            inReply: $(this).val() },
                          CAFEVDB.Blog.editWindow, 'json');
                   return false;
                 });

  blogThreads.on('click',
                 '#blogentryactions button.edit',
                 function(event) {
                   event.preventDefault();
                   $.post(OC.filePath('cafevdb','ajax/blog','editentry.php'),
                          { blogId: $(this).val() ,
                            inReply: -1
                          },
                          CAFEVDB.Blog.editWindow, 'json');
                   return false;
                 });

  
  blogThreads.on('click',
                 '#blogentryactions button.delete',
                 function(event) {
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
                                                     $('#blogthreads').html(data.data.contents);
                                                     CAFEVDB.Blog.popupMessages();
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

  blogThreads.on('click',
                 '#blogentryactions button.raise',
                 function(event) {
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
                              $('#blogthreads').html(data.data.contents);
                              CAFEVDB.Blog.popupMessages();
                              return true;
                            } else {
                              OC.dialogs.alert(data.data.message,
                                               t('cafevdb', 'Error'));
                              return true;
                            }
                          }, 'json');
                   return false;
                 });

  blogThreads.on('click',
                 '#blogentryactions button.lower',
                 function(event) {
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
                              $('#blogthreads').html(data.data.contents);
                              CAFEVDB.Blog.popupMessages();
                              return true;
                            } else {
                              OC.dialogs.alert(data.data.message,
                                               t('cafevdb', 'Error'));
                              return true;
                            }
                          }, 'json');
                   return false;
                 });
  

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***


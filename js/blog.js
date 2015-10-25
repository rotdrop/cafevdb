/* Orchestra member, musicion and project management application.
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
      dialogClass: 'blog-edit-dialog custom-close resize-target',
      modal: true,
      closeOnEscape: false,
      position: Blog.popupPosition,
      width: 'auto',
      height: 'auto',
      resizable: false,
      open : function () {
        var self = this;

        var dialogHolder = $(self);
        var dialogWidget = dialogHolder.dialog('widget');

        CAFEVDB.dialogCustomCloseButton(dialogHolder, function(event, container) {
          event.preventDefault();
          var cancelButton = container.find('#blogcancel').first();
          if (cancelButton.length > 0) {
            event.stopImmediatePropagation();
            cancelButton.trigger('click');
          }
          return false;
        });

        var resizeHandler = function(parameters) {
          dialogHolder.dialog('option', 'height', 'auto');
          dialogHolder.dialog('option', 'width', 'auto');
          var newHeight = dialogWidget.height()
                        - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
          newHeight -= dialogHolder.outerHeight(false) - dialogHolder.height();
          //alert("Setting height to " + newHeight);
          dialogHolder.height(newHeight);
        };

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
        CAFEVDB.addEditor('#blogtextarea', function() {
          $(self).dialog('option', 'position', Blog.popupPosition);
        });

        dialogHolder.on('resize', resizeHandler);
      },
      close : function(event, ui) {
        //$('#blogtextarea').tinymce().remove();
        //$('#blogtextarea').ckeditor().remove();
        $('.tipsy').remove();
        CAFEVDB.removeEditor('#blogtextarea');
        $(this).dialog('destroy').remove();
      }
    });
    return true;
  };
  Blog.cancel = function(event) {
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
  };
  Blog.submit = function(event) {
    var self = this;
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
             action: Blog.blogId >= 0 ? 'modify' : 'create',
             blogId: Blog.blogId,
             inReply: Blog.inReply,
             text: $('#blogtextarea').val(),
             priority: $('#blogpriority').val(),
             popup: popupValue,
             clearReader: clearReaderValue
           }, function (data) {
             if (data.status == 'success') {
               $('#blogedit').dialog('close').remove();
               Blog.updateThreads(data);
               return true;
             } else {
               OC.dialogs.alert(data.data.message,
                                t('cafevdb', 'Error'));
               return true;
             }
           }, 'json');
    return false;
  };

  Blog.avatar = function() {
    var self = this;
    var blogThreads = $('#blogthreads');

    blogThreads.find('span.avatar').each(function(index) {
      var self = $(this);
      var author = self.data('author');
      var size = self.data('size');
      self.avatar(author, size);

    });
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
        position: Blog.popupPosition,
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

  Blog.updateThreads = function(data) {
    if (data.status == 'success') {
      var blogThreads = $('#blogthreads');
      blogThreads.html(data.data.contents);
      Blog.popupMessages();
      Blog.avatar();
      return true;
    } else {
      OC.dialogs.alert(data.data.message, t('cafevdb', 'Error'));
      return true;
    }
  };

  CAFEVDB.Blog = Blog;

})(window, jQuery, CAFEVDB);



$(document).ready(function() {

  CAFEVDB.addReadyCallback(function() {
    var Blog = CAFEVDB.Blog;

    $('#blogeditform').submit(function () { return false; });

    $('#blogform #blognewentry').click(function(event) {
      event.preventDefault();
      var post = $('#blogform').serializeArray();
      $.post(OC.filePath('cafevdb','ajax/blog','editentry.php'),
             post,
             Blog.editWindow, 'json');
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
                            Blog.editWindow, 'json');
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
                            Blog.editWindow, 'json');
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
                                                   Blog.updateThreads,
                                                   'json');
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
                            Blog.updateThreads,
                            'json');
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
                            Blog.updateThreads,
                            'json');
                     return false;
                   });

    Blog.popupMessages(); // annoy people
    Blog.avatar(); // display avatars

  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

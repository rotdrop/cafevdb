/* Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

(function(window, $, CAFEVDB, undefined) {
  'use strict';
  var Blog = function() {};

  Blog.author    = 'unknown';
  Blog.blogId    = -1;
  Blog.inReplyTo = -1;
  Blog.content   = '';
  Blog.priority  = false;
  Blog.popup     = false;
  Blog.reader    = '';
  Blog.popupPosition = { my: "middle top+5%",
                         at: "middle bottom",
                         of: '#controls',
                         offset: "0 0" };
  Blog.editWindow = function(data) {
    var blog = Blog;

    $('#dialog_holder').html(data.content);
    Blog.author    = data.author;
    Blog.blogId    = data.blogId;
    Blog.inReplyTo = data.inReplyTo;
    Blog.text      = data.text;
    Blog.priority  = data.priority;
    Blog.popup     = data.popup;
    Blog.reader    = data.reader;

    $('div.debug').html(data.debug);
    $('div.debug').show();

    var popup = $('#blogedit').cafevDialog({
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

        dialogWidget.find('button, input, label').cafevTooltip({position:'auto bottom'});

        if (CAFEVDB.toolTipsEnabled) {
          $.fn.cafevTooltip.enable();
        } else {
          $.fn.cafevTooltip.disable();
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
        $.fn.cafevTooltip.remove();
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
      OC.dialogs.confirm(
        t('cafevdb', 'The message content has been changed and will be lost if you press `Yes\''),
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

    const action = Blog.blogId >= 0 ? 'modify' : 'create';
    $.post(OC.generateUrl('/apps/cafevdb/blog/action/' + action),
           {
             blogId: Blog.blogId,
             inReplyTo: Blog.inReplyTo,
             content: $('#blogtextarea').val(),
             priority: $('#blogpriority').val(),
             popup: popupValue,
             clearReader: clearReaderValue
           })
    .fail(function(xhr, status, errorThrown) {
            const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown);
            OC.dialogs.alert(message, t('cafevdb', 'Error'));
    })
    .done(function (data) {
      $('#blogedit').dialog('close').remove();
      Blog.updateThreads(data);
      return true;
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
      $(this).cafevDialog({
        dialogClass: 'no-close blog-popup-dialog',
        title: t('cafevdb', 'One-time Blog Popup'),
        modal: true,
        closeOnEscape: false,
        position: Blog.popupPosition,
        width: 'auto',
        height: 'auto',
        resizable: false,
        buttons: [
          { text: t('cafevdb', 'I have read this popup, please bother me no more!'),
            title: t('cafevdb', 'Mark this popup as read; the popup will not show up again.'),
            click: function() {
              const action = 'markread';
              $.post(OC.generateUrl('/apps/cafevdb/blog/action/' + action),
                     { blogId: thisBlogId })
	      .fail(function(xhr, status, errorThrown) {
                const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)
                OC.dialogs.alert(message, t('cafevdb', 'Error'));
              })
              .done(function (data) {
                // no need to submit the form
              });
              $(this).dialog("close").remove();
            }
          } ],
        open : function () {
          $(".ui-dialog-titlebar-close").hide();

          $('button').cafevTooltip({position:'auto bottom'});
          $('input').cafevTooltip({position:'auto bottom'});
          $('label').cafevTooltip({position:'auto bottom'});

          if (CAFEVDB.toolTips) {
            $.fn.cafevTooltip.enable();
          } else {
            $.fn.cafevTooltip.disable();
          }
        },
        close : function(event, ui) {
          $.fn.cafevTooltip.remove();
          $(this).dialog('destroy').remove();
        },
      });
    });
  };

  Blog.updateThreads = function(data) {
    var blogThreads = $('#blogthreads');
    blogThreads.html(data.content);
    Blog.popupMessages();
    Blog.avatar();
    return true;
  };

  CAFEVDB.Blog = Blog;

})(window, jQuery, CAFEVDB);



$(function() {

  CAFEVDB.addReadyCallback(function() {
    var Blog = CAFEVDB.Blog;

    $('#blogeditform').submit(function () { return false; });

    $('#blogform #blognewentry').click(function(event) {
      event.preventDefault();
      var post = $('#blogform').serializeArray();
      $.post(OC.generateUrl('/apps/cafevdb/blog/editentry'),
             post)
      .fail(function(xhr, status, errorThrown) {
        const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)
        OC.dialogs.alert(message, t('cafevdb', 'Error'));
      })
      .done(Blog.editWindow);
      return false;
    });

    // Use delegate handlers for dynamic content
    var blogThreads = $('#blogthreads');

    blogThreads.on(
      'click',
      '#blogentryactions button.reply',
      function(event) {
        event.preventDefault();
        $.post(OC.generateUrl('/apps/cafevdb/blog/editentry'),
               { blogId: -1,
                 inReplyTo: $(this).val() })
        .fail(function(xhr, status, errorThrown) {
          const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)
          OC.dialogs.alert(message, t('cafevdb', 'Error'));
        })
        .done(Blog.editWindow);
        return false;
      });

    blogThreads.on(
      'click',
      '#blogentryactions button.edit',
      function(event) {
        event.preventDefault();
        $.post(OC.generateUrl('/apps/cafevdb/blog/editentry'),
               { blogId: $(this).val() ,
                 inReplyTo: -1
               })
        .fail(function(xhr, status, errorThrown) {
          const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)
          OC.dialogs.alert(message, t('cafevdb', 'Error'));
        })
        .done(Blog.editWindow);
        return false;
      });


    blogThreads.on(
      'click',
      '#blogentryactions button.delete',
      function(event) {
        event.preventDefault();
        var blogId = $(this).val();
        OC.dialogs.confirm(
          t('cafevdb', 'The entire message thread will be deleted if you press `Yes\''),
          t('cafevdb', 'Really delete the entry?'),
          function (decision) {
            if (decision) {
              const action = 'delete';
              $.post(OC.generateUrl('/apps/cafevdb/blog/action/' + action),
                     { blogId: blogId })
	      .fail(function(xhr, status, errorThrown) {
                const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)
                OC.dialogs.alert(message, t('cafevdb', 'Error'));
              })
              .done(Blog.updateThreads);
            }
          },
          true);
        return false;
      });

    blogThreads.on(
      'click',
      '#blogentryactions button.raise',
      function(event) {
        event.preventDefault();
        var id = $(this).val();
        var prio = $('#blogpriority'+id).val();
        const action = 'modify';
        $.post(OC.generateUrl('/apps/cafevdb/blog/action/' + action),
               { content: '',
                 blogId: id,
                 priority: +prio+1,
                 popup: false,
                 inReplyTo: -1
               })
	.fail(function(xhr, status, errorThrown) {
          const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)
          OC.dialogs.alert(message, t('cafevdb', 'Error'));
        })
        .done(Blog.updateThreads);
        return false;
      });

    blogThreads.on(
      'click',
      '#blogentryactions button.lower',
      function(event) {
        event.preventDefault();
        var id = $(this).val();
        var prio = $('#blogpriority'+id).val();
        const action = 'modify';
        $.post(OC.generateUrl('/apps/cafevdb/blog/action/' + action),
               { content: '',
                 blogId: id,
                 priority: +prio-1,
                 popup: false,
                 inReplyTo: -1
               })
	.fail(function(xhr, status, errorThrown) {
          const message = CAFEVDB.ajaxFailMessage(xhr, status, errorThrown)
          OC.dialogs.alert(message, t('cafevdb', 'Error'));
        })
        .done(Blog.updateThreads);
        return false;
      });

    Blog.popupMessages(); // annoy people
    Blog.avatar(); // display avatars

  });

});

// Local Variables: ***
// js-indent-level: 2 ***
// End: ***

/**
 * Orchestra member, musicion and project management application.
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

import { globalState, $ } from './globals.js';
import generateUrl from './generate-url.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Dialogs from './dialogs.js';
import * as WysiwygEditor from './wysiwyg-editor.js';

require('blog.css');

globalState.Blog = {
  author: 'unknown',
  blogId: -1,
  inReplyTo: -1,
  content: '',
  priority: false,
  popup: false,
  reader: '',
};

const popupPosition = {
  my: 'middle top+5%',
  at: 'middle bottom',
  of: '#controls',
  offset: '0 0',
};

const editWindow = function(data) {
  const blog = globalState.Blog;

  $('#dialog_holder').html(data.content);
  blog.author = data.author;
  blog.blogId = data.blogId;
  blog.inReplyTo = data.inReplyTo;
  blog.text = data.text;
  blog.priority = data.priority;
  blog.popup = data.popup;
  blog.reader = data.reader;

  $('div.debug').html(data.debug);
  $('div.debug').show();

  $('#blogedit').cafevDialog({
    title: t('cafevdb', 'Edit Blog Entry'),
    dialogClass: 'blog-edit-dialog custom-close resize-target',
    modal: true,
    closeOnEscape: false,
    position: popupPosition,
    width: 'auto',
    height: 'auto',
    resizable: false,
    open() {
      const self = this;

      const dialogHolder = $(self);
      const dialogWidget = dialogHolder.dialog('widget');

      CAFEVDB.dialogCustomCloseButton(dialogHolder, function(event, container) {
        event.preventDefault();
        const cancelButton = container.find('#blogcancel').first();
        if (cancelButton.length > 0) {
          event.stopImmediatePropagation();
          cancelButton.trigger('click');
        }
        return false;
      });

      const resizeHandler = function(parameters) {
        dialogHolder.dialog('option', 'height', 'auto');
        dialogHolder.dialog('option', 'width', 'auto');
        let newHeight = dialogWidget.height()
            - dialogWidget.find('.ui-dialog-titlebar').outerHeight();
        newHeight -= dialogHolder.outerHeight(false) - dialogHolder.height();
        // alert('Setting height to ' + newHeight);
        dialogHolder.height(newHeight);
      };

      $('.ui-dialog-titlebar-close').hide();

      dialogWidget.find('button, input, label').cafevTooltip({ position: 'auto bottom' });

      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }

      $('#blogedit #blogcancel').click(cancel);
      $('#blogedit #blogsubmit').click(submit);

      $('#blogtextarea').val(globalState.Blog.text);

      // $('#blogtextarea').tinymce(myTinyMCE.config);
      // $('#blogtextarea').ckeditor(function() {}, {enterMode:CKEDITOR.ENTER_P});
      WysiwygEditor.addEditor('#blogtextarea', function() {
        $(self).dialog('option', 'position', popupPosition);
      });

      dialogHolder.on('resize', resizeHandler);
    },
    close(event, ui) {
      // $('#blogtextarea').tinymce().remove();
      // $('#blogtextarea').ckeditor().remove();
      $.fn.cafevTooltip.remove();
      WysiwygEditor.removeEditor('#blogtextarea');
      $(this).dialog('destroy').remove();
    },
  });
  return true;
};
const cancel = function(event) {
  event.preventDefault();
  // $('#blogtextarea').tinymce().save();
  if ($('#blogtextarea').val() === globalState.Blog.text) {
    $('#blogedit').dialog('close').remove();
  } else {
    Dialogs.confirm(
      t('cafevdb', 'The message content has been changed and will be lost if you press `Yes\''),
      t('cafevdb', 'Really cancel current entry?'),
      function(decision) {
        if (decision) {
          $('#blogedit').dialog('close').remove();
        }
      },
      true);
  }
  return false;
};

const submit = function(event) {
  event.preventDefault();
  // $('#blogtextarea').tinymce().save();
  let popupValue = 0;
  if ($('#blogpopupset').attr('checked') !== undefined) {
    popupValue = 1;
  } else if ($('#blogpopupclear').attr('checked') !== undefined) {
    popupValue = -1;
  }
  let clearReaderValue = 0;
  if ($('#blogreaderclear').attr('checked') !== undefined) {
    clearReaderValue = 1;
  } else {
    clearReaderValue = 0;
  }

  const action = globalState.Blog.blogId >= 0 ? 'modify' : 'create';
  $.post(generateUrl('blog/action/' + action), {
    blogId: globalState.Blog.blogId,
    inReplyTo: globalState.Blog.inReplyTo,
    content: $('#blogtextarea').val(),
    priority: $('#blogpriority').val(),
    popup: popupValue,
    clearReader: clearReaderValue,
  })
    .fail(function(xhr, status, errorThrown) {
      const message = Ajax.failMessage(xhr, status, errorThrown);
      Dialogs.alert(message, t('cafevdb', 'Error'));
    })
    .done(function(data) {
      $('#blogedit').dialog('close').remove();
      updateThreads(data);
      return true;
    }, 'json');
  return false;
};

const avatar = function() {
  const blogThreads = $('#blogthreads');

  blogThreads.find('span.avatar').each(function(index) {
    const self = $(this);
    const author = self.data('author');
    const size = self.data('size');
    self.avatar(author, size);

  });
};

const popupMessages = function() {
  const blogThreads = $('#blogthreads');

  blogThreads.find('div.blogentrypopup').each(function(index) {
    let thisBlogId = $(this).find('input.blogentrypopupid').val();
    if (thisBlogId === false || thisBlogId === '') {
      thisBlogId = -1;
    }
    $(this).cafevDialog({
      dialogClass: 'no-close blog-popup-dialog',
      title: t('cafevdb', 'One-time Blog Popup'),
      modal: true,
      closeOnEscape: false,
      position: popupPosition,
      width: 'auto',
      height: 'auto',
      resizable: false,
      buttons: [
        {
          text: t('cafevdb', 'I have read this popup, please bother me no more!'),
          title: t('cafevdb', 'Mark this popup as read; the popup will not show up again.'),
          click() {
            const action = 'markread';
            $.post(generateUrl('blog/action/' + action), { blogId: thisBlogId })
	      .fail(function(xhr, status, errorThrown) {
                const message = Ajax.failMessage(xhr, status, errorThrown);
                Dialogs.alert(message, t('cafevdb', 'Error'));
              })
              .done(function(data) {
                // no need to submit the form
              });
            $(this).dialog('close').remove();
          },
        },
      ],
      open() {
        $('.ui-dialog-titlebar-close').hide();

        $('button').cafevTooltip({ position: 'auto bottom' });
        $('input').cafevTooltip({ position: 'auto bottom' });
        $('label').cafevTooltip({ position: 'auto bottom' });

        if (globalState.toolTips) {
          $.fn.cafevTooltip.enable();
        } else {
          $.fn.cafevTooltip.disable();
        }
      },
      close(event, ui) {
        $.fn.cafevTooltip.remove();
        $(this).dialog('destroy').remove();
      },
    });
  });
};

const updateThreads = function(data) {
  const blogThreads = $('#blogthreads');
  blogThreads.html(data.content);
  popupMessages();
  avatar();
  return true;
};

const documentReady = function() {

  CAFEVDB.addReadyCallback(function() {

    $('#blogeditform').submit(function() { return false; });

    $('#blogform #blognewentry').click(function(event) {
      event.preventDefault();
      const post = $('#blogform').serializeArray();
      $.post(generateUrl('blog/editentry'), post)
        .fail(function(xhr, status, errorThrown) {
          const message = Ajax.failMessage(xhr, status, errorThrown);
          Dialogs.alert(message, t('cafevdb', 'Error'));
        })
        .done(editWindow);
      return false;
    });

    // Use delegate handlers for dynamic content
    const blogThreads = $('#blogthreads');

    blogThreads.on(
      'click',
      '#blogentryactions button.reply',
      function(event) {
        event.preventDefault();
        $.post(generateUrl('blog/editentry'), {
          blogId: -1,
          inReplyTo: $(this).val(),
        })
          .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t('cafevdb', 'Error'));
          })
          .done(editWindow);
        return false;
      });

    blogThreads.on(
      'click',
      '#blogentryactions button.edit',
      function(event) {
        event.preventDefault();
        $.post(generateUrl('blog/editentry'), {
          blogId: $(this).val(),
          inReplyTo: -1,
        })
          .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t('cafevdb', 'Error'));
          })
          .done(editWindow);
        return false;
      });

    blogThreads.on(
      'click',
      '#blogentryactions button.delete',
      function(event) {
        event.preventDefault();
        const blogId = $(this).val();
        Dialogs.confirm(
          t('cafevdb', 'The entire message thread will be deleted if you press `Yes\''),
          t('cafevdb', 'Really delete the entry?'),
          function(decision) {
            if (decision) {
              const action = 'delete';
              $.post(generateUrl('blog/action/' + action), { blogId })
	        .fail(function(xhr, status, errorThrown) {
                  const message = Ajax.failMessage(xhr, status, errorThrown);
                  Dialogs.alert(message, t('cafevdb', 'Error'));
                })
                .done(updateThreads);
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
        const id = $(this).val();
        const prio = $('#blogpriority' + id).val();
        const action = 'modify';
        $.post(generateUrl('blog/action/' + action), {
          content: '',
          blogId: id,
          priority: +prio + 1,
          popup: false,
          inReplyTo: -1,
        })
	  .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t('cafevdb', 'Error'));
          })
          .done(updateThreads);
        return false;
      });

    blogThreads.on(
      'click',
      '#blogentryactions button.lower',
      function(event) {
        event.preventDefault();
        const id = $(this).val();
        const prio = $('#blogpriority' + id).val();
        const action = 'modify';
        $.post(generateUrl('blog/action/' + action), {
          content: '',
          blogId: id,
          priority: +prio - 1,
          popup: false,
          inReplyTo: -1,
        })
	  .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t('cafevdb', 'Error'));
          })
          .done(updateThreads);
        return false;
      });

    popupMessages(); // annoy people
    avatar(); // display avatars

  });

};

export {
  documentReady,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

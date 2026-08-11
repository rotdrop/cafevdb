/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import type { BlogResponse } from '../../build/ts-types/php-modules/Controller/DTO.ts';

import { translate as t } from '@nextcloud/l10n';
import { EnumBlogAction } from '../../build/ts-types/php-modules/Controller.ts';
import {
  BASE_PATH,
  END_POINT_ACTION,
  END_POINT_EDIT,
} from '../../build/ts-types/php-modules/Controller/BlogController.ts';
import { RESIZE_TARGET } from '../../build/ts-types/php-modules/Controller/CssClasses.ts';
import generateAppUrl from '../toolkit/util/generate-url.ts';
import * as Ajax from './ajax.ts';
import * as CAFEVDB from './cafevdb.ts';
import * as DialogUtils from './dialog-utils.ts';
import * as Dialogs from './dialogs.ts';
import { appName, globalState } from './globals.ts';
import $ from './jquery.ts';
import * as WysiwygEditor from './wysiwyg-editor.ts';

import '../legacy/nextcloud/jquery/avatar.js';

require('blog.scss');

const BlogState: Partial<BlogResponse> = {
  author: 'unknown',
  blogId: -1,
  inReplyTo: -1,
  content: '',
  priority: false,
  popup: false,
  reader: '',
};

const popupPosition = {
  my: 'center top+5%',
  at: 'center top',
  of: '#app-content, #app-content-vue',
  offset: '0 0',
};

/** @param data TBD. */
function editWindow(data: BlogResponse) {
  // @todo: should not be necessary, just attach to body without first
  // injectin the HTML content.
  $('#dialog_holder').html(data.content);

  BlogState.author = data.author;
  BlogState.blogId = data.blogId;
  BlogState.inReplyTo = data.inReplyTo;
  BlogState.text = data.text;
  BlogState.priority = data.priority;
  BlogState.popup = data.popup;
  BlogState.reader = data.reader;

  const $dialogHolder = $('#blogedit');
  $dialogHolder.cafevDialog({
    title: t(appName, 'Edit Blog Entry'),
    dialogClass: `blog-edit-dialog custom-close ${RESIZE_TARGET}`,
    modal: true,
    closeOnEscape: false,
    position: popupPosition,
    width: 'auto',
    height: 'auto',
    resizable: false,
    open() {
      const $dialogWidget = $dialogHolder.dialog('widget');

      DialogUtils.customCloseButton($dialogHolder, function(event, container) {
        event.preventDefault();
        const cancelButton = container.find('#blogcancel').first();
        if (cancelButton.length > 0) {
          event.stopImmediatePropagation();
          cancelButton.trigger('click');
        }
        return false;
      });

      const resizeHandler = function() {
        $dialogHolder.dialog('option', 'height', 'auto');
        $dialogHolder.dialog('option', 'width', 'auto');
        let newHeight = $dialogWidget.height()! -
            $dialogWidget.find('.ui-dialog-titlebar').outerHeight()!;
        newHeight -= $dialogHolder.outerHeight(false)! - $dialogHolder.height()!;
        $dialogHolder.height(newHeight);
      };

      $('.ui-dialog-titlebar-close').hide();

      $dialogWidget.find('button, input, label').cafevTooltip({ position: 'auto bottom' });

      if (globalState.toolTipsEnabled) {
        $.fn.cafevTooltip.enable();
      } else {
        $.fn.cafevTooltip.disable();
      }

      $('#blogedit #blogcancel').on('click', cancel);
      $('#blogedit #blogsubmit').on('click', submit);

      $('#blogtextarea').val(BlogState.text ?? '');

      // $('#blogtextarea').tinymce(myTinyMCE.config);
      // $('#blogtextarea').ckeditor(function() {}, {enterMode:CKEDITOR.ENTER_P});
      WysiwygEditor.addEditor('#blogtextarea', function() {
        $(self).dialog('option', 'position', popupPosition);
      });

      $dialogHolder.on('resize. ' + appName, resizeHandler);
    },
    close() {
      // $('#blogtextarea').tinymce().remove();
      // $('#blogtextarea').ckeditor().remove();
      $.fn.cafevTooltip.remove();
      WysiwygEditor.removeEditor('#blogtextarea');
      $(this).dialog('destroy').remove();
    },
  });
  return true;
}

/** @param event TBD. */
function cancel(event: JQuery.ClickEvent) {
  event.preventDefault();
  // $('#blogtextarea').tinymce().save();
  if ($('#blogtextarea').val() === BlogState.text) {
    $('#blogedit').dialog('close').remove();
  } else {
    Dialogs.confirm(
      t(appName, 'The message content has been changed and will be lost if you press `Yes\''),
      t(appName, 'Really cancel current entry?'),
      function(decision) {
        if (decision) {
          $('#blogedit').dialog('close').remove();
        }
      },
      true,
    );
  }
  return false;
}

/** @param event TBD. */
function submit(event: JQuery.ClickEvent) {
  event.preventDefault();
  // $('#blogtextarea').tinymce().save();
  let popupValue = 0;
  if ($('#blogpopupset').prop('checked')) {
    popupValue = 1;
  } else if ($('#blogpopupclear').prop('checked')) {
    popupValue = -1;
  }
  let clearReaderValue: number;
  if ($('#blogreaderclear').prop('checked')) {
    clearReaderValue = 1;
  } else {
    clearReaderValue = 0;
  }

  console.info('GLOBAL', BlogState);
  const action = (BlogState.blogId ?? -1) > 0 ? EnumBlogAction.MODIFY : EnumBlogAction.CREATE;
  $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_ACTION}/${action}`), {
    blogId: BlogState.blogId,
    inReplyTo: BlogState.inReplyTo,
    content: $('#blogtextarea').val(),
    priority: $('#blogpriority').val(),
    popup: popupValue,
    clearReader: clearReaderValue,
  })
    .fail(function(xhr, status, errorThrown) {
      const message = Ajax.failMessage(xhr, status, errorThrown);
      Dialogs.alert(message, t(appName, 'Error'));
    })
    .done(function(data) {
      $('#blogedit').dialog('close').remove();
      updateThreads(data);
      return true;
    });
  return false;
}

const avatar = function() {
  const blogThreads = $('#blogthreads');

  blogThreads.find('span.avatar').each(function() {
    const $self = $(this);
    const author = $self.data('author');
    const size = $self.data('size');
    $self.avatar(author, size);

  });
};

const popupMessages = function() {
  const blogThreads = $('#blogthreads');

  blogThreads.find('div.blogentrypopup').each(function() {
    let thisBlogId = $(this).find('input.blogentrypopupid').val();
    if (!thisBlogId) {
      thisBlogId = -1;
    }
    $(this).cafevDialog({
      dialogClass: 'no-close blog-popup-dialog',
      title: t(appName, 'One-time Blog Popup'),
      modal: true,
      closeOnEscape: false,
      position: popupPosition,
      width: 'auto',
      height: 'auto',
      resizable: false,
      buttons: [
        {
          text: t(appName, 'I have read this popup, please bother me no more!'),
          title: t(appName, 'Mark this popup as read; the popup will not show up again.'),
          click() {
            $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_ACTION}/${EnumBlogAction.MARK_READ}`), { blogId: thisBlogId })
              .fail(function(xhr, status, errorThrown) {
                const message = Ajax.failMessage(xhr, status, errorThrown);
                Dialogs.alert(message, t(appName, 'Error'));
              })
              .done(function(_data) {
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

        if (globalState.toolTipsEnabled) {
          $.fn.cafevTooltip.enable();
        } else {
          $.fn.cafevTooltip.disable();
        }
      },
      close(_event, _ui) {
        $.fn.cafevTooltip.remove();
        $(this).dialog('destroy').remove();
      },
    });
  });
};

/** @param data TBD. */
function updateThreads(data: BlogResponse) {
  const blogThreads = $('#blogthreads');
  blogThreads.html(data.content);
  popupMessages();
  avatar();
  return true;
}

const documentReady = function() {

  CAFEVDB.addReadyCallback(async () => {

    $('#blogeditform').on('submit', function() { return false; });

    $('#blogform #blognewentry').on('click', function(event) {
      event.preventDefault();
      const post = $('#blogform').serializeArray();
      $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_EDIT}`), post)
        .fail(function(xhr, status, errorThrown) {
          const message = Ajax.failMessage(xhr, status, errorThrown);
          Dialogs.alert(message, t(appName, 'Error'));
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
        $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_EDIT}`), {
          blogId: -1,
          inReplyTo: $(this).val(),
        })
          .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t(appName, 'Error'));
          })
          .done(editWindow);
        return false;
      },
    );

    blogThreads.on(
      'click',
      '#blogentryactions button.edit',
      function(event) {
        event.preventDefault();
        $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_EDIT}`), {
          blogId: $(this).val(),
          inReplyTo: -1,
        })
          .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t(appName, 'Error'));
          })
          .done(editWindow);
        return false;
      },
    );

    blogThreads.on(
      'click',
      '#blogentryactions button.delete',
      function(event) {
        event.preventDefault();
        const blogId = $(this).val();
        Dialogs.confirm(
          t(appName, 'The entire message thread will be deleted if you press `Yes\''),
          t(appName, 'Really delete the entry?'),
          function(decision) {
            if (decision) {
              $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_ACTION}/${EnumBlogAction.DELETE}`), { blogId })
                .fail(function(xhr, status, errorThrown) {
                  const message = Ajax.failMessage(xhr, status, errorThrown);
                  Dialogs.alert(message, t(appName, 'Error'));
                })
                .done(updateThreads);
            }
          },
          true,
        );
        return false;
      },
    );

    blogThreads.on(
      'click',
      '#blogentryactions button.raise',
      function(event) {
        event.preventDefault();
        const id = $(this).val();
        const prio = $('#blogpriority' + id).val() ?? 0;
        $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_ACTION}/${EnumBlogAction.MODIFY}`), {
          content: '',
          blogId: id,
          priority: +prio + 1,
          popup: false,
          inReplyTo: -1,
        })
          .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t(appName, 'Error'));
          })
          .done(updateThreads);
        return false;
      },
    );

    blogThreads.on(
      'click',
      '#blogentryactions button.lower',
      function(event) {
        event.preventDefault();
        const id = $(this).val();
        const prio = $('#blogpriority' + id).val() ?? 0;
        $.post(generateAppUrl(`${BASE_PATH}/${END_POINT_ACTION}/${EnumBlogAction.MODIFY}`), {
          content: '',
          blogId: id,
          priority: +prio - 1,
          popup: false,
          inReplyTo: -1,
        })
          .fail(function(xhr, status, errorThrown) {
            const message = Ajax.failMessage(xhr, status, errorThrown);
            Dialogs.alert(message, t(appName, 'Error'));
          })
          .done(updateThreads);
        return false;
      },
    );

    popupMessages(); // annoy people
    avatar(); // display avatars

  });

};

export {
  documentReady,
};

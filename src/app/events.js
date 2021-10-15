/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, $ } from './globals.js';
import fileDownload from './file-download.js';
import generateUrl from './generate-url.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Legacy from '../legacy.js';
import * as Email from './email.js';
import * as DialogUtils from './dialog-utils.js';
import { makePlaceholder as selectPlaceholder } from './select-utils.js';
import { token as pmeToken } from './pme-selectors.js';
import { revertRows as revertTableRows } from './table-utils.js';
import modalizer from './modalizer.js';

require('jquery-ui/ui/widgets/accordion');

require('events.scss');

const Events = globalState.Events = {
  projectId: -1,
  projectName: '',
  events: { /* nothing */ },
  confirmText: {
    delete: t(appName, 'Do you really want to delete this event?'),
    detach: t(appName, 'Do you really want to detach this event from the current project?'),
    select: '',
    deselect: '',
  },
};

const accordionList = function(selector, $dialogHolder) {
  const $element = $dialogHolder.find(selector);
  if ($element.find('tr').length <= 10) {
    // FIXME: this should perhaps be decided in the template or elsewhere.
    return;
  }
  $element.accordion({
    heightStyle: 'content',
    collapsible: true,
    animate: 0,
    active: false,
    beforeActivate(event, ui) {

      if ($(ui.newHeader).hasClass('empty')) {
        return false;
      }

      return true;
    },
    activate(event, ui) {
      adjustSize($dialogHolder);
    },
    create(event, ui) {
      adjustSize($dialogHolder);
    },
  });
};

const init = function(htmlContent, textStatus, request) {

  globalState.Events.projectId = parseInt(request.getResponseHeader('X-' + appName + '-project-id'));
  globalState.Events.projectName = request.getResponseHeader('X-' + appName + '-project-name');

  // var popup = $('#events').cafevDialog({
  const dialogContent = $(htmlContent);
  dialogContent.cafevDialog({
    dialogClass: 'cafevdb-project-events no-scroll',
    position: {
      my: 'middle middle',
      at: 'middle top+50%',
      of: '#app-content',
    },
    width: 'auto', // 510,
    height: 'auto',
    resizable: false,
    open() {
      // $.fn.cafevTooltip.remove();
      const $dialogHolder = $(this);

      accordionList('.event-list-container', $dialogHolder);

      // revertTableRows($dialogHolder.find('table.listing'));

      /* Adjust dimensions to do proper scrolling. */
      adjustSize($dialogHolder);

      const eventForm = $dialogHolder.find('#eventlistform');
      const eventMenu = eventForm.find('select.event-menu');

      // style the menu with chosen
      eventMenu.chosen({
        inherit_select_classes: true,
        disable_search: true,
        width: '10em',
      });

      selectPlaceholder(eventMenu);

      DialogUtils.toBackButton($(this));

      $.fn.cafevTooltip.remove();
      CAFEVDB.toolTipsInit('#events');

      eventMenu.on('change', function(event) {
        event.preventDefault();

        if ($('#event').dialog('isOpen') === true) {
          $('#event').dialog('close');
          return false;
        }

        $('#events #debug').hide();
        $('#events #debug').empty();

        const post = eventForm.serializeArray();
        const eventType = eventMenu.find('option:selected').val();
        post.push({ name: 'eventKind', value: eventType });

        $('#dialog_holder').load(
          generateUrl('legacy/events/forms/new'),
          post,
          function(response, textStatus, xhr) {
            if (textStatus === 'success') {
              Legacy.Calendar.UI.startEventDialog();
              return;
            }
            Ajax.handleError(xhr, textStatus, xhr.status);
          });

        eventMenu.find('option').prop('selected', false);
        $.fn.cafevTooltip.remove();

        eventMenu.trigger('chosen:updated');

        return false;
      });

      eventForm
        .off('click', ':button')
        .on('click', ':button', buttonClick);

      eventForm
        .off('click', 'td.eventdata')
        .on('click', 'td.eventdata', function(event) {
          $(this).parent().find(':button.edit').trigger('click');
          return false;
        });

      $dialogHolder
        .off('cafevdb:events_changed')
        .on('cafevdb:events_changed', function(event, events) {
          $.post(
            generateUrl('projects/events/redisplay'),
            {
              projectId: Events.projectId,
              projectName: Events.projectName,
              eventSelect: events,
            })
            .fail(Ajax.handleError)
            .done(relist);
          return false;
        });
      $dialogHolder
        .off('change', ['input', 'email', pmeToken('misc'), pmeToken('check')].join('.'))
        .on('change', ['input', 'email', pmeToken('misc'), pmeToken('check')].join('.'), function(event) {
          updateEmailForm();
          return false;
        });
    },
    close(event, ui) {
      $.fn.cafevTooltip.remove();
      $('#event').dialog('close');
      $(this).dialog('destroy').remove();

      // Remove modal plane if appropriate
      modalizer(false);
    },
  });
};

const updateEmailForm = function(post, emailFormDialog) {
  if (typeof emailFormDialog === 'undefined') {
    emailFormDialog = $('div#emailformdialog');
  }
  if (emailFormDialog.length > 0) {
    // Email dialog already open. We trigger a custom event to
    // propagte the data. We only submit the event ids.
    if (typeof post === 'undefined') {
      post = $('#eventlistform').serializeArray();
    }
    const events = [];
    $.each(post, function(i, param) {
      if (param.name === 'eventSelect[]') {
        events.push(param.value);
      }
    });
    emailFormDialog.trigger('cafevdb:events_changed', [events]);
  }
};

const adjustSize = function($dialogHolder) {
  const $dialogWidget = $dialogHolder.dialog('widget');
  const $controls = $dialogHolder.find('.eventcontrols');
  const $scrollElement = $dialogHolder.find('.scroller');
  const $dimensionElement = $dialogHolder.find('.size-holder');

  const top = $scrollElement.position().top
        + $dialogHolder.position().top;
  const width = Math.max(
    $dimensionElement.outerWidth(true),
    $controls.outerWidth(true)
  );
  const height = $dimensionElement.outerHeight(true);

  // const width = dialogWidget.width();
  // const height = dialogWidget.height();

  $dialogWidget.innerHeight(top + height);
  $dialogWidget.innerWidth(width);

  if ($dimensionElement.outerHeight() <= $scrollElement.innerHeight()) {
    $scrollElement.css('overflow', 'hidden');
  } else {
    $scrollElement.css('overflow', 'auto');
  }

  if ($scrollElement.hasVerticalScrollbar()) {
    const scroll = $scrollElement.verticalScrollbarWidth();
    if (scroll > 0) {
      $dialogWidget.innerWidth(width + scroll);
    }
  }

  if ($scrollElement.hasHorizontalScrollbar()) {
    const scroll = $scrollElement.horizontalScrollbarHeight();
    if (scroll > 0) {
      $dialogWidget.innerHeight(top + height + scroll);
    }
  }

};

const relist = function(htmlContent, textStatus, xhr) {

  // globalState.Events.projectId = parseInt(xhr.getResponseHeader('X-' + appName + '-project-id'));
  // globalState.Events.projectName = xhr.getResponseHeader('X-' + appName + '-project-name');

  const $dialogHolder = $('#events');
  const listing = $dialogHolder.find('#eventlistholder');
  listing.html(htmlContent);

  accordionList('.event-list-container', $dialogHolder);

  adjustSize($dialogHolder);

  $.fn.cafevTooltip.remove();

  CAFEVDB.toolTipsInit(listing);

  updateEmailForm();
};

const redisplay = function() {
  const post = $('#eventlistform').serializeArray();

  $.post(generateUrl('projects/events/redisplay'), post)
    .fail(Ajax.handleError)
    .done(relist);
};

const buttonClick = function(event) {
  event.preventDefault();

  const evntdlgopen = $('#event').dialog('isOpen');

  const post = $('#eventlistform').serializeArray();

  if (evntdlgopen === true) {
    // TODO: maybe save event
    $('#event').dialog('close');
    return false;
  }

  $('#events #debug').hide();
  $('#events #debug').empty();

  const name = $(this).attr('name');

  if (name === 'edit') {

    // Edit existing event
    post.push({ name: 'uri', value: $(this).val() });
    post.push({ name: 'calendarid', value: $(this).data('calendarId') });
    $('#dialog_holder').load(
      generateUrl('legacy/events/forms/edit'),
      post,
      function(response, textStatus, xhr) {
        if (textStatus === 'success') {
          Legacy.Calendar.UI.startEventDialog();
          return;
        }
        Ajax.handleError(xhr, textStatus, xhr.status);
      });
    return false;
  } else if (name === 'delete'
             || name === 'detach'
             || name === 'select'
             || name === 'deselect') {
    // Execute the task and redisplay the event list.

    post.push({ name: 'EventURI', value: $(this).val() });

    const really = globalState.Events.confirmText[name];
    if (really !== undefined && really !== '') {
      // Attention: dialogs do not block, so the action needs to be
      // wrapped into the callback.
      OC.dialogs.confirm(
        really,
        t(appName, 'Really delete?'),
        function(decision) {
          if (decision) {
            $.post(generateUrl('projects/events/' + name), post)
              .fail(Ajax.handleError)
              .done(relist);
          }
        },
        true);
    } else {
      $.post(generateUrl('projects/events/' + name), post)
        .fail(Ajax.handleError)
        .done(relist);
    }
    return false;
  } else if (name === 'sendmail') {

    const emailFormDialog = $('div#emailformdialog');
    if (emailFormDialog.length === 0) {
      // If the email-form is not open, then open it :)
      Email.emailFormPopup(post);
    } else {
      // Email dialog already open. We trigger a custom event to
      // propagte the data. We only submit the event ids.
      updateEmailForm(post, emailFormDialog);

      // and we move the email-dialog to front
      emailFormDialog.dialog('moveToTop');
    }

  } else if (name === 'download') {

    fileDownload(
      'projects/events/download',
      post,
      t(appName, 'Unable to download calendar events.')
    );

    return false;
  }

  return false;
};

export {
  init,
  redisplay,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

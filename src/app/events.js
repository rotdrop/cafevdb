/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName } from './globals.js';
import generalUrl from './generate-url.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Legacy from '../legacy.js';

globalState.Events = {
  projectId: -1,
  projectName: '',
  Events: { /* nothing */ },
  confirmText: {
    'delete': t(appName, 'Do you really want to delete this event?'),
    'detach': t(appName, 'Do you really want to detach this event from the current project?'),
    select: '',
    deselect: '',
  },
};

const init = function(htmlContent, textStatus, request) {

  globalState.Events.projectId = request.getResponseHeader('X-' + appName + '-project-id');
  globalState.Events.projectName = request.getResponseHeader('X-' + appName + '-project-name');

  //var popup = $('#events').cafevDialog({
  var dialogContent = $(htmlContent);
  var popup = dialogContent.cafevDialog({
    dialogClass: 'cafevdb-project-events no-scroll',
    position: {
      my: "middle middle",
      at: "middle top+50%",
      of: "#app-content"
    },
    width : "auto", //510,
    height: "auto",
    resizable: false,
    open  : function(){
      //$.fn.cafevTooltip.remove();
      var dialogHolder = $(this);
      var dialogWidget = dialogHolder.dialog('widget');

      /* Adjust dimensions to do proper scrolling. */
      // @TODO this really needs to be reworked
      // adjustSize(dialogHolder, dialogWidget);

      var eventForm = dialogHolder.find('#eventlistform');
      var eventMenu = eventForm.find('select.event-menu');

      // style the menu with chosen
      eventMenu.chosen({
        inherit_select_classes:true,
        disable_search:true,
        width:'10em'
      });

      CAFEVDB.fixupNoChosenMenu(eventMenu);

      CAFEVDB.dialogToBackButton($(this));

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

        var post = eventForm.serializeArray();
        var eventType = eventMenu.find('option:selected').val();
        post.push({ name: 'eventKind', value: eventType });

        $('#dialog_holder').load(
          generateUrl('legacy/events/forms/new'),
          post,
          function(response, textStatus, xhr) {
            if (textStatus == 'success') {
              Legacy.Calendar.UI.startEventDialog();
              return;
            }
            Ajax.handleError(xhr, textStatus, xhr.status);
          });

        eventMenu.find('option').removeAttr('selected');
        $.fn.cafevTooltip.remove();

        eventMenu.trigger('chosen:updated');

        return false;
      });

      eventForm.
        off('click', ':button').
        on('click', ':button', buttonClick);

      eventForm.
        off('click', 'td.eventdata').
	on('click', 'td.eventdata', function(event) {
          $(this).parent().find(':button.edit').trigger('click');
          return false;
        });

      dialogHolder.
        off('cafevdb:events_changed').
        on('cafevdb:events_changed', function(event, events) {
          // @TODO
          $.post(
            generateUrl('projects/events/redisplay'),
            { ProjectId: Events.projectId,
              ProjectName: Events.projectName,
              EventSelect: events })
	    .fail(Ajax.handleError)
            .done(relist);
          return false;
        });
      dialogHolder.
        off('change', 'input.email-check').
        on('change', 'input.email-check', function(event) {
          updateEmailForm();
          return false;
        });
    },
    close : function(event, ui) {
      $.fn.cafevTooltip.remove();
      $('#event').dialog('close');
      $(this).dialog('destroy').remove();

      // Remove modal plane if appropriate
      CAFEVDB.modalizer(false);
    }
  });
};

const updateEmailForm = function(post, emailFormDialog) {
  if (typeof emailFormDialog == 'undefined') {
    emailFormDialog = $('div#emailformdialog');
  }
  if (emailFormDialog.length > 0) {
    // Email dialog already open. We trigger a custom event to
    // propagte the data. We only submit the event ids.
    if (typeof post == 'undefined') {
      post = $('#eventlistform').serializeArray();
    }
    var events = [];
    $.each(post, function (i, param) {
      if (param.name == 'EventSelect[]') {
        events.push(param.value);
      }
    });
    emailFormDialog.trigger('cafevdb:events_changed', [ events ]);
  }
};

const adjustSize = function(dialogHolder, dialogWidget) {
  var dimensionElement = dialogHolder.find('.size-holder');
  var scrollElement = dialogHolder.find('.scroller');
  var top    = scrollElement.position().top;
  var width  = dimensionElement.outerWidth(true);
  var height = dimensionElement.outerHeight(true);
  dialogWidget.innerHeight(top+height);
  dialogWidget.innerWidth(width);

  var needScroll = scrollElement.needScrollbars();
  if (!needScroll.horizontal) {
    scrollElement.addClass('inhibit-overflow-x');
  }
  if (!needScroll.vertical) {
    scrollElement.addClass('inhibit-overflow-y');
  }

  var scroll;
  scroll = scrollElement.horizontalScrollbarHeight();
  if (scroll > 0) {
    dialogWidget.innerHeight(top+height+scroll);
  }
  scroll = scrollElement.verticalScrollbarWidth();
  if (scroll > 0) {
    dialogWidget.innerWidth(width+scroll);
  }
};

const relist = function(htmlContent, textStatus, xhr) {

  //globalState.Events.projectId = xhr.getResponseHeader('X-' + appName + '-project-id');
  //globalState.Events.projectName = xhr.getResponseHeader('X-' + appName + '-project-name');

  const events = $('#events');
  const listing = events.find('#eventlistholder');
  listing.html(htmlContent);

  /* Adjust dimensions to do proper scrolling. */
  const dialogWidget = events.dialog('widget');
  //adjustSize(events, dialogWidget);

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

  if (evntdlgopen === true){
    // TODO: maybe save event
    $('#event').dialog('close');
    return false;
  }

  $('#events #debug').hide();
  $('#events #debug').empty();

  const name = $(this).attr('name');

  if (name == 'edit') {

    // Edit existing event
    post.push({ name: 'uri', value:  $(this).val()});
    post.push({ name: 'calendarid', value: $(this).data('calendarId')});
    $('#dialog_holder').load(
      generateUrl('legacy/events/forms/edit'),
      post,
      function(response, textStatus, xhr) {
        if (textStatus == 'success') {
          Legacy.Calendar.UI.startEventDialog();
          return;
        }
        Ajax.handleError(xhr, textStatus, xhr.status);
      });
    return false;
  } else if (name == 'delete' ||
             name == 'detach' ||
             name == 'select' ||
             name == 'deselect') {
    // Execute the task and redisplay the event list.

    post.push({ name: 'EventURI', value: $(this).val() });

    const really = globalState.Events.confirmText[name];
    console.info('really', name, really);
    if (really !== undefined && really != '') {
      // Attention: dialogs do not block, so the action needs to be
      // wrapped into the callback.
      OC.dialogs.confirm(
        really,
        t('cafevdb', 'Really delete?'),
        function (decision) {
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
  } else if (name == 'sendmail') {

    var emailFormDialog = $('div#emailformdialog');
    if (emailFormDialog.length == 0) {
      // If the email-form is not open, then open it :)
      // @TODO reenable
      // CAFEVDB.Email.emailFormPopup(post);
    } else {
      // Email dialog already open. We trigger a custom event to
      // propagte the data. We only submit the event ids.
      updateEmailForm(post, emailFormDialog);

      // and we move the email-dialog to front
      emailFormDialog.dialog('moveToTop');
    }

  } else if (name == 'download') {

    const cookieValue = CAFEVDB.makeId();
    const cookieName = appName + '_' + 'project_events_download'
    post.push({ name: 'DownloadCookieName', value: cookieName });
    post.push({ name: 'DownloadCookieValue', value: cookieValue });

    $.fileDownload(
      generateUrl('projects/events/download'), {
        httpMethod: 'POST',
        data: post,
        cookieName:  cookieName,
        cookieValue: cookieValue,
        cookiePath: oc_webroot,
      })
      .fail(function (responseHtml, url) {
        Dialogs.alert(t('cafevdb', 'Unable to download calendar events: {response}',
                        { 'response': responseHtml }),
                      t('cafevdb', 'Error'),
                      function () {},
                      true, true);
      })
      .done(function (url) { console.info('DONE downloading', url); });
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

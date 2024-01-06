/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2023 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { globalState, appName, $ } from './globals.js';
import fileDownload from './file-download.js';
import generateUrl from './generate-url.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import * as Legacy from '../legacy.js';
import * as Email from './email.js';
import * as DialogUtils from './dialog-utils.js';
import { token as pmeToken } from './pme-selectors.js';
import { revertRows as revertTableRows } from './table-utils.js';
import { busyIcon as pageBusyIcon } from './page.js';
import modalizer from './modalizer.js';
import { close as closeActionMenus } from './action-menu.js';
import { handleMenu as handleUserManualMenu } from './user-manual.js';

require('jquery-ui/ui/widgets/accordion');

require('events.scss');

globalState.Events = {
  projectId: -1,
  projectName: '',
};

const confirmText = {
  delete: t(appName, 'Do you really want to delete this event?'),
  detach: t(appName, 'Do you really want to detach this event from the current project?'),
  select: '',
  deselect: '',
  absenceField: '',
};

const accordionList = function(selector, $dialogHolder) {
  const $element = $dialogHolder.find(selector);
  if ($element.find('tr').length <= 10) {
    // FIXME: this should perhaps be decided in the template or elsewhere.
    return false;
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
  return true;
};

const setLoadingIndicator = function(state) {
  pageBusyIcon(state);
  $('#projectevents-reload').toggleClass('loading', state);
  if (!state) {
    closeActionMenus();
  }
};

const handleError = function(xhr, textStatus, errorThrown) {
  Ajax.handleError(xhr, textStatus, errorThrown, () => setLoadingIndicator(false));
};

const init = function(htmlContent, textStatus, request, afterInit) {

  afterInit = afterInit || function() {};

  globalState.Events.projectId = parseInt(request.getResponseHeader('X-' + appName + '-project-id'));
  globalState.Events.projectName = request.getResponseHeader('X-' + appName + '-project-name');

  const dialogContent = $(htmlContent);
  dialogContent.cafevDialog({
    dialogClass: 'cafevdb-project-events no-scroll',
    position: {
      my: 'center top',
      at: 'center top+50',
      of: '#app-content',
    },
    width: 'auto', // 510,
    height: 'auto',
    resizable: false,
    open() {
      // $.fn.cafevTooltip.remove();
      const $dialogHolder = $(this);

      $dialogHolder.find('table.listing').each(function(index) {
        // sort newest-first for large numbers of events.
        const $table = $(this);
        if ($table.find('tr').length > 10) {
          revertTableRows($table);
        }
      });

      if (!accordionList('.event-list-container', $dialogHolder)) {
        /* Adjust dimensions to do proper scrolling. */
        adjustSize($dialogHolder);
      }

      const eventForm = $dialogHolder.find('#eventlistform');
      const eventMenu = eventForm.find('.new-event-dropdown');

      DialogUtils.toBackButton($(this));

      $.fn.cafevTooltip.remove();
      CAFEVDB.toolTipsInit('#events');

      eventForm
        .off('click', '.project-event-manual .action-menu-toggle')
        .on('click', '.project-event-manual .action-menu-toggle', function(event) {
          return false;
        });
      handleUserManualMenu($dialogHolder);

      eventMenu.on('click', '.menu-item', function(event) {
        const $this = $(this);

        if ($('#event').dialog('isOpen') === true) {
          $('#event').dialog('close');
          return false;
        }

        const post = eventForm.serializeArray();
        const eventType = $this.data('operation');
        post.push({ name: 'eventKind', value: eventType });

        $('#dialog_holder').load(
          generateUrl('legacy/events/forms/new'),
          post,
          function(response, textStatus, xhr) {
            if (textStatus === 'success') {
              Legacy.Calendar.UI.startEventDialog(() => setLoadingIndicator(false));
              return;
            }
            handleError(xhr, textStatus, xhr.status);
          });

        $.fn.cafevTooltip.remove();

        return false;
      });

      const eventActionSelector = ':button:not(.action-menu-toggle), .event-action:not(.event-action-select, .event-action-scope, .event-action-absence-field)';
      eventForm
        .off('click', eventActionSelector)
        .on('click', eventActionSelector, eventAction);

      const contextMenuRowSelector = 'tr.projectevents';
      eventForm
        .off('contextmenu', contextMenuRowSelector)
        .on('contextmenu', contextMenuRowSelector, function(event) {
          if (event.ctrlKey) {
            return; // let the user see the normal context menu
          }
          if ($(event.target).closest('.dropdown-container').length > 0) {
            return; // ignore right click in drop-down menu
          }
          const $row = $(this);
          const $menu = $row.find('.dropdown-container.event-actions');

          if ($menu.length === 0) {
            return;
          }

          const $menuToggle = $menu.find('.action-menu-toggle');
          const $menuContent = $menu.find('.dropdown-content');

          event.preventDefault();
          event.stopImmediatePropagation();

          $menuContent.css({
            position: 'fixed',
            left: event.originalEvent.clientX,
            top: event.originalEvent.clientY,
          });

          $menu.addClass('context-menu');
          $menuToggle.trigger('click');

          return false;
        });

      eventForm
        .off('click', 'td.eventdata')
        .on('click', 'td.eventdata', function(event) {
          $(this).parent().find('.event-actions .event-action-edit').trigger('click');
          return false;
        });

      const eventUidSelector = 'tr.event-is-repeating td.event-uid';
      eventForm
        .off('click', eventUidSelector)
        .on('click', eventUidSelector, function(event) {
          const $this = $(this);
          const $row = $this.closest('tr');
          $row.find('input.scope-radio[value="series"]').prop('checked', true).trigger('change');
          const $emailCheck = $row.find('input.email-check');
          $emailCheck.prop('checked', !$emailCheck.prop('checked')).trigger('change');

          return false;
        });

      const eventSeriesUidSelector = 'tr.event-has-cross-series-relations td.event-series-uid';
      eventForm
        .off('click', eventSeriesUidSelector)
        .on('click', eventSeriesUidSelector, function(event) {
          const $this = $(this);
          const $row = $this.closest('tr');
          $row.find('input.scope-radio[value="related"]').prop('checked', true).trigger('change');
          const $emailCheck = $row.find('input.email-check');
          $emailCheck.prop('checked', !$emailCheck.prop('checked')).trigger('change');

          return false;
        });

      eventForm
        .off('change', 'input.email-check')
        .on('change', 'input.email-check', emailSelection);

      eventForm
        .off('change', 'input.scope-radio')
        .on('change', 'input.scope-radio', scopeSelection);

      eventForm
        .off('change', 'input.absence-field-check')
        .on('change', 'input.absence-field-check', eventAction);

      $dialogHolder
        .off('cafevdb:events_changed')
        .on('cafevdb:events_changed', function(event, events, source) {
          $.post(
            generateUrl('projects/events/redisplay'),
            {
              projectId: globalState.Events.projectId,
              projectName: globalState.Events.projectName,
              eventSelect: events,
            })
            .fail(handleError)
            .done((htmlContent, textStatus, xhr) => {
              relist(htmlContent, textStatus, xhr, () => setLoadingIndicator(false));
            });
          return false;
        });
      $dialogHolder
        .off('change', ['input', 'email', pmeToken('misc'), pmeToken('check')].join('.'))
        .on('change', ['input', 'email', pmeToken('misc'), pmeToken('check')].join('.'), function(event) {
          updateEmailForm();
          return false;
        });
      $dialogHolder.dialog('moveToTop');
      afterInit();
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
    $controls.outerWidth(true),
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

const relist = function(htmlContent, textStatus, xhr, afterInit) {

  afterInit = afterInit || (() => {
    updateEmailForm();
    setLoadingIndicator(false);
  });

  const $dialogHolder = $('#events');
  const listing = $dialogHolder.find('#eventlistholder');
  listing.html(htmlContent);

  $dialogHolder.find('table.listing').each(function(index) {
    // sort newest-first for large numbers of events.
    const $table = $(this);
    if ($table.find('tr').length > 10) {
      revertTableRows($table);
    }
  });

  if (!accordionList('.event-list-container', $dialogHolder)) {
    adjustSize($dialogHolder);
  }

  $.fn.cafevTooltip.remove();

  CAFEVDB.toolTipsInit(listing);

  afterInit();
};

const redisplay = function() {
  const post = $('#eventlistform').serializeArray();

  $.post(generateUrl('projects/events/redisplay'), post)
    .fail(handleError)
    .done(relist);
};

const getRowScope = function($row, scope) {
  scope = scope || $row.find('.scope-radio:checked').val();

  switch (scope) {
  case 'single':
    break;
  case 'series':
    if ($row.hasClass('event-is-not-repeating')) {
      scope = 'single';
    }
    break;
  case 'related':
    if ($row.hasClass('event-has-no-cross-series-relations')) {
      scope = $row.hasClass('event-is-not-repeating') ? 'single' : 'series';
    }
    break;
  }

  return scope;
};

const emailSelection = function(event) {
  const $this = $(this);
  const $row = $this.closest('tr');
  const scope = getRowScope($row);

  switch (scope) {
  case 'single': {
    const eventUri = $row.data('uri');
    const recurrenceId = $row.data('recurrenceId');
    const selector = 'tr[data-uri="' + eventUri + '"][data-recurrence-id="' + recurrenceId + '"] input.email-check';
    $row.closest('.event-list-container').find(selector).prop('checked', $this.prop('checked'));
    break;
  }
  case 'series': {
    const eventUri = $row.data('uri');
    const selector = 'tr[data-uri="' + eventUri + '"] input.email-check';
    $row.closest('.event-list-container').find(selector).prop('checked', $this.prop('checked'));
    break;
  }
  case 'related': {
    const seriesUid = $row.data('seriesUid');
    const selector = 'tr[data-series-uid="' + seriesUid + '"] input.email-check';
    $row.closest('.event-list-container').find(selector).prop('checked', $this.prop('checked'));
    break;
  }
  }

  updateEmailForm();

  return false;
};

const scopeSelection = function(event) {
  const $this = $(this);
  const $row = $this.closest('tr');
  const scope = $this.val();

  // arguably one could try to adjust the selection on scope change ...

  $row.attr('data-action-scope', scope); // this line for CSS
  $row.data('actionScope', scope); // this line for consistency in jQuery

  // Synchronize the action scope. This essentially means that we
  // should just have one set of scope controls, but for now we to it
  // this way.

  $row.closest('.event-list-container').find('tr.projectevents').each(function() {
    const $row = $(this);
    const oldScope = $row.find('.scope-radio:checked').val();
    const rowScope = getRowScope($row, scope);

    if (oldScope !== rowScope) {
      $row.find('input.scope-radio[value="' + rowScope + '"]').prop('checked', true).trigger('change');
    }
  });

  return false;
};

const eventAction = function(event) {

  const evntdlgopen = $('#event').dialog('isOpen');

  const post = $('#eventlistform').serializeArray();

  if (evntdlgopen === true) {
    // TODO: maybe save event
    $('#event').dialog('close');
    return false;
  }

  setLoadingIndicator(true);
  const afterInit = () => setLoadingIndicator(false);

  const $this = $(this);
  const $row = $this.closest('tr');
  const rowData = $row.data() || {};
  const calendarId = rowData.calendarId;
  const uri = rowData.uri;
  // const recurrenceId = $row.data('recurrenceId');

  const name = $this.attr('name') || $this.data('operation');

  switch (name) {
  case 'calendar-app':
    // just let the browser follow the link
    afterInit();
    return true;
  case 'edit': {
    // Edit existing event. The legacy code does not allow
    // modifications of single instances in a series.
    post.push({ name: 'uri', value: uri });
    post.push({ name: 'calendarid', value: calendarId });
    $('#dialog_holder').load(
      generateUrl('legacy/events/forms/edit'),
      post,
      function(response, textStatus, xhr) {
        if (textStatus === 'success') {
          Legacy.Calendar.UI.startEventDialog(afterInit);
          return;
        }
        handleError(xhr, textStatus, xhr.status);
      });
    break;
  }
  case 'clone': {
    // Clone an existing event. The legacy code does not allow
    // modifications of single instances in a series.
    post.push({ name: 'uri', value: uri });
    post.push({ name: 'calendarid', value: calendarId });
    $('#dialog_holder').load(
      generateUrl('legacy/events/forms/clone'),
      post,
      function(response, textStatus, xhr) {
        if (textStatus === 'success') {
          Legacy.Calendar.UI.startEventDialog(afterInit);
          return;
        }
        handleError(xhr, textStatus, xhr.status);
      });
    break;
  }
  case 'absenceField':
    post.push({ name: 'enableAbsenceField', value: $this.prop('checked') });
    // fallthrough
  case 'select':
  case 'deselect':
  case 'delete':
  case 'detach': {
    // Execute the task and redisplay the event list.

    const rowJson = JSON.stringify(rowData);
    if (rowJson !== '{}') {
      post.push({ name: 'eventIdentifier', value: rowJson });
    }

    const really = confirmText[name];
    if (really !== undefined && really !== '') {
      // Attention: dialogs do not block, so the action needs to be
      // wrapped into the callback.
      OC.dialogs.confirm(
        really,
        t(appName, 'Really delete?'),
        function(decision) {
          if (decision) {
            $.post(generateUrl('projects/events/' + name), post)
              .fail(handleError)
              .done(relist);
          } else {
            afterInit();
          }
        },
        true);
    } else {
      $.post(generateUrl('projects/events/' + name), post)
        .fail(handleError)
        .done(relist);
    }
    break;
  }
  case 'sendmail': {
    const emailFormDialog = $('div#emailformdialog');
    if (emailFormDialog.length === 0) {
      // If the email-form is not open, then open it :)
      Email.emailFormPopup(post, undefined, undefined, afterInit);
    } else {
      // Email dialog already open. We trigger a custom event to
      // propagte the data. We only submit the event ids.
      updateEmailForm(post, emailFormDialog);

      // and we move the email-dialog to front
      emailFormDialog.dialog('moveToTop');
      afterInit();
    }
    break;
  }
  case 'download': {
    fileDownload(
      'projects/events/download',
      post, {
        always() {
          afterInit();
        },
        errorMessage(url, data) {
          t(appName, 'Unable to download calendar events.');
        },
      },
    );
    break;
  }
  case 'reload': {
    redisplay(afterInit);
    break;
  }
  default:
    afterInit();
    break;
  }

  return false;
};

export {
  init,
  redisplay,
};

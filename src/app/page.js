/* Orchestra member, musicion and project management application.
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

import { initialState, appName } from './config.js';
import { globalState, $ } from './globals.js';
import generateUrl from './generate-url.js';
import * as Notification from './notification.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';

globalState.Page = globalState.Page || { historyPosition: 0, historySize: 1 };

// overrides from PHP, see config.js
$.extend(globalState.Page, initialState.CAFEVDB.Page);

const busyIcon = function(on) {
  if (on) {
    $('#reloadbutton img.number-0').hide();
    $('#reloadbutton img.number-1').show();
  } else {
    $('#reloadbutton img.number-1').hide();
    $('#reloadbutton img.number-0').show();
  }
};

/**
 * Load a page through the history-aware AJAX page loader.
 *
 * @param {Object} post TBD.
 *
 * @param {Function} afterLoadCallback TBD.
 *
 */
const loadPage = function(post, afterLoadCallback) {
  $('body').removeClass('dialog-titlebar-clicked');
  CAFEVDB.modalizer(true);
  busyIcon(true);
  let action;
  let parameter;
  if (post.historyOffset !== undefined) {
    action = 'recall';
    parameter = post.historyOffset;
  } else {
    action = 'remember';
    parameter = 'blank';
  }
  $.post(generateUrl('page/' + action + '/' + parameter), post)
    .fail(function(xhr, status, errorThrown) {
      const errorData = Ajax.handleError(xhr, status, errorThrown);
      // If the error response contains history data, use it. Othewise
      // reset the history
      if (action === 'recall') {
        if (errorData.history !== undefined) {
          updateHistoryControls(errorData.history.position, errorData.history.size);
        } else {
          updateHistoryControls(0, 0);
        }
      }
      CAFEVDB.modalizer(false);
      busyIcon(false);
    })
    .done(function(htmlContent, textStatus, request) {
      // console.log(data);
      const historySize = parseInt(request.getResponseHeader('X-' + appName + '-history-size'));
      const historyPosition = parseInt(request.getResponseHeader('X-' + appName + '-history-position'));

      // Remove pending dialog when moving away from the page
      $('.ui-dialog-content').dialog('destroy').remove();

      globalState.Page.historyPosition = historyPosition;
      globalState.Page.historySize = historySize;

      // remove left-over notifications
      Notification.hide();

      // remove left-over tool-tips
      $.fn.cafevTooltip.remove();

      // This is a "complete" page reload, so inject the
      // contents into #contents.
      //
      // avoid overriding event handler, although this should
      // be somewhat slower than replacing everything in one run.

      const appGeneralId = appName + '-general';
      const newContent = $('<div>' + htmlContent + '</div>');
      const newAppContent = newContent.find('#' + appGeneralId).children();
      const newAppNavigation = newContent.find('#app-navigation').children();

      $('#app-navigation').empty().prepend(newAppNavigation);
      $('#' + appGeneralId).empty().prepend(newAppContent);

      CAFEVDB.snapperClose();
      CAFEVDB.modalizer(false);
      busyIcon(false);

      CAFEVDB.runReadyCallbacks();
      if (typeof afterLoadCallback === 'function') {
        afterLoadCallback();
      }

      return false;
    });
};

const updateHistoryControls = function(historyPosition, historySize) {
  const redo = $('#personalsettings .navigation.redo');
  const undo = $('#personalsettings .navigation.undo');

  if (historyPosition !== undefined && historySize !== undefined) {
    globalState.Page.historyPosition = historyPosition;
    globalState.Page.historySize = historySize;
  } else {
    historyPosition = globalState.Page.historyPosition;
    historySize = globalState.Page.historySize;
  }

  console.debug('UPDATE HISTORY CONTROLS', globalState.Page);

  redo.prop('disabled', historyPosition === 0);
  undo.prop('disabled', historySize - historyPosition <= 1);
};

/**
 * Optain the service-key for querying the app-container for the
 * renderer class for the given template.
 */
const renderTag = 'template:';
const templateRenderer = function(template) {
  return renderTag + template;
};

const templateFromRenderer = function(templateRenderer) {
  return templateRenderer.replace(renderTag, '');
};

const documentReady = function() {

  const appInnerContent = $('#app-inner-content');

  appInnerContent.on('click', '.ui-dialog-titlebar', function(event) {
    $('body').toggleClass('dialog-titlebar-clicked');
    return false;
  });

  $('#app-navigation-toggle').on('click', function() {
    $('body').removeClass('dialog-titlebar-clicked');
    $(this).cafevTooltip('hide');
  });

  appInnerContent.on(
    'click keydown',
    '#personalsettings .navigation.reload',
    function(event) {
      event.stopImmediatePropagation();
      const pmeReload = appInnerContent.find('form.pme-form input.pme-reload').first();
      if (pmeReload.length > 0) {
        // remove left-over notifications
        Notification.hide();
        pmeReload.trigger('click');
        $('body').removeClass('dialog-titlebar-clicked');
      } else {
        loadPage({
          historyOffset: 0,
        });
      }
      return false;
    });

  appInnerContent.on(
    'click keydown',
    '#personalsettings .navigation.undo',
    function(event) {
      event.stopImmediatePropagation();
      loadPage({
        historyOffset: 1,
      });
      return false;
    });

  appInnerContent.on(
    'click keydown',
    '#personalsettings .navigation.redo',
    function(event) {
      event.stopImmediatePropagation();
      loadPage({
        historyOffset: -1,
      });
      return false;
    });

  CAFEVDB.addReadyCallback(function() {
    // content.find('form.pme-form input.pme-reload').hide();
    $('#app-navigation-toggle')
      .attr('title', t(appName, 'Display the application menu and settings side-bar'))
      .cafevTooltip({
        placement: 'auto',
        container: '#app-content',
      });
    updateHistoryControls();
  });

};

export {
  busyIcon,
  loadPage,
  updateHistoryControls,
  templateRenderer,
  templateFromRenderer,
  documentReady,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

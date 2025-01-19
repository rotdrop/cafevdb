/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from '../config.js';
import globalState from './globalstate.js';
import $ from './jquery.js';
import './jquery-cafevdb-tooltips.js';
import generateUrl from './generate-url.js';
import * as Notification from './notification.js';
import { snapperClose, addReadyCallback, runReadyCallbacks } from './cafevdb.js';
import * as Ajax from './ajax.js';
import modalizer from './modalizer.js';
import * as qs from 'qs';
import { provideHistoryState, pushHistory, updateHistoryControls } from './brower-history.js';
import pageBusyIcon from './busy-icon.js';

/**
 * Load a page through the history-aware AJAX page loader.
 *
 * @param {object} post Post data.
 *
 * @param {Function} afterLoadCallback Called after completeion  let action;
 *
 * @param {boolean} keepHistory Leave the history data as is.
 *
 */
const loadPage = function(post, afterLoadCallback, keepHistory) {
  if (globalState.vueMode) {
    console.trace('*** ERROR ***: loadPage() called in vue-mode.');
    return;
  }
  $('body').removeClass('dialog-titlebar-clicked');
  modalizer(true);
  pageBusyIcon(true);
  let postObject;
  if (typeof post === 'string') {
    postObject = qs.parse(post, { allowSparse: true });
  } else {
    postObject = post;
    post = qs.stringify(postObject);
  }
  $.post(generateUrl('page/remember/blank'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
      // If the error response contains history data, use it. Othewise
      // reset the history
      updateHistoryControls();
      modalizer(false);
      pageBusyIcon(false);
    })
    .done(function(htmlContent, textStatus, request) {

      // Remove pending dialog when moving away from the page
      $('.ui-dialog-content').dialog('destroy').remove();

      if (!keepHistory) {
        pushHistory(postObject);
      }
      updateHistoryControls();

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

      snapperClose();
      modalizer(false);
      pageBusyIcon(false);

      runReadyCallbacks();
      if (typeof afterLoadCallback === 'function') {
        afterLoadCallback();
      }

      return false;
    });
};

addEventListener('popstate', (event) => {
  console.info('HISTORY POP STATE', event);
  if (globalState.vueMode) {
    console.info('*** WARNING *** disable popstate listener in vue-mode');
    return;
  }
  const state = event.state;
  if (state?.[appName]?.post) {
    loadPage(state[appName].post, undefined /* callback */, true /* keep history */);
  }
});

addEventListener('load', (event) => {
  console.info('HISTORY LOAD NEW', event);
  if (globalState.vueMode) {
    console.info('*** WARNING *** disable load listener in vue-mode');
    return;
  }
  provideHistoryState();
});

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
        loadPage(history.state?.[appName]?.post, undefined /* callback */, true /* keep history */);
      }
      return false;
    });

  appInnerContent.on(
    'click keydown',
    '#personalsettings .navigation.undo',
    function(event) {
      history.back();
      return false;
    });

  appInnerContent.on(
    'click keydown',
    '#personalsettings .navigation.redo',
    function(event) {
      history.forward();
      return false;
    });

  addReadyCallback(function() {
    // content.find('form.pme-form input.pme-reload').hide();
    $('#app-navigation-toggle')
      .attr('title', t(appName, 'Display the application menu and settings side-bar'))
      .cafevTooltip({
        placement: 'auto',
        container: '#content',
      });
    updateHistoryControls();
  });

};

export {
  loadPage,
  documentReady,
};

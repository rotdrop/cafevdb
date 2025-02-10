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

import { appName } from '../config.ts';
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
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts';
import { LEGACY_PAGE_LOAD, LEGACY_PAGE_CLEANUP } from '../event-bus-events.ts';

const pageCleanup = () => {
  // Remove pending dialog when moving away from the page
  $('.ui-dialog-content').dialog('destroy').remove();

  $('body').removeClass('dialog-titlebar-clicked');

  // remove left-over notifications
  Notification.hide();

  // remove left-over tool-tips
  $.fn.cafevTooltip.remove();
};

asyncSubscribe(LEGACY_PAGE_CLEANUP, pageCleanup);

/**
 * Load a page through the history-aware AJAX page loader.
 *
 * @param {object} post Post data.
 *
 * @param {boolean} keepHistory Leave the history data as is.
 */
const loadPage = async function(post, keepHistory) {

  pageCleanup();

  let postObject;
  if (typeof post === 'string') {
    postObject = qs.parse(post, { allowSparse: true });
  } else {
    postObject = post;
    post = qs.stringify(postObject);
  }

  modalizer(true);
  pageBusyIcon(true);

  if (globalState.vueMode) {
    const eventData = { post: postObject, keepHistory };
    console.debug('LEGACY LOAD PAGE IN VUE MODE', eventData);
    return asyncEmit(LEGACY_PAGE_LOAD, eventData)
      .finally(() => {
        modalizer(false);
        pageBusyIcon(false);
      });
  }

  return $.post(generateUrl('page/remember/blank'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
      // If the error response contains history data, use it. Othewise
      // reset the history
      updateHistoryControls();
      modalizer(false);
      pageBusyIcon(false);
    })
    .done(async function(htmlContent, textStatus, request) {

      if (!keepHistory) {
        pushHistory(postObject);
      }
      updateHistoryControls();

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
      const $contentContainer = $('#' + appGeneralId);
      $contentContainer.on('load.' + appName, (event) => {
        console.info('CONTENT LOAD EVENT', event);
        $contentContainer.off('load.' + appName);
      });
      $contentContainer.empty().prepend(newAppContent);

      await runReadyCallbacks();

      snapperClose();
      modalizer(false);
      pageBusyIcon(false);
    });
};

console.debug('INSTALL ON POPSTATE LISTENER');
addEventListener('popstate', (event) => {
  console.debug('HISTORY POP STATE', event);
  if (globalState.vueMode) {
    console.debug('*** WARNING *** disable popstate listener in vue-mode');
    return;
  }
  const state = event.state;
  if (state?.[appName]?.post) {
    loadPage(state[appName].post, undefined /* callback */, true /* keep history */);
  }
});

console.debug('INSTALL ON LOAD LISTENER');
addEventListener('load', (event) => {
  console.debug('HISTORY LOAD NEW', event);
  if (globalState.vueMode) {
    console.debug('*** WARNING *** disable load listener in vue-mode');
    return;
  }
  provideHistoryState();
});

const documentReady = function() {

  const $main = $('main');

  $main.on('click', '.ui-dialog-titlebar', function(event) {
    $('body').toggleClass('dialog-titlebar-clicked');
    return false;
  });

  $('#app-navigation-toggle').on('click', function() {
    $('body').removeClass('dialog-titlebar-clicked');
    $(this).cafevTooltip('hide');
  });

  $main.on(
    'click keydown',
    '#personalsettings .navigation.reload',
    function(event) {
      event.stopImmediatePropagation();
      const pmeReload = $main.find('form.pme-form input.pme-reload').first();
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

  $main.on(
    'click keydown',
    '#personalsettings .navigation.undo',
    function(event) {
      history.back();
      return false;
    });

  $main.on(
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

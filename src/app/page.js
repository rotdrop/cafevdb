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

import { initialState, appName } from './config.js';
import { globalState, $ } from './globals.js';
import generateUrl from './generate-url.js';
import * as Notification from './notification.js';
import * as CAFEVDB from './cafevdb.js';
import * as Ajax from './ajax.js';
import modalizer from './modalizer.js';
import * as qs from 'qs';

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

const generateQueryObject = function(post) {
  const searchObject = {};
  const searchFields = ['template', 'projectId'];
  for (const field of searchFields) {
    if (post[field]) {
      searchObject[field] = post[field];
    }
  }
  return searchObject;
};

const generateQueryString = function(post) {
  const searchObject = generateQueryObject(post);
  const queryString = qs.stringify(searchObject);
  return queryString === '' ? '' : '?' + queryString;
};

const generatePageTitle = function(post) {
  const searchObject = generateQueryObject(post);
  const searchTitle = Object.values(searchObject).join('@');
  let title = document.title;
  if (searchTitle !== '') {
    title += ' -- ' + searchTitle;
  }
  return title;
};

const pushHistory = function(post) {
  const oldState = history.state;
  const newState = {
    post,
    nextState: null, // pushState deletes all following entries.
    prevState: oldState,
  };
  oldState.nextState = newState;
  history.replaceState(oldState, generatePageTitle(oldState.post), generateQueryString(oldState.post));
  history.pushState(newState, generatePageTitle(post), generateQueryString(post));
};

const replaceHistory = function(post) {
  const state = history.state;
  state.post = post;
  history.replaceState(state, generatePageTitle(post), generateQueryString(post));
};

/**
 * Load a page through the history-aware AJAX page loader.
 *
 * @param {object} post TBD.
 *
 * @param {Function} afterLoadCallback TBD.
 *
 */
const loadPage = function(post, afterLoadCallback) {
  $('body').removeClass('dialog-titlebar-clicked');
  modalizer(true);
  busyIcon(true);
  let action;
  let postObject;
  if (typeof post === 'string') {
    postObject = qs.parse(post, { allowSparse: true });
  } else {
    postObject = post;
    post = qs.stringify(postObject);
  }
  if (postObject.historyOffset !== undefined) {
    action = 'loader';
  } else {
    action = 'remember';
  }
  $.post(generateUrl('page/remember/blank'), post)
    .fail(function(xhr, status, errorThrown) {
      Ajax.handleError(xhr, status, errorThrown);
      // If the error response contains history data, use it. Othewise
      // reset the history
      updateHistoryControls();
      modalizer(false);
      busyIcon(false);
    })
    .done(function(htmlContent, textStatus, request) {

      // Remove pending dialog when moving away from the page
      $('.ui-dialog-content').dialog('destroy').remove();

      if (action === 'remember') {
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

      CAFEVDB.snapperClose();
      modalizer(false);
      busyIcon(false);

      CAFEVDB.runReadyCallbacks();
      if (typeof afterLoadCallback === 'function') {
        afterLoadCallback();
      }

      return false;
    });
};

const updateHistoryControls = function(state) {
  const redo = $('#personalsettings .navigation.redo');
  const undo = $('#personalsettings .navigation.undo');

  state = state || history.state;

  undo.prop('disabled', !state || !state.prevState);
  redo.prop('disabled', !state || !state.nextState);
};

addEventListener('popstate', (event) => {
  const state = event.state;
  if (state && state.post) {
    loadPage(Object.assign({}, history.state.post, { historyOffset: 0 }));
  }
});

addEventListener('load', (event) => {
  const state = history.state || {};
  Object.assign(state, { post: {}, prevState: null, nextState: null }, state);
  Object.assign(state.post, qs.parse(window.location.search, { ignoreQueryPrefix: true }));
  history.replaceState(state, generatePageTitle(state.post), generateQueryString(state.post));
});

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
        loadPage(Object.assign({}, history.state.post, { historyOffset: 0 }));
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
  pushHistory,
  replaceHistory,
  updateHistoryControls,
  templateRenderer,
  templateFromRenderer,
  documentReady,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

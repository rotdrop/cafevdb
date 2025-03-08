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

import $ from './jquery.js';
import './jquery-cafevdb-tooltips.js';
import * as Notification from './notification.js';
import modalizer from './modalizer.js';
import * as qs from 'qs';
import pageBusyIcon from './busy-icon.js';
import { emit as asyncEmit, subscribe as asyncSubscribe } from '../services/async-event-bus.ts';
import { LEGACY_PAGE_LOAD, LEGACY_PAGE_CLEANUP } from '../event-bus-events.ts';

const pageCleanup = () => {
  // Remove pending dialog when moving away from the page
  console.info('PAGE CLEANUP');

  $('.ui-dialog-content').dialog('close');
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

  const eventData = { post: postObject, keepHistory };
  console.debug('LEGACY LOAD PAGE IN VUE MODE', eventData);
  return asyncEmit(LEGACY_PAGE_LOAD, eventData)
    .finally(() => {
      modalizer(false);
      pageBusyIcon(false);
    });
};

const documentReady = function() {

  // dialog-titlebar-clicked moves dialogs to front, the style/mobiles.scss

  const $main = $('main');

  $main.on('click', '.ui-dialog-titlebar', function(event) {
    $('body').toggleClass('dialog-titlebar-clicked');
    return false;
  });

  $('button.app-navigation-toggle').on('click', function() {
    $('body').removeClass('dialog-titlebar-clicked');
    $(this).cafevTooltip('hide');
  });
};

export {
  loadPage,
  documentReady,
};

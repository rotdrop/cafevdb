/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
import * as qs from 'qs';

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

const provideHistoryState = function(post) {
  if (!post) {
    post = qs.parse(window.location.search, { ignoreQueryPrefix: true });
  }
  const appData = getHistoryState();
  Object.assign(appData.post, post || {});
  const state = history.state || {};
  state[appName] = appData;
  if (globalState.vueMode) {
    return state;
  } else {
    history.replaceState(state, '', generateQueryString(post));
    return history.state;
  }
};

const pushHistory = function(post) {
  console.info('PUSH HISTORY', post);
  const state = history.state || provideHistoryState();
  const newState = {
    post,
    nextState: false, // pushState deletes all following entries.
    prevState: true,
    length: history.length + 1,
  };
  state[appName].nextState = true;
  history.replaceState(state, '', generateQueryString(state?.[appName]?.post));
  state[appName] = newState;
  if (globalState.vueMode) {
    return state;
  } else {
    history.pushState(state, '', generateQueryString(post));
    return history.state;
  }
};

const replaceHistory = function(post) {
  if (!history?.state?.[appName]) {
    provideHistoryState(post);
  } else {
    const state = history.state;
    state[appName].post = post;
    state[appName].length = history.length;
    if (globalState.vueMode) {
      return state;
    } else {
      history.replaceState(state, '', generateQueryString(post));
      return history.state;
    }
  }
};

const getHistoryState = function() {
  return history?.state?.[appName] || { post: {}, prevState: false, nextState: false, length: history?.length || 0 };
};

const updateHistoryControls = function(stateArg) {
  const settingsElement = document.getElementById('personalsettings');

  const redo = settingsElement?.querySelector('.navigation.redo');
  const undo = settingsElement?.querySelector('.navigation.undo');

  if (undo && redo) {
    const state = stateArg || history.state;

    undo.disabled = !state?.[appName]?.prevState;
    redo.disabled = !state?.[appName]?.nextState;
  }
};

export {
  provideHistoryState,
  getHistoryState,
  pushHistory,
  replaceHistory,
  updateHistoryControls,
};

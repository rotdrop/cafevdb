/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { getRequestToken, onRequestTokenUpdate } from '@nextcloud/auth';
import ImagesLoaded from 'imagesloaded';
import { appName, appPrefix, cloudUser, cloudWebRoot, initialState, webRoot } from './config.ts';
import globalState from './globalstate.ts';
import jQuery from './jquery.ts';

import 'jquery-ui';
import 'jquery-ui/ui/effect';
import 'jquery-ui/ui/widgets/dialog';
import 'jquery-ui/ui/widgets/tabs';
// eslint-disable-next-line @typescript-eslint/no-require-imports
require('chosen/public/chosen.jquery.js');
// eslint-disable-next-line @typescript-eslint/no-require-imports
require('chosen/public/chosen.css');

declare global {
  // eslint-disable-next-line @typescript-eslint/no-namespace
  namespace ImagesLoaded {
    interface ImagesLoadedConstructor {
      makeJQueryPlugin($: JQueryStatic): void;
    }
  }
}

ImagesLoaded.makeJQueryPlugin(jQuery);

// some nextcloud hacks

// still needed for jquery
import '../legacy/nextcloud/jquery/requesttoken.js';
// import '@nextcloud/dialogs/styles/toast.scss';
// CSS unrelated to particular modules
import 'oc-fixes.scss';
import 'mobile.scss';
import 'config-check.scss';

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

if (!globalState.initialized) {
  Object.assign(globalState, initialState.CAFEVDB);
  // @TODO the nonce in principle could go to the initial-state
  const requestToken = getRequestToken();
  globalState.nonce = requestToken ? btoa(requestToken) : null;
  globalState.initialNonce = globalState.nonce;
  globalState.initialized = true;
}
let nonce = globalState.nonce;

// this may not be necessary as the actual secret value does not change
onRequestTokenUpdate(function(token) {
  __webpack_nonce__ = token;
  globalState.nonce = token;
  nonce = globalState.nonce;
  console.debug('NEW REQUEST TOKEN', token);
});

const appContainerSelector = [
  '#content.app-' + appName,
  // '#content-vue.content.app-' + appName + ' ' + '#app-content-vue',
  '#content-vue.content.app-' + appName,
].join(',');

export {
  jQuery as $,
  appContainerSelector,
  appName,
  appPrefix,
  cloudUser,
  cloudWebRoot,
  globalState,
  jQuery,
  nonce,
  webRoot,
};

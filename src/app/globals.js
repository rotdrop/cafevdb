/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { onRequestTokenUpdate, getRequestToken } from '@nextcloud/auth';
import { initialState, appName, cloudWebRoot, webRoot, cloudUser, appPrefix, appNameTag } from './config.js';
import jQuery from './jquery.js';
import globalState from './globalstate.js';

require('jquery-ui');
require('jquery-ui/ui/effect');
require('jquery-ui/ui/widgets/dialog');
require('jquery-ui/ui/widgets/tabs');
require('chosen/public/chosen.jquery.js');
require('chosen/public/chosen.css');

const ImagesLoaded = require('imagesloaded');
ImagesLoaded.makeJQueryPlugin(jQuery);

// some nextcloud hacks

// still needed for jquery
require('../legacy/nextcloud/jquery/requesttoken.js');
// require('@nextcloud/dialogs/styles/toast.scss');

// CSS unrelated to particular modules

require('oc-fixes.scss');
require('mobile.scss');
require('config-check.scss');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

if (!globalState.initialized) {
  jQuery.extend(globalState, initialState.CAFEVDB);
  // @TODO the nonce in principle could go to the initial-state
  globalState.nonce = btoa(getRequestToken());
  globalState.initialNonce = globalState.nonce;
  globalState.initialized = true;
}
let nonce = globalState.nonce;

// this may not be necessary as the actual secret value does not change
onRequestTokenUpdate(function(token) {
  globalState.nonce = token;
  nonce = globalState.nonce;
  console.debug('NEW REQUEST TOKEN', token, OC.requestToken);
});

export {
  globalState,
  appName,
  webRoot,
  cloudWebRoot,
  nonce,
  jQuery,
  jQuery as $,
  cloudUser,
  appPrefix,
  appNameTag,
};

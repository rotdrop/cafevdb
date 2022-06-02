/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { onRequestTokenUpdate } from '@nextcloud/auth';
import { initialState, appName, cloudWebRoot, webRoot, cloudUser, appPrefix } from './config.js';
import jQuery from './jquery.js';

require('jquery-ui');
require('jquery-ui/ui/effect');
require('jquery-ui/ui/widgets/dialog');
require('jquery-ui/ui/widgets/tabs');
require('chosen/public/chosen.jquery.js');
require('chosen/public/chosen.css');

const ImagesLoaded = require('imagesloaded');
ImagesLoaded.makeJQueryPlugin(jQuery);

// some nextcloud hacks

require('../legacy/nextcloud/jquery/requesttoken.js');
require('@nextcloud/dialogs/styles/toast.scss');

// CSS unrelated to particular modules

require('oc-fixes.css');
require('mobile.scss');
require('config-check.scss');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

if (window.CAFEFDB === undefined || !window.CAFEVDB.initialized) {
  window.CAFEVDB = jQuery.extend(window.CAFEVDEB || {}, initialState.CAFEVDB);
  // @TODO the nonce in principle could go to the initial-state
  window.CAFEVDB.nonce = btoa(OC.requestToken);
  window.CAFEVDB.initialNonce = window.CAFEVDB.nonce;
  window.CAFEVDB.initialized = true;
}
const globalState = window.CAFEVDB;
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
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

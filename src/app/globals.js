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

import { initialState, appName, cloudWebRoot, webRoot, cloudUser, appPrefix } from './config.js';

function importAll(r) {
  r.keys().forEach(r);
}

// jQuery stuff

const jQuery = require('jquery');
const $ = jQuery;

window.$ = $;
window.jQuery = jQuery;

require('jquery-ui');
require('jquery-ui/ui/effect');
require('jquery-ui/ui/widgets/dialog');
require('jquery-ui/ui/widgets/datepicker');
require('jquery-ui/ui/widgets/tabs');
importAll(require.context('jquery-ui/ui/i18n/', true, /^datepicker-.*\.js$/));
require('chosen/public/chosen.jquery.js');
require('chosen/public/chosen.css');

$.datepicker.setDefaults({
  beforeShow(i) {
    if ($(i).prop('readonly')) {
      return false;
    }
  },
});

const ImagesLoaded = require('imagesloaded');
ImagesLoaded.makeJQueryPlugin(jQuery);

// some nextcloud hacks

require('../legacy/nextcloud/jquery/requesttoken.js');
require('@nextcloud/dialogs/styles/toast.scss');

// CSS unrelated to particular modules

require('oc-fixes.css');
require('mobile.css');
require('config-check.scss');

// ok, this ain't pretty, but unless we really switch to object OOP we
// need some global state which is accessible in all or most modules.

if (window.CAFEFDB === undefined) {
  window.CAFEVDB = initialState.CAFEVDB;
  // @TODO the nonce in principle could go to the initial-state
  window.CAFEVDB.nonce = btoa(OC.requestToken);
}
const globalState = window.CAFEVDB;
const nonce = globalState.nonce;

console.info('INITIAL GLOBAL STATE', globalState, initialState);

export {
  globalState,
  appName,
  webRoot,
  cloudWebRoot,
  nonce,
  jQuery,
  $,
  cloudUser,
  appPrefix,
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

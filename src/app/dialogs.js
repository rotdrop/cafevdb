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

// Compatibility
import { globalState } from './globals.js';

require('dialogs.css');

const alert = function(text, title, callback, modal, allowHtml) {
  return OC.dialogs.message(
    text,
    title,
    'alert',
    OC.dialogs.OK_BUTTON,
    callback,
    modal,
    allowHtml
  );
};

const info = function(text, title, callback, modal, allowHtml) {
  return OC.dialogs.message(
    text,
    title,
    'info',
    OC.dialogs.OK_BUTTON,
    callback,
    modal,
    allowHtml
  );
};

const confirm = function(text, title, callback, modal, allowHtml) {
  return OC.dialogs.message(
    text,
    title,
    'notice',
    OC.dialogs.YES_NO_BUTTONS,
    callback,
    modal,
    allowHtml
  );
};

/**
 * Popup a dialog with debug info if data.data.debug is set and non
 * empty.
 *
 * @param {Object} data TBD.
 *
 * @param {Function} callback TBD.
 *
 */
const debugPopup = function(data, callback) {
  if (typeof data.debug != 'undefined' && datadebug != '') {
    if (typeof callback != 'function') {
      callback = undefined;
    }
    info('<div class="debug error contents">'+data.debug+'</div>',
	 t(CAFEVDB.appName, 'Debug Information'),
	 callback, true, true);
  }
};

export { alert, info, confirm, debugPopup };

globalState.Dialogs = {
  alert, info, confirm, debugPopup
};

// Local Variables: ***
// js-indent-level: 2 ***
// indent-tabs-mode: nil ***
// End: ***

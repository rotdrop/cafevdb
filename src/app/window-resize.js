/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

const attachWindowResizeHandler = () => {

  globalState.oldWidth = -1;
  globalState.oldHeight = -1;

  $(window)
    .off('resize.' + appName)
    .on('resize.' + appName, function(event) {
      console.info('WINDOW RESIZE HANDLER', event);
      if (!globalState.windowResizeTimeout) {
        const delay = 50;
        const width = (window.innerWidth > 0) ? window.innerWidth : screen.width;
        const height = (window.innerHeight > 0) ? window.innerHeight : screen.height;
        if (globalState.oldWidth !== width || globalState.oldHeight !== height) {
          console.debug('cafevdb window size change', width, globalState.oldWidth, height, globalState.oldHeight);
          globalState.windowResizeTimeout = setTimeout(
            function() {
              globalState.windowResizeTimeout = null;
              $('.resize-target, .ui-dialog-content').trigger('resize');
            }, delay);
          globalState.oldHeight = height;
          globalState.oldWidth = width;
        }
      }
    });
};

export default attachWindowResizeHandler;

/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { RESIZE_TARGET } from '../../build/ts-types/php-modules/Controller/CssClasses.ts';
import { appName } from '../config.ts';
import globalState from './globalstate.ts';
import $ from './jquery.ts';

const delay = 50;

globalState.oldWidth = -1;
globalState.oldHeight = -1;

const getWindowSize = () => {
  return {
    width: (window.innerWidth > 0) ? window.innerWidth : screen.width,
    height: (window.innerHeight > 0) ? window.innerHeight : screen.height,
  };
};

const attachWindowResizeHandler = () => {

  $(window)
    .off('resize.' + appName)
    .on('resize.' + appName, function(event) {
      if (!globalState.windowResizeTimeout) {
        const { width, height } = getWindowSize();
        if (globalState.oldWidth !== width || globalState.oldHeight !== height) {
          console.debug('cafevdb window size change', {
            width,
            height,
            oldWidth: globalState.oldWidth,
            oldHeight: globalState.oldHeight,
            event,
          });
          globalState.windowResizeTimeout = setTimeout(
            function() {
              delete globalState.windowResizeTimeout;
              console.debug('WINDOW TRIGGER RESIZE');
              $(`.${RESIZE_TARGET}, .ui-dialog-content`).trigger('resize.' + appName);
            }, delay);
          globalState.oldHeight = height;
          globalState.oldWidth = width;
        }
      }
    });
};

$(() => {
  const { width, height } = getWindowSize();
  globalState.oldWidth = width;
  globalState.oldHeight = height;
});

export default attachWindowResizeHandler;

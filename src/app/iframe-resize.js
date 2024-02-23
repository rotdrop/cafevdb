/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2023, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import { appName } from './app-info.js';
import $ from './jquery.js';
import { loadState } from '@nextcloud/initial-state';
import generateUrl from './generate-url.js';

require('iframe-resizer');

let scriptUrl;

/**
 * Handle iframe resizing based on the size of its contents. The width
 * is prescribed by CSS, though. Perhaps we would like to resize the
 * iframe also horizontally if the screen is too small (mobile
 * devices).
 *
 * @param {jQuery} $iframe TBD.
 */
const iFrameResize = function($iframe) {
  $iframe = $($iframe);

  if (!scriptUrl) {
    const iFrameContentScriptData = loadState(appName, 'iFrameContentScript');
    scriptUrl = generateUrl('js/' + iFrameContentScriptData.asset + '.js');
  }

  $iframe.contents().find('head').prepend(`<script type="text/javascript" defer src="${scriptUrl}"></script>`);

  $iframe.iFrameResize();
};

export default iFrameResize;

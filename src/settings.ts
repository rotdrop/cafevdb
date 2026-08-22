/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020-2022, 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

export * from './webpack-setup.ts';
import { runReadyCallbacks } from './app/cafevdb.ts';
import { attachDialogHandlers } from './app/dialogs.js';
import personalSettings from './app/personal-settings.js';
import appSettings from './app/settings.js';
import onDocumentLoaded from './toolkit/util/on-document-loaded.ts';

import './app/jquery-extensions.ts';

require('personal-settings.scss');

onDocumentLoaded(() => {
  attachDialogHandlers();
  appSettings();
  personalSettings();
  runReadyCallbacks();
});

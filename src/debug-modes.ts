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

import { appName } from './config.js';
import { translate as t } from '@nextcloud/l10n';

export const DEBUG_GENERAL = (1 << 0);
export const DEBUG_QUERY = (1 << 1);
export const DEBUG_CSP = (1 << 2);
export const DEBUG_L10N = (1 << 3);
export const DEBUG_REQUEST = (1 << 4);
export const DEBUG_TOOLTIPS = (1 << 5);
export const DEBUG_EMAILFORM = (1 << 6);
export const DEBUG_GEOCODING = (1 << 7);
export const DEBUG_ALL = DEBUG_GENERAL
  | DEBUG_QUERY
  | DEBUG_CSP
  | DEBUG_L10N
  | DEBUG_REQUEST
  | DEBUG_TOOLTIPS
  | DEBUG_EMAILFORM
  | DEBUG_GEOCODING;
export const DEBUG_NONE = 0;

export const debugOptions = {
  [DEBUG_GENERAL]: t(appName, 'General Information'),
  [DEBUG_QUERY]: t(appName, 'SQL Queries'),
  [DEBUG_CSP]: t(appName, 'CSP Violations'),
  [DEBUG_L10N]: t(appName, 'L10N'),
  [DEBUG_REQUEST]: t(appName, 'HTTP Requests'),
  [DEBUG_TOOLTIPS]: t(appName, 'Missing Context Help'),
  [DEBUG_EMAILFORM]: t(appName, 'Mass Email Form'),
  [DEBUG_GEOCODING]: t(appName, 'GeoCoding'),
};

export default debugOptions;

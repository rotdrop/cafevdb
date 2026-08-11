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

import { translate as t } from '@nextcloud/l10n';
import {
  // DEBUG_ALL,
  DEBUG_CSP,
  DEBUG_EMAILFORM,
  DEBUG_GENERAL,
  DEBUG_GEOCODING,
  DEBUG_L10N,
  // DEBUG_NONE,
  DEBUG_ORM,
  DEBUG_QUERY,
  DEBUG_REQUEST,
  DEBUG_SMAPS,
  DEBUG_TOOLTIPS,
  DEBUG_VUE,
} from '../build/ts-types/php-modules/Settings/ConfigConstants.ts';
import { appName } from './config.ts';

export {
  DEBUG_ALL,
  DEBUG_CSP,
  DEBUG_EMAILFORM,
  DEBUG_GENERAL,
  DEBUG_GEOCODING,
  DEBUG_L10N,
  DEBUG_NONE,
  DEBUG_ORM,
  DEBUG_QUERY,
  DEBUG_REQUEST,
  DEBUG_SMAPS,
  DEBUG_TOOLTIPS,
  DEBUG_VUE,
} from '../build/ts-types/php-modules/Settings/ConfigConstants.ts';

export const debugOptions = {
  [DEBUG_CSP]: t(appName, 'CSP Violations'),
  [DEBUG_EMAILFORM]: t(appName, 'Mass Email Form'),
  [DEBUG_GENERAL]: t(appName, 'General Information'),
  [DEBUG_GEOCODING]: t(appName, 'GeoCoding'),
  [DEBUG_L10N]: t(appName, 'L10N'),
  [DEBUG_ORM]: t(appName, 'ORM (disable caching)'),
  [DEBUG_QUERY]: t(appName, 'SQL Queries'),
  [DEBUG_REQUEST]: t(appName, 'HTTP Requests'),
  [DEBUG_SMAPS]: t(appName, 'Resolve JS Sourceode'),
  [DEBUG_TOOLTIPS]: t(appName, 'Missing Context Help'),
  [DEBUG_VUE]: t(appName, 'Vue JS Frontend'),
};

export default debugOptions;

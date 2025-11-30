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

import { appName as configAppName } from '../../build/ts-types/app-config.ts';
import OCInstance from './OC.ts';

declare global {
  // eslint-disable-next-line
  var appName: string;
  // eslint-disable-next-line
  var APP_NAME: string;
  // eslint-disable-next-line
  // var OC: Record<string, any>
}

global.appName = configAppName;
global.APP_NAME = configAppName;
// @ts-expect-error 2339
global.OC = OCInstance;

export {};

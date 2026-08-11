/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2024, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import type { AppName } from '../build/ts-types/app-config.ts';

import { appName } from '../build/ts-types/app-config.ts';

function appPrefix<T extends string>(id: T): `${AppName}-${T}`;
function appPrefix<T1 extends string, T2 extends string>(id: T1, join: T2): `${AppName}${T2}${T1}`;
/**
 * @param id Custom postfix appended to the app-name with the separator join.
 *
 * @param join Separator between app-name and id.
 */
function appPrefix<T extends string>(id: T, join = '-') {
  // return joinLiterals(join)(appName, id);
  return `${appName}${join}${id}` as const;
}

const appNameTag = `app-${appName}` as const;

export type {
  AppName,
};

export {
  appName,
  appNameTag,
  appPrefix,
};

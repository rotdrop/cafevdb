/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2025, 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

export type * from './type-traits.d.ts';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const isOne = (x: any): x is 1 => x === 1;

export const asKey = <T extends object, K extends keyof T = keyof T>(_arg: T, key: K): K => key;

export const hasProperty = <T = unknown, K extends string = string>(property: K, arg: unknown)
  : arg is { [key in K]: T } => typeof arg === 'object' && !!arg && property in arg;

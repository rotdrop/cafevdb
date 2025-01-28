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

export const PERMISSION_NONE = 0;
export const PERMISSION_FRONTEND = 0x01;
export const PERMISSION_ADDRESSBOOK = 0x02;
export const PERMISSION_FILESYSTEM = 0x04;
export const PERMISSION_CALENDAR = 0x08;
export const PERMISSION_FINANCE = 0x10;
export const PERMISSION_MANAGEMENT = 0x20;
export const PERMISSION_EMAIL = 0x40;
export const PERMISSION_ALL = PERMISSION_FRONTEND
  | PERMISSION_ADDRESSBOOK
  | PERMISSION_FILESYSTEM
  | PERMISSION_CALENDAR
  | PERMISSION_FINANCE
  | PERMISSION_MANAGEMENT
  | PERMISSION_EMAIL;

export const authorized = (requestedPermissions: number, availablePermissions: number) => (requestedPermissions === (requestedPermissions & availablePermissions));

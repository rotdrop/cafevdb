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
export const PERMISSION_FRONTEND = (1 << 0);
export const PERMISSION_ADDRESSBOOK = (1 << 1);
export const PERMISSION_FILESYSTEM = (1 << 2);
export const PERMISSION_CALENDAR = (1 << 3);
export const PERMISSION_FINANCE = (1 << 4);
export const PERMISSION_MANAGEMENT = (1 << 5);
export const PERMISSION_EMAIL = (1 << 6);
export const PERMISSION_ALL = PERMISSION_FRONTEND
  | PERMISSION_ADDRESSBOOK
  | PERMISSION_FILESYSTEM
  | PERMISSION_CALENDAR
  | PERMISSION_FINANCE
  | PERMISSION_MANAGEMENT
  | PERMISSION_EMAIL;

export const authorized = (requestedPermissions: number, availablePermissions: number) => (requestedPermissions === (requestedPermissions & availablePermissions));

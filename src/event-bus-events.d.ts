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

import {
  LEGACY_PAGE_LOAD,
  LEGACY_PME_HISTORY_UPDATE,
} from './event-bus-events.js';

declare module '@nextcloud/event-bus' {
  export interface NextcloudEvents {
    // mapping of 'event name' => 'event type'
    LEGACY_PAGE_LOAD: { post: object, template: string|null, projectId: number|null, projectName: string|undefined, keepHistory: boolean }
    LEGACY_PME_HISTORY_UPDATE: { post: object, html: string, action: string }
  }
}
export {
  LEGACY_PAGE_LOAD,
  LEGACY_PME_HISTORY_UPDATE,
};

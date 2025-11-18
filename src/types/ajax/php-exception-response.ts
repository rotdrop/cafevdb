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

import { isAxiosErrorResponse, type AxiosErrorResponse } from '../../toolkit/types/axios-type-guards.ts';
import type {
  ILogEntry as NextcloudLogEntry,
  IException as NextcloudException,
} from '@nextcloud/app-logreader/src/interfaces/ILogEntry.ts';
import type Keyable from '../../types/keyable.d.ts';

export interface NextcloudExceptionLogEntry extends Omit<NextcloudLogEntry, 'exception'> {
  exception: NextcloudException,
}

export const isNextcloudLogEntry = (data: unknown): data is NextcloudLogEntry =>
  !!data && typeof data === 'object' && !!(data as Keyable).reqId && !!(data as Keyable).app;

export const isNextcloudExceptionLogEntry = (data: unknown): data is NextcloudExceptionLogEntry =>
  isNextcloudLogEntry(data) && !!data.exception;

export const isNextcloudExceptionResponse = (data: unknown): data is AxiosErrorResponse<NextcloudExceptionLogEntry> =>
  isAxiosErrorResponse(data) && isNextcloudExceptionLogEntry(data.response.data);

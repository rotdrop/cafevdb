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

import { AxiosError } from 'axios';
import { isAxiosError } from '../../toolkit/types/axios-type-guards.ts';
import type { ILogEntry as NextcloudLogEntry } from '@nextcloud/app-logreader/src/interfaces/ILogEntry.ts'

export type { ILogEntry as NextcloudLogEntry } from '@nextcloud/app-logreader/src/interfaces/ILogEntry.ts'

export const isNextcloudLogEntry = (data: any): data is NextcloudLogEntry =>
  (!!data
   && typeof data === 'object'
   && data.app
   && data.level
   && data.message
   && data.reqId);

export const isNextcloudExceptionResponse = <D = any>(error: any): error is AxiosError<NextcloudLogEntry, D> =>
  isAxiosError(error) && !!error.response && isNextcloudLogEntry(error.response.data);

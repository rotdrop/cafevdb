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
import { isAxiosError } from './axios-type-guards.ts';
import ResponseTypes from './response-types.ts';

export interface PHPExceptionData {
  type: ResponseTypes.PHPExceptionData,
  message: string, // heading, deprecated
  brief: string, // brief summary, deprecated
  exception: { // the data from the exception itself
    class: string, // full exception PHP class name
    message: string, // exception message
    file: string,
    line: number,
    code: number,
    trace: string, // $e->getTraceAsString()
  },
  previous: null|PHPExceptionData,
}

export const isPHPExceptionData = (data: any): data is PHPExceptionData =>
  !!data && typeof data === 'object' && data?.type === ResponseTypes.PHPExceptionData

export const isPHPExceptionResponse = <D = any>(error: any): error is AxiosError<PHPExceptionData, D> =>
  isAxiosError(error) && !!error.response && isPHPExceptionData(error.response.data)

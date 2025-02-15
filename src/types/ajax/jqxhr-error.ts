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

import { isNextcloudLogEntry } from './nextcloud-log.ts';
import type { NextcloudLogEntry } from './nextcloud-log.ts';

export type JqXHR<T = any> = JQuery.jqXHR<T>;

export class JQueryAjaxError<T = any> extends Error {
  constructor(message: string, xhr: JqXHR<T>, html?: string) {
    super(message);
    this.name = 'JQAjaxError';
    this.cause = xhr;
    this.html = html;
  }
  cause: JqXHR<T>;
  html?: string;
}

export interface JqJsonXHR<T = any> extends Omit<JqXHR<T>, 'responseJSON'> {
  responseJSON: T;
}

export interface JqHtmlXHR extends Omit<JqXHR<string>, 'responseJSON'> {
  responseText: string; // present anyway, but so what
}

export interface JQueryAjaxHtmlError<T extends string> extends Omit<JQueryAjaxError<T>, 'html'> {
  html: T;
}

// sparse testing for a jQuery XHR object ...
export const isJqXHR = <T = any>(error: any): error is JqXHR<T> =>
  !!error && error.responseText && error.status && error.abort && error.done && error.fail;

export const isJqJsonXHR = <T = any>(error: any): error is JqJsonXHR<T> =>
  isJqXHR(error) && error.responseJSON;

export const isJqHtmlXHR = (error: any): error is JqHtmlXHR =>
  isJqXHR(error) && !error.responseJSON && ((error.getResponseHeader('content-type') || '').includes('html'));

export const isJqNextcloudLogEntryXHR = (error: any): error is JqJsonXHR<NextcloudLogEntry> =>
  isJqXHR(error) && isNextcloudLogEntry(error.responseJSON);

export const isJQueryAjaxHtmlError = <T extends string>(error: any): error is JQueryAjaxHtmlError<T> =>
  error instanceof JQueryAjaxError && !!error.html;

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

export const MailMergeDataset = 'dataset';
export const MailMergeDownload = 'download';
export const MailMergeCloud = 'cloud';
export type MailMergeOperation = typeof MailMergeDataset
  | typeof MailMergeDownload
  | typeof MailMergeCloud

export interface ContactKeys {
  key: string|number,
  uri?: string,
  uid: string,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  book: any,
}

export interface MailMergePayload {
  senderId?: number|string,
  fileName?: string,
  templateName?: string,
  projectId?: number,
  recipientIds?: number[],
  contactKeys?: ContactKeys[],
  addressBooksUris?: Record<string, string>,
  compositePaymentIds?: number[],
  invoiceIds?: (string|number)[],
  operation: MailMergeOperation,
  limit?: number,
  offset?: null,
}

export interface MailMergeResponse {
  message: string,
  cloudFolder: string,
  cloudFiles: string[],
  count: number,
  senderId: number,
}

export {};

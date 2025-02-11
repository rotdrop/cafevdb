/**
 * Orchestra member, musicion and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022, 2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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

import * as ncRouter from '@nextcloud/router';
import generateAppUrl from '../app/generate-url.js';
import axios from '@nextcloud/axios';
import { saveAs } from 'file-saver';
import { parse as parseContentDisposition } from 'content-disposition';

/**
 * Download from ajax link.
 *
 * @param url Url target.
 *
 * @param data Request payload.
 *
 * @param method HTTP methods, defaults to 'post'.
 */
export default async function(url: string, data: any, method = 'post') {
  method = method || 'post';
  url = (url.startsWith(ncRouter.generateUrl(''))
         || url.startsWith(ncRouter.generateRemoteUrl('')))
    ? url
    : generateAppUrl(url);
  const response = await axios({
    method,
    url,
    data,
    responseType: 'blob',
  });

  let fileName = 'download';
  const contentDisposition = response.headers['content-disposition'];
  if (contentDisposition) {
    const contentMeta = parseContentDisposition(contentDisposition);
    fileName = contentMeta.parameters.filename || fileName;
  }
  let contentType = response.headers['content-type'];
  if (contentType) {
    contentType = contentType.split(';')[0];
  } else {
    contentType = 'application/octetstream';
  }

  // Convert the Byte Data to BLOB object.
  const blob = new Blob([response.data], { type: contentType });

  saveAs(blob, fileName);
}

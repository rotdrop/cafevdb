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

import { expect, beforeAll } from '@jest/globals';
import type { DownloadsShareResponse } from '../../../../../build/ts-types/php-modules/Controller/DTO.ts';
import { spawnSync } from 'child_process';
import path from 'path';
import fs from 'fs';
import { DateTime } from 'luxon';

declare global {
  const APP_ROOT: string;
  const JEST_ARTIFACTS: string;
}

let dto: DownloadsShareResponse;

beforeAll(async () => {
  spawnSync(path.join(__dirname, 'dto-generator.php'), ['DownloadsShareResponse', JEST_ARTIFACTS]);
  const dtoJSON = fs.readFileSync(path.join(JEST_ARTIFACTS, 'DownloadsShareResponse' + '.json'));
  dto = JSON.parse(dtoJSON.toString()) as DownloadsShareResponse;
  // console.info('DTO', { dto, json: dtoJSON.toString() });
});

describe('DownloadsShareResponse', () => {
  it('should have a plain string as expires date', () => {
    expect(typeof dto.expires).toBe('string');
  });
});

describe('DownloadsShareResponse', () => {
  it('should have an expires string which is convertible to a Date instance', () => {
    expect(dto?.expires ? new Date(dto.expires) : false).toBeInstanceOf(Date);
  });
});

describe('DownloadsShareResponse', () => {
  it('should have an expires string which is convertible to a Luxon DateTime instance', () => {
    expect(dto?.expires ? DateTime.fromISO(dto.expires) : false).toBeInstanceOf(DateTime);
  });
});

describe('DownloadsShareResponse', () => {
  it('should have an expires string which is correctly convertible to a Luxon DateTime instance', () => {
    const stringValue = dto?.expires ? DateTime.fromISO(dto.expires, { setZone: true }).toISO() ?? 'XXXX' : 'XXXX';
    const expiresValue = (dto?.expires ?? 'WWWW');
    expect(stringValue).toBe(expiresValue.substring(0, 23) + expiresValue.substring(26));
  });
});

describe('DownloadsShareResponse', () => {
  it('should have an expires string of the form YYYY-MM-DD', () => {
    expect(((dto?.expires ?? '').match(/^\d{4}-\d{2}-\d{2}/) ?? [''])[0]).toBe((dto?.expires ?? 'XXXXXXXXXXX').substring(0, 10));
  });
});

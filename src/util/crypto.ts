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

import * as WebCrypto from 'easy-web-crypto';

import type {
  ProtectedMasterKey,
  CipherData,
} from 'easy-web-crypto';

/**
 * derive a new key from passphrase and generate the master AES key
 * (you can now store this encrypted key for later use)
 */
export const generateMasterKey = (passphrase: string) =>
  WebCrypto.genEncryptedMasterKey(passphrase);

/** decrypt the (stored) AES key to be able to encrypt/decrypt data */
export const decryptMasterKey = (
  passphrase: string,
  encMasterKey: ProtectedMasterKey,
) => WebCrypto.decryptMasterKey(passphrase, encMasterKey);

/** Encrypt data */
export const encrypt = (key: CryptoKey, data: string) =>
  WebCrypto.encrypt(key, data);

/** Decrypt data */
export const decrypt = (key: CryptoKey, encrypted: CipherData) =>
  WebCrypto.decrypt(key, encrypted);

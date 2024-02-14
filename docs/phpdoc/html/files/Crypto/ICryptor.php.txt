<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020, 2021, 2022, 2023, 2024 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Crypto;

/** Simple wrapper interface. */
interface ICryptor
{
  /**
   * @param null|string $data
   *
   * @return null|string
   */
  public function encrypt(?string $data):?string;

  /**
   * @param null|string $data
   *
   * @return null|string
   */
  public function decrypt(?string $data):?string;

  /** @return bool */
  public function canEncrypt():bool;

  /** @return bool */
  public function canDecrypt():bool;

  /**
   * Determines if the given data has been encrypted by this cryptor
   * instance. A returned null means "don't know".
   *
   * @param null||string $data
   *
   * @return null|bool
   */
  public static function isEncrypted(?string $data):?bool;
}

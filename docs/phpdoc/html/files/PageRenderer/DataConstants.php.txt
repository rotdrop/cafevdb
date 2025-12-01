<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\PageRenderer;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * Define some constants for consistency.
 */
#[TSAttributes\TypeScript]
class DataConstants
{
  public const DATA_META_DATA = 'meta-data';
  public const DATA_SEALED_VALUE = 'sealed-value';
  public const DATA_CRYPTO_HASH = 'crypto-hash';
  public const DATA_PME_VALUES = 'pme-values';

  public const CLASS_META_DATA_POPUP = self::DATA_META_DATA . '-popup';
  public const CLASS_LAZY_DECRYPTION = 'lazy-decryption';

  const VALUES_SEP = ',';
  const JOIN_FIELD_NAME_SEPARATOR = ':';
  const JOIN_KEY_SEP = ':';
  const COMP_KEY_SEP = '-';
  const VALUES_TABLE_SEP = '@';

  const DATA_DATA_KEY = 'data';
  const DATA_VALUES_KEY = 'values';
}

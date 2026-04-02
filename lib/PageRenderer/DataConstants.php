<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022-2026 Claus-Justus Heine
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

use OCA\CAFEVDB\Legacy\PhpMyEdit\PhpMyEdit as PME;

/**
 * Define some constants in order to avoid typos and to enforce consistent
 * naming.
 */
#[TSAttributes\TypeScript]
class DataConstants
{
  public const RENDERER_PREFIX_TAG = 'template:';
  public const EXPORTER_PREFIX_TAG = 'export:';

  public const DATA_META_DATA = 'meta-data';
  public const DATA_SEALED_VALUE = 'sealed-value';
  public const DATA_CRYPTO_HASH = 'crypto-hash';

  public const CLASS_META_DATA_POPUP = self::DATA_META_DATA . '-popup';
  public const CLASS_LAZY_DECRYPTION = 'lazy-decryption';

  const VALUES_SEP = ',';
  const JOIN_FIELD_NAME_SEPARATOR = ':';
  const JOIN_KEY_SEP = ':';
  const COMP_KEY_SEP = '-';
  const VALUES_TABLE_SEP = '@';

  const DATA_DATA_KEY = 'data';
  const DATA_VALUES_KEY = 'values';

  const MASTER_FIELD_SUFFIX = '__master_key_';

  const PAGE_RENDERER = [
    'masterFieldSuffix' => self::MASTER_FIELD_SUFFIX,
    'valuesTableSep' => self::VALUES_TABLE_SEP,
    'joinKeySep' => self::JOIN_KEY_SEP,
    'compKeySep' => self::COMP_KEY_SEP,
    'joinFieldNameSeparator' => self::JOIN_FIELD_NAME_SEPARATOR,
  ];

  const DATA_PME_GROUP_ID = PME::DATA_GROUP_ID;
  const DATA_PME_GROUP_INFO = PME::DATA_GROUP_INFO;
  const DATA_PME_INITIAL_VALUES = PME::DATA_INITIAL_VALUES;
  const DATA_PME_ORIGINAL_VALUE = PME::DATA_ORIGINAL_VALUE;
  const DATA_PME_PME_VALUES = PME::DATA_PME_VALUES;
  const DATA_PME_TAB_ID = PME::DATA_TAB_ID;
  const DATA_PME_TAB_INDEX = PME::DATA_TAB_INDEX;

  const MRECS_KEY = PME::MRECS_KEY;

  const OPERATION_CHANGE = PME::OPERATION_CHANGE;
  const OPERATION_COPY_ADD = PME::OPERATION_COPY_ADD;
  const OPERATION_DELETE = PME::OPERATION_DELETE;
  const OPERATION_LIST = PME::OPERATION_LIST;
  const OPERATION_VIEW = PME::OPERATION_VIEW;
}

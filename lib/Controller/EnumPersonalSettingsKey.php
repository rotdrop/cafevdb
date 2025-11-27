<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Controller;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

use OCA\CAFEVDB\Settings\ConfigConstants;

/**
 * (Some) personal settings keys.
 */
#[TypeScript(options: ['nativeEnums' => true])]
enum EnumPersonalSettingsKey: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

  case DEBUG_MODE = 'debugMode';
  case DEBUG_QUERY_SQL_FILTER = 'debugQuerySqlFilter';
  case DESELECT_INVISIBLE_MISC_RECS = 'deselectInvisibleMiscRecs';
  case DIRECT_CHANGE = 'directChange';
  case EMAIL_DRAFT_AUTO_SAVE = 'emailDraftAutoSave';
  case ENCRYPTION_KEY = 'encryptionKey';
  case EXPERT_MODE = 'expertMode';
  case INITIAL_FILTER_VISIBILITY = 'initialFilterVisibility';
  case FINANCE_MODE = 'financeMode';
  case PAGE_ROWS_DEFAULT = 'pageRowsDefault';
  case RESTORE_HISTORY = 'restoreHistory';
  case SHOW_DISABLED = 'showDisabled';
  case TOOL_TIPS_ENABLED = 'toolTipsEnabled';
  case WYSIWYG_EDITOR = 'wysiwygEditor';
  case DEFAULT_EMAIL_FROM_ADDRESS = 'defaultEmailFromAddress';
}

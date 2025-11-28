<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Settings;

use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;

/**
 * Compatibility stuff to keep for a while after changing some config keys to
 * camel case or whatever.
 */
class OldSettingsKeys
{
  public const USER_KEYS = [
    EnumPersonalSettingsKey::DEBUG_MODE->value => 'debugmode',
    EnumPersonalSettingsKey::PAGE_ROWS_DEFAULT->value => 'pagerows',
    EnumPersonalSettingsKey::RESTORE_HISTORY->value => 'restorehistory',
    EnumPersonalSettingsKey::DIRECT_CHANGE->value => 'directchange',
    EnumPersonalSettingsKey::SHOW_DISABLED->value => 'showdisabled',
    EnumPersonalSettingsKey::EMAIL_DRAFT_AUTO_SAVE->value => 'email-draft-auto-save',
  ];

  public const APP_KEYS = [
    ConfigConstants::APP_ENCRYPTION_KEY_HASH_KEY => 'encryptionkeyhash',
    ConfigConstants::USER_GROUP_KEY => 'usergroup',
    ConfigConstants::EMAIL_USER => 'emailuser',
  ];
}

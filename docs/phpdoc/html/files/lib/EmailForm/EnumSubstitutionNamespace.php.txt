<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\EmailForm;

/** Valid keys for ${NAMESPACE::KEY} substitutions. */
enum EnumSubstitutionNamespace: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait;

  case GLOBAL = 'GLOBAL';
  case MEMBER = 'MEMBER';

  /**
   * @param string|EnumGlobalSubstitutionKey|EnumMemberSubstitutionKey $key
   *
   * @return EnumGlobalSubstitutionKey|EnumMemberSubstitutionKey
   */
  public function substitutionCase(string|EnumGlobalSubstitutionKey|EnumMemberSubstitutionKey $key): EnumGlobalSubstitutionKey|EnumMemberSubstitutionKey
  {
    switch ($this) {
      case self::GLOBAL:
        return EnumGlobalSubstitutionKey::get($key);
      case self::MEMBER:
        return EnumMemberSubstitutionKey::get($key);
    }
  }

  /**
   * @return array<EnumMemberSubstitutionKey|EnumGlobalSubstitutionKey>
   */
  public function substitionCases(): array
  {
    switch ($this) {
      case self::GLOBAL:
        return EnumGlobalSubstitutionKey::cases();
      case self::MEMBER:
        return EnumMemberSubstitutionKey::cases();
      default:
        return [];
    }
  }
}

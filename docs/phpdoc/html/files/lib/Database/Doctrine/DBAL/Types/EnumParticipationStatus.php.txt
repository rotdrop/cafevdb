<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2022, 2024-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Database\Doctrine\DBAL\Types;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * Participation status enum for musicians.
 *
 * @todo This should rather be specified per project.
 */
#[TSAttributes\TypeScript(options: ['nativeEnums' => true])]
enum EnumParticipationStatus: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\TranslatableEnumTrait;

  case ASSOCIATED = 'associated';
  case CONDUCTOR = 'conductor';
  case PASSIVE = 'passive';
  case REGULAR = 'regular';
  case SOLOIST = 'soloist';
  case TEMPORARY = 'temporary';
}

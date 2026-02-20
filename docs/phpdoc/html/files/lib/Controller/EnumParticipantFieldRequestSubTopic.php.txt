<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/** Allowed post-params for '/projects/participant-fields/property/get' */
#[TSAttributes\TypeScript(options: ['nativeEnums' => true])]
enum EnumParticipantFieldRequestSubTopic: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

  /**
   * @var string Resolve a receivables generator with the given user input.
   */
  case DEFINE = 'define';

  /**
   * @var string Generate (missing) fields.
   */
  case RUN = 'run';

  /**
   * @var string For the given project (re-)generate all generated
   * receivables. The other operations refer to one specific receivable.
   */
  case RUN_ALL = 'run-all';

  /**
   * Recompute one or all receivables, given on the request parameters
   * provided.
   */
  case REGENERATE = 'regenerate';

  case GET = 'get';
}

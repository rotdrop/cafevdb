<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020-2026 Claus-Justus Heine
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

/** CSS classes shared between the legacy templates, scss and typescript. */
#[TSAttributes\TypeScript]
class CssClasses
{
  public const ACCEPT_GENDER_DETECTION = 'accept-gender-detection';
  public const AMOUNT_CHECK_FAILURE = 'amount-check-failure';
  public const CSS_PREFIX_POSTFIX = 'page';
  public const DIRECT_CHANGE = 'direct-change';
  public const PROJECT_PARTICIPANT_FIELDS_DISPLAY = 'project-participant-fields-display';
  public const REVERT_TO_DEFAULT = 'revert-to-default';
  public const SHOW_HIDE_DISABLED = 'show-hide-disabled';
}

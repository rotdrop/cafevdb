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

namespace OCA\CAFEVDB\Controller;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/**
 * CSS classes shared between the legacy templates, scss and
 * typescript. Perhaps one should move this file somewhere else.
 */
#[TSAttributes\TypeScript]
class CssClasses
{
  public const APP_NAME_TAG_PREFIX = 'app' . self::CLASS_SEPARATOR;
  public const BUSY = 'busy';
  public const CLASS_SEPARATOR = '-';
  public const HAVE_WRITTEN_MANDATE = 'have-written-mandate';
  public const HIDDEN = 'hidden';
  public const HIDE_ONLY_CHILD = 'hide-only-child';
  public const NO_WRITTEN_MANDATE = 'no-written-mandate';
  public const RESIZE_TARGET = 'resize-target';
  public const UPLOAD_WRITTEN_MANDATE_LATER = 'upload-written-mandate-later';
  public const WRITTEN_MANDATE_UPLOAD = 'written-mandate-upload';
  public const WYSIWYG_EDITOR = 'wysiwyg-editor';
}

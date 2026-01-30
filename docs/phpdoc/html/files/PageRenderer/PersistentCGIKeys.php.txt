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

namespace OCA\CAFEVDB\PageRenderer;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

/** Provide some constants to enforce consistency and avoid typos. */
#[TSAttributes\TypeScript]
class PersistentCGIKeys
{
  public const TABLE = 'table';
  public const TEMPLATE = 'template';
  public const TEMPLATE_RENDERER = 'templateRenderer';
  public const MUSICIAN_ID = 'musicianId';
  public const PROJECT_ID = 'projectId';
  public const PROJECT_NAME = 'projectName';
  public const RECORDS_PER_PAGE = 'recordsPerPage';
  public const INSTRUMENTS_FDD_INDEX = 'instrumentsFddIndex';
  public const PARTICIPATION_STATUS_FDD_INDEX = 'participationStatusFddIndex';
  public const PARTICIPATION_CONTEXT = 'participationContext';
  public const DATA_PREFIX = 'dataPrefix';
  public const ENTITY_ROWS_EXPANDED = 'entityRowsExpanded';
}

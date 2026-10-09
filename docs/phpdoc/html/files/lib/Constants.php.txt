<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022, 2023, 2025, 2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB;

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCA\CAFEVDB\Toolkit\Constants as ToolkitConstants;

/** General constants for the app. */
#[TSAttributes\TypeScript]
class Constants extends ToolkitConstants
{
  const README_NAME = 'README.md';
  const OLD_CONTENT_SEPARATOR = "\n\n----------------------\n\n";
  const RENDER_AS_PARTS = 'parts'; // silly name
  // SQL variables in order to grant access to personal data
  const SQL_ROW_ACCESS_TOKEN = 'ROW_ACCESS_TOKEN';
  const SQL_CLOUD_USER_ID = 'CLOUD_USER_ID';
  const SQL_PROJECT_APPLICATION_ROW_ACCESS_TOKEN = 'PROJECT_APPLICATION_ROW_ACCESS_TOKEN';
  const SQL_PROJECT_APPLICATION_SHARE_TOKENS = 'PROJECT_APPLICATION_SHARE_TOKENS';
  const SQL_PROJECT_APPLICATION_PROJECT_NAME = 'PROJECT_APPLICATION_PROJECT_NAME';
}

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

use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumAttachmentOrigin as AttachmentOrigin;

/**
 * Upload origin, arguably the origin "cloud" is not an upload origin,
 * however, the idea is to provide "upload from local machine" as well as
 * "choose from cloud".
 */
enum EnumFileUploadOrigin: string
{
  use \OCA\CAFEVDB\Toolkit\Traits\BackedEnumTrait;

  // @todo The DB enum has more options. Are they needed with AJAX?
  case UPLOAD = AttachmentOrigin::UPLOAD->value;
  case CLOUD = AttachmentOrigin::CLOUD->value;
}

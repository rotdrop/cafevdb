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

namespace OCA\CAFEVDB\Tests\Unit\Controller\DTO;

use OCA\CAFEVDB\Controller\DTO\UploadFileMetaData;
use OCA\CAFEVDB\Controller\EnumAddDocumentConflictAction;
use OCA\CAFEVDB\Controller\EnumFileStorageBackend;

/** Generate an instance of UploadFileMetaData */
trait UploadFileMetaDataTrait
{
  /** @return UploadFileMetaData */
  protected function getUploadFileMetaData(): UploadFileMetaData
  {
    return new UploadFileMetaData(
      musicianId: 1,
      projectId: 1,
      dirName: 'dirName',
      baseName: 'baseName',
      extension: 'extension',
      fileName: 'fileName',
      fileId: 1,
      storageBackend: EnumFileStorageBackend::DB,
      download: 'download',
      filesApp: 'filesApp',
      conflict: EnumAddDocumentConflictAction::REPLACE,
      messages: ['message'],
    );
  }
}

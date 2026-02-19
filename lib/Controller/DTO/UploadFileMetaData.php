<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2025-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\Controller\DTO;

use InvalidArgumentException;

use OCA\CAFEVDB\Controller\EnumFileStorageBackend;
use OCA\CAFEVDB\Controller\EnumAddDocumentConflictAction;

/**
 * DTO document storage file uploads meta data.
 */
class UploadFileMetaData extends \OCA\CAFEVDB\Toolkit\DTO\AbstractDTO
{
  /** {@inheritdoc} */
  public function __construct(
    public readonly int $musicianId,
    public readonly int $projectId,
    // 'pathChain' => $pathChain, ?? needed ??
    public readonly string $dirName,
    public readonly string $baseName,
    public readonly string $extension,
    public readonly string $fileName,
    public readonly int $fileId,
    public readonly EnumFileStorageBackend $storageBackend,
    public readonly string $download,
    public readonly string $filesApp,
    public readonly ?EnumAddDocumentConflictAction $conflict,
    /** @var string[] */
    public readonly array $messages,
  ) {
  }

  /**
   * Create from a data array.
   *
   * @param array $data
   *
   * @return IBANMetatData
   *
   * @SuppressWarnings(PHPMD.UndefinedVariable)
   * @SuppressWarnings(PHPMD.UnusedLocalVariable)
   */
  public static function fromArray(array $data): UploadFileMetaData
  {
    static::initKeys();
    extract(array_intersect_key($data, array_flip(static::$keys[__CLASS__])));
    try {
      $storageBackend = EnumFileStorageBackend::get($storageBackend);
      if ($conflict !== null) {
        $conflict = EnumAddDocumentConflictAction::get($conflict);
      }
    } catch (InvalidArgumentException $e) {
      throw $e;
    }

    return new self(
      musicianId: $musicianId,
      projectId: $projectId,
      dirName: $dirName,
      baseName: $baseName,
      extension: $extension,
      fileName: $fileName,
      fileId: $fileId,
      storageBackend: $storageBackend,
      download: $download,
      filesApp: $filesApp,
      conflict: $conflict,
      messages: $messages,
    );
  }
}

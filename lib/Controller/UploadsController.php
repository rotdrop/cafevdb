<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2022, 2024-2026 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use Throwable;

use OCA\RotDrop\DevScripts\PhpToTypeScript;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants as CloudConstants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Simple upload end-points which move uploaded file to and from a temporary
 * location in the app-storage area.
 */
#[PhpToTypeScript\Attributes\TypeScript]
class UploadsController extends Controller
{
  use \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Wrapped\OCA\RotDrop\Toolkit\Traits\ResponseTrait;

  public const END_POINT_MOVE = 'upload/move';
  public const END_POINT_STASH = 'upload/stash';

  public const UPLOAD_KEY = 'files';

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    private AppStorage $appStorage,
    private UserStorage $userStorage,
    protected IL10N $l,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($appName, $request);
  }

  /**
   * @param string $stashedFile The stashed file-name in the app-storage area.
   *
   * @param string $destinationPath DOCME.
   *
   * @return JsonResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[Coreattributes\FrontpageRoute(
    verb: 'POST',
    url: Constants::PATH_SEP . self::END_POINT_MOVE . Constants::PATH_SEP . EnumFileStorageBackend::CLOUD->value,
  )]
  public function move(
    string $stashedFile,
    string $destinationPath,
  ): Http\JsonResponse {

    $uploadFolder = AppStorage::UPLOAD_FOLDER;

    $appFile = $this->appStorage->getFile($uploadFolder, $stashedFile);

    $this->userStorage->putContent($destinationPath, $appFile->getContent());
    $cloudFile = $this->userStorage->get($destinationPath);
    $downloadLink = $this->userStorage->getDownloadLink($destinationPath);
    $appFile->delete();

    return new DTO\FileUploadMoveResponse(
      messages: [$this->l->t('Moved "%s" to "%s".', [ $stashedFile, $destinationPath ])],
      fileName: basename($destinationPath),
      downloadLink: $downloadLink,
      fileId: $cloudFile->getId(),
    )->response();
  }

  /**
   * Stash-away upload data from cloud-files or file-system files for later usage.
   *
   * @param array<string> $cloudPaths File-names in the cloud storage. May be empty in
   * which case an ordinary upload is assumed.
   *
   * @param string|EnumFileUploadMode $uploadMode One of EnumFileUploadMode::COPY,
   * EnumFileUploadMode::MOVE, EnumFileUploadMode::LINK and
   * EnumFileUploadMode::TEST. This only applies to "uploads" from the cloud
   * file-space.
   *
   * - EnumFileUploadMode::COPY The default, just make and copy and generate a
   *   new file.
   *
   * - EnumFileUploadMode::MOVE This is like copy but removes the source. This is
   *   somewhat inefficient at it will generate an intermediate temporary
   *   file.
   *
   * - EnumFileUploadMode::LINK If the cloud file is backed by our db-storage
   *   then do not copy the source but instead link the existing
   *   file-entity. In this mode no temporary file generated, just the
   *   File-entity id is reported back to the caller
   *
   * - EnumFileUploadMode::TEST check what could be done and return the list of
   *   possible modes to the caller.
   *
   * @param string $uploadFolder The sub-folder in the app-storage containing
   * the stashed file.
   *
   * @return DataResponse
   */
  #[CoreAttributes\NoAdminRequired]
  #[CoreAttributes\FrontpageRoute(verb: 'POST', url: '/' . self::END_POINT_STASH)]
  public function stash(
    array $cloudPaths = [],
    string|EnumFileUploadMode $uploadMode = EnumFileUploadMode::COPY,
    string $uploadFolder = AppStorage::UPLOAD_FOLDER,
  ): DataResponse {
    $uploadMaxFileSize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $postMaxSize = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($uploadMaxFileSize, $postMaxSize);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    $uploadMode = EnumFileUploadMode::get($uploadMode);

    $uploads = [];
    if (!empty($cloudPaths)) {
      foreach ($cloudPaths as $path) {
        $files = [];

        /** @var OCP\Files\File $cloudFile */
        $cloudFile = $this->userStorage->getFile($path);

        /** @var Entities\DatabaseStorageFile $dbFile */
        $dbFile = $this->userStorage->getDatabaseFile($path);
        if (!empty($dbFile) && !($dbFile instanceof Entities\DatabaseStorageFile)) {
          return self::grumble($this->l->t('File "%s" does not exist or refers to a database storage folder, we expected a plain file.', $path));
        }
        if (empty($cloudFile)) {
          if ($this->userStorage->getFolder($path)) {
            return self::grumble($this->l->t('The path "%s/" refers to a folder. This is not yet implemented.', $path));
          }
          return self::grumble($this->l->t('File "%s" could not be found in cloud storage.', $path));
        }

        $fileName = $cloudFile->getName();
        $fileEntityId = !empty($dbFile) ? $dbFile->getFile()->getId() : null;

        switch ($uploadMode) {
          case EnumFileUploadMode::TEST:
            $uploadModes = [ EnumFileUploadMode::COPY, ];
            if ($cloudFile->getPermissions() & CloudConstants::PERMISSION_DELETE) {
              $uploadModes[] = EnumFileUploadMode::MOVE;
            }
            if (!empty($dbFile)) {
              $uploadModes[] = EnumFileUploadMode::LINK;
            }
            $uploads[] = new DTO\UploadModeTest(
              originalName: $path,
              availableUploadModes: $uploadModes,
            );
            continue 2;
          case EnumFileUploadMode::LINK:
            if (empty($dbFile)) {
              return self::grumble($this->l->t(
                'File "%s" is not backed by database-storage and thus cannot be linked.',
                $cloudFile->getName()
              ));
            }
            $originalName = $fileEntityId;
            $tmpName = null;
            break;
          case EnumFileUploadMode::MOVE:
            if (!($cloudFile->getPermissions() & CloudConstants::PERMISSION_DELETE)) {
              return self::grumble($this->l->t(
                'File "%s" cannot be deleted, moving it is therefor not possible.',
                $fileName
              ));
            }
            // the actual deletion should be post-poned until the stashed file
            // has been moved into place.
            // no break
          case EnumFileUploadMode::COPY:
            try {
              $uploadFile = $this->appStorage->newTemporaryFile($uploadFolder);
              $uploadFile->putContent($cloudFile->getContent());
            } catch (Throwable $t) {
              return self::grumble($this->l->t('Could not copy cloud file "%s" to upload storage.', $fileName));
            }
            $originalName = $uploadMode == EnumFileUploadMode::MOVE ? $path : $fileName;
            $tmpName = $uploadFile->getName();
            break;
        }

        // We emulate an uploaded file here:
        $fileRecord = [
          'name' => $fileName,
          'error' => UPLOAD_ERR_OK,
          'str_error' => Util::fileUploadError(UPLOAD_ERR_OK, $this->l),
          'tmp_name' => $tmpName,
          'type' => $cloudFile->getMimetype(),
          'size' => $cloudFile->getSize(),
          'upload_max_file_size' => $maxUploadFileSize,
          'max_human_file_size'  => $maxHumanFileSize,
          'upload_mode' => $uploadMode,
          'original_name' => $originalName,
        ];
        $uploads[] = DTO\UploadFileData::fromArray($fileRecord);
      }
    } else {
      if ($uploadMode != EnumFileUploadMode::COPY) {
        return self::grumble($this->l->t(
          'For client-uploads the only supported upload-mode is "copy", "%s" is not possible.',
          $uploadMode
        ));
      }

      $files = $this->request->files[self::UPLOAD_KEY];
      if (empty($files)) {
        // may be caused by PHP restrictions which are not caught by
        // error handlers.
        $contentLength = $this->request->server['CONTENT_LENGTH'];
        $limit = \OCP\Util::uploadLimit();
        if ($contentLength > $limit) {
          return self::grumble(
            $this->l->t('Upload size %s exceeds limit %s, contact your server administrator.', [
              \OCP\Util::humanFileSize($contentLength),
              \OCP\Util::humanFileSize($limit),
            ]));
        }
        $error = error_get_last();
        if (!empty($error)) {
          return self::grumble(
            $this->l->t('No file was uploaded, error message was "%s".', $error['message']));
        }
        return self::grumble($this->l->t('No file was uploaded. Unknown error'));
      }

      $files = Util::transposeArray($files);

      $totalSize = 0;
      foreach ($files as $file) {

        $totalSize += $file['size'];

        if ($maxUploadFileSize >= 0 and $totalSize > $maxUploadFileSize) {
          return self::grumble([
            'message' => $this->l->t('Not enough storage available'),
            'upload_max_file_size' => $maxUploadFileSize,
            'max_human_file_size' => $maxHumanFileSize,
          ]);
        }


        $file['str_error'] = Util::fileUploadError($file['error'], $this->l);
        if ($file['error'] != UPLOAD_ERR_OK) {
          continue;
        }

        $file['upload_max_file_size'] = $maxUploadFileSize;
        $file['max_human_file_size']  = $maxHumanFileSize;
        $file['original_name'] = $file['name']; // clone
        $file['upload_mode'] = EnumFileUploadMode::COPY;

        try {
          $uploadFile = $this->appStorage->newTemporaryFile($uploadFolder);
          $this->appStorage->moveFileSystemFile($file['tmp_name'], $uploadFile);
          $file['name'] = $uploadFile->getName();
          $file['tmp_name'] = $file['name'];
        } catch (Throwable $t) {
          $file['error'] = 99;
          $file['str_error'] = $this->l->t('Couldn\'t save temporary file for: %s', $file['name']);
          continue;
        }
        $uploads[] = DTO\UploadFileData::fromArray($file);
      }
    }
    return new DataResponse($uploads);
  }
}

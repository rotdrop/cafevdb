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

use Spatie\TypeScriptTransformer\Attributes as TSAttributes;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\DataResponse;
use OCP\Constants as CloudConstants;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\IRequest;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Simple upload end-points which move uploaded file to and from a temporary
 * location in the app-storage area.
 */
#[TSAttributes\TypeScript]
class UploadsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const END_POINT_MOVE = 'upload/move';
  public const END_POINT_STASH = 'upload/stash';

  public const UPLOAD_KEY = 'files';

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    protected ConfigService $configService,
    private AppStorage $appStorage,
    private UserStorage $userStorage,
  ) {

    parent::__construct($appName, $request);

    // $this->entityManager = null;
    $this->l = $this->l10N();
  }

  /**
   * @param string $stashedFile The stashed file-name in the app-storage area.
   * @param string $destinationPath DOCME.
   * @param string $uploadMode One of the upload-modes EnumFileUploadMode::COPY,
   *   EnumFileUploadMode::MOVE, self::UPLOAD_MODE link.
   * @param null|string $originalFileName The original upload file-name if any.
   * @param string $storage Either 'cloud' or 'db'. Route has default argument 'cloud'.
   * @param bool $encrypted Whether to store the data encrypted (DB only).
   * @param int $ownerId Musician-id of owner of encrypted file.
   * @param string $uploadFolder The sub-folder in the app-storage containing
   * the stashed file.
   *
   * @return DataResponse
   *
   * @throws Exceptions\EnduserNotificationException
   */
  #[CoreAttributes\NoAdminRequired]
  #[Coreattributes\FrontpageRoute(
    verb: 'POST',
    url: '/' . self::END_POINT_MOVE . '/{storage}',
    defaults: ['storage' => EnumFileStorageBackend::CLOUD->value],
  )]
  public function move(
    string $stashedFile,
    string $destinationPath,
    string $uploadMode = EnumFileUploadMode::COPY->value,
    ?string $originalFileName = null,
    string $storage = EnumFileStorageBackend::CLOUD->value,
    bool $encrypted = false,
    int $ownerId = 0,
    string $uploadFolder = AppStorage::UPLOAD_FOLDER
  ): Http\DataResponse|Http\JsonResponse {
    $uploadMOde = EnumFileUploadMode::get($uploadMOde);
    $storage = EnumFileStorageBackend::get($storage);
    if ($uploadMode == EnumFileUploadMode::MOVE) {
      if (empty($originalFileName)) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Original file path is not given, cannot move files.'),
        );
      }
      /** @var File $originalFile */
      $originalFile = $this->userStorage->getFile($originalFileName);
      if (empty($originalFile)) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('The original file "%s" cannot be found, cannot move files.', $originalFileName),
        );
      }
      if (!($originalFile->getPermissions() & CloudConstants::PERMISSION_DELETE)) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Original file "%s" cannot be deleted, moving it is therefore not possible.', $originalFileName),
        );
      }
      $originalFile->delete();
    }

    $appFile = $this->appStorage->getFile($uploadFolder, $stashedFile);
    switch ($storage) {
      case EnumFileStorageBackend::CLOUD:
        if ($uploadMode == EnumFileUploadMode::LINK) {
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Linking files is only support when the destination storage is backed by the data-base.'),
          );
        }

        $this->userStorage->putContent($destinationPath, $appFile->getContent());
        $downloadLink = $this->userStorage->getDownloadLink($destinationPath);
        $appFile->delete();

        return new DTO\FileUploadMoveResponse(
          messages: [$this->l->t('Moved "%s" to "%s".', [ $stashedFile, $destinationPath ])],
          fileName: basename($destinationPath),
          downloadLink: $downloadLink,
        )->response();

      case EnumFileStorageBackend::DB:
        // here $destinationPath is the file-name in the data-base
        if (empty($this->entityManager)) {
          $this->entityManager = $this->di(EntityManager::class);
        }

        $dbFileClass = $encrypted ? Entities\EncryptedFile::class : Entities\File::class;
        if ($uploadMode == EnumFileUploadMode::LINK) {
          // this is somewhat academic as here is no dedicate storage
          // location. However, this is how linking in principle works: just
          // increase the link-count.
          $dbFile = $this->entityManager->find(Entities\File::class, $originalFileName);
          if (empty($dbFile)) {
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Link source cannot be found.'),
            );
          }
          if ($encrypted && !($dbFile instanceof Entities\EncryptedFile)) {
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Encryption requested, but link-source "%s" is unencrypted', $dbFile->getName()),
            );
          }
        } else {
          /** @var Entities\EncryptedFile $dbFile */
          $dbFile = new $dbFileClass(
            fileName: $destinationPath,
            data: $appFile->getContent(),
            mimeType: $appFile->getMimeType()
          );
          $dbFile->setFileName($originalFileName);
        }
        if ($encrypted && $ownerId > 0) {
          $owner = $this->getDatabaseRepository(Entities\Musician::class)->find($ownerId);
          $dbFile->addOwner($owner);
        }

        $this->entityManager->beginTransaction();
        try {
          $this->persist($dbFile);
          $this->flush();
          $this->entityManager->commit();
        } catch (Throwable $t) {
          $this->logException($t);
          $this->entityManager->rollback();
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Unable to move "%1$s" to db-storage with name "%2$s".', [ $stashedFile, $destinationPath ]),
            previous: $t,
            httpStatusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
          );
        }

        // ok, all fine
        $appFile->delete();

        $downloadLink = $this->urlGenerator()->linkToRoute(
          $this->appName().'.downloads.get', [
            'section' => DownloadsController::SECTION_DATABASE,
            'object' => $dbFile->getId(),
          ])
          . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
          . '&fileName=' . urlencode(basename($destinationPath));

        return new DTO\FileUploadMoveResponse(
          messages: [$this->l->t('Moved "%1$s" to db-storage with name "%2$s", id %d.', [ $stashedFile, $destinationPath, $dbFile->getId() ])],
          fileName: basename($destinationPath),
          fileId: $dbFile->getId(),
          downloadLink:  $downloadLink,
        )->response();
    }
    throw new Exceptions\EnduserNotificationException(
      $this->l->t('Unknown request'),
    );
  }

  /**
   * Stash-away upload data from cloud-files or file-system files for later usage.
   *
   * @param array $cloudPaths File-names in the cloud storage. May be empty in
   * which case an ordinary upload is assumed.
   *
   * @param string $uploadMode One of EnumFileUploadMode::COPY, EnumFileUploadMode::MOVE,
   * EnumFileUploadMode::LINK and EnumFileUploadMode::TEST. This only applies
   * to "uploads" from the cloud file-space.
   *
   * - EnumFileUploadMode::COPY The default, just make and copy and generate a
   *   new file.
   *
   * - EnumFileUploadMode::MOVE This is like copy but removes the source. This is
       somewhat inefficient at it will generate an intermediate temporary
       file.
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
    string $uploadMode = EnumFileUploadMode::COPY->value,
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
        $cloudFile = $this->userStorage->get($path);

        /** @var Entities\DatabaseStorageFile $dbFile */
        $dbFile = $this->userStorage->getDatabaseFile($path);
        if (!empty($dbFile) && !($dbFile instanceof Entities\DatabaseStorageFile)) {
          return self::grumble($this->l->t('File "%s" referes to a database storage folder, we expected a plain file.', $path));
        }

        if (empty($cloudFile)) {
          return self::grumble($this->l->t('File "%s" could not be found in cloud storage.', $path));
        }
        if ($cloudFile->getType() != FileInfo::TYPE_FILE) {
          return self::grumble($this->l->t('File "%s" is not a plain file, this is not yet implemented.', $path));
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
          'error' => 0,
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

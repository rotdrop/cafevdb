<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;
use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Constants as CloudConstants;

use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\AppStorage;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/**
 * Simple upload end-point which moved uploaded file to a temporary
 * location in the app-storage area.
 */
class UploadsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const UPLOAD_KEY = 'files';
  public const MOVE_DEST_CLOUD = 'cloud';
  public const MOVE_DEST_DB = 'db';

  public const UPLOAD_MODE_TEST = 'test';
  public const UPLOAD_MODE_MOVE = 'move';
  public const UPLOAD_MODE_LINK = 'link';
  public const UPLOAD_MODE_COPY = 'copy';

  /** @var AppStorage */
  private $appStorage;

  /** @var UserStorage */
  private $userStorage;

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    ConfigService $configService,
    AppStorage $appStorage,
    UserStorage $userStorage,
  ) {

    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->appStorage = $appStorage;
    $this->userStorage = $userStorage;
    $this->entityManager = null;
    $this->l = $this->l10N();
  }

  /**
   * @param string $stashedFile The stashed file-name in the app-storage area.
   * @param string $destinationPath DOCME.
   * @param string $uploadMode One of the upload-modes self::UPLOAD_MODE_COPY,
   *   self::UPLOAD_MODE_MOVE, self::UPLOAD_MODE link.
   * @param null|string $originalFileName The original upload file-name if any.
   * @param string $storage Either 'cloud' or 'db'. Route has default argument 'cloud'.
   * @param bool $encrypted Whether to store the data encrypted (DB only).
   * @param int $ownerId Musician-id of owner of encrypted file.
   * @param string $uploadFolder The sub-folder in the app-storage containing
   * the stashed file.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function move(
    string $stashedFile,
    string $destinationPath,
    string $uploadMode = self::UPLOAD_MODE_COPY,
    ?string $originalFileName = null,
    string $storage = self::MOVE_DEST_CLOUD,
    bool $encrypted = false,
    int $ownerId = 0,
    string $uploadFolder = AppStorage::UPLOAD_FOLDER
  ):Response {
    if ($uploadMode == self::UPLOAD_MODE_MOVE) {
      if (empty($originalFileName)) {
        return self::grumble($this->l->t(
          'Original file path is not given, cannot move files.'
        ));
      }
      /** @var File $originalFile */
      $originalFile = $this->userStorage->getFile($originalFileName);
      if (empty($originalFile)) {
        return self::grumble($this->l->t(
          'The original file "%s" cannot be found, cannot move files.',
          $originalFileName
        ));
      }
      if (!($originalFile->getPermissions() & CloudConstants::PERMISSION_DELETE)) {
        return self::grumble($this->l->t(
          'Original file "%s" cannot be deleted, moving it is therefore not possible.',
          $originalFileName
        ));
      }
      $originalFile->delete();
    }

    $appFile = $this->appStorage->getFile($uploadFolder, $stashedFile);
    switch ($storage) {
      case self::MOVE_DEST_CLOUD:
        if ($uploadMode == self::UPLOAD_MODE_LINK) {
          return self::grumble($this->l->t(
            'Linking files is only support when the destination storage is backed by the data-base.'
          ));
        }

        $this->userStorage->putContent($destinationPath, $appFile->getContent());
        $downloadLink = $this->userStorage->getDownloadLink($destinationPath);
        $appFile->delete();

        return self::dataResponse([
          'message' => $this->l->t('Moved "%s" to "%s".', [ $stashedFile, $destinationPath ]),
          'fileName' => basename($destinationPath),
          'downloadLink' => $downloadLink,
        ]);
      case self::MOVE_DEST_DB:
        // here $destinationPath is the file-name in the data-base
        if (empty($this->entityManager)) {
          $this->entityManager = $this->di(EntityManager::class);
        }

        $dbFileClass = $encrypted ? Entities\EncryptedFile::class : Entities\File::class;
        if ($uploadMode == self::UPLOAD_MODE_LINK) {
          // this is somewhat academic as here is no dedicate storage
          // location. However, this is how linking in principle works: just
          // increase the link-count.
          $dbFile = $this->entityManager->find(Entities\File::class, $originalFileName);
          if (empty($dbFile)) {
            return self::grumble($this->l->t('Link source cannot be found.'));
          }
          if ($encrypted && !($dbFile instanceof Entities\EncryptedFile)) {
            return self::grumble($this->l->t(
              'Encryption requested, but link-source "%s" is unencrypted',
              $dbFile->getName()
            ));
          }
          $dbFile->link(); // increase the link-count
        } else {
          /** @var Entities\EncryptedFile $dbFile */
          $dbFile = new $dbFileClass(
            fileName: $destinationPath,
            data: $appFile->getContent(),
            mimeType: $appFile->getMimeType()
          );
          $dbFile->setOriginalFileName($originalFileName);
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
        } catch (\Throwable $t) {
          $this->logException($t);
          $this->entityManager->rollback();
          return self::grumble($this->exceptionChainData($t));
        }

        // ok, all fine
        $appFile->delete();

        $downloadLink = $this->urlGenerator()->linkToRoute(
          $this->appName().'.downloads.get', [
            'section' => 'database',
            'object' => $dbFile->getId(),
          ])
          . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
          . '&fileName=' . urlencode(basename($destinationPath));

        return self::dataResponse([
          'message' => $this->l->t('Moved "%1$s" to db-storage with name "%2$s", id %d.', [ $stashedFile, $destinationPath, $dbFile->getId() ]),
          'fileName' => basename($destinationPath),
          'fileId' => $dbFile->getId(),
          'downloadLink' => $downloadLink,
        ]);
    }
    return self::grumble($this->l->t('Unknown request'));
  }

  /**
   * Stash-away upload data from cloud-files or file-system files for later usage.
   *
   * @param array $cloudPaths File-names in the cloud storage. May be empty in
   * which case an ordinary upload is assumed.
   *
   * @param string $uploadMode One of self::UPLOAD_MODE_COPY, self::UPLOAD_MODE_MOVE,
   * self::UPLOAD_MODE_LINK and self::UPLOAD_MODE_TEST. This only applies
   * to "uploads" from the cloud file-space.
   *
   * - self::UPLOAD_MODE_COPY The default, just make and copy and generate a
   *   new file.
   *
   * - self::UPLOAD_MODE_MOVE This is like copy but removes the source. This is
       somewhat inefficient at it will generate an intermediate temporary
       file.
   *
   * - self::UPLOAD_MODE_LINK If the cloud file is backed by our db-storage
   *   then do not copy the source but instead link the existing
   *   file-entity. In this mode no temporary file generated, just the
   *   File-entity id is reported back to the caller
   *
   * - self::UPLOAD_MODE_TEST check what could be done and return the list of
   *   possible modes to the caller.
   *
   * @param string $uploadFolder The sub-folder in the app-storage containing
   * the stashed file.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function stash(
    array $cloudPaths = [],
    string $uploadMode = self::UPLOAD_MODE_COPY,
    string $uploadFolder = AppStorage::UPLOAD_FOLDER,
  ):Response {
    $uploadMaxFileSize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $postMaxSize = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($uploadMaxFileSize, $postMaxSize);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    $uploads = [];
    if (!empty($cloudPaths)) {
      foreach ($cloudPaths as $path) {
        $files = [];

        /** @var OCP\Files\File $cloudFile */
        $cloudFile = $this->userStorage->get($path);

        /** @var Entities\EncryptedFile $dbFile */
        $dbFile = $this->userStorage->getDatabaseFile($path);

        if (empty($cloudFile)) {
          return self::grumble($this->l->t('File "%s" could not be found in cloud storage.', $path));
        }
        if ($cloudFile->getType() != FileInfo::TYPE_FILE) {
          return self::grumble($this->l->t('File "%s" is not a plain file, this is not yet implemented.'));
        }

        $fileName = $cloudFile->getName();
        $fileEntityId = !empty($dbFile) ? $dbFile->getId() : null;

        switch ($uploadMode) {
          case self::UPLOAD_MODE_TEST:
            $uploadModes = [ self::UPLOAD_MODE_COPY, ];
            if ($cloudFile->getPermissions() & CloudConstants::PERMISSION_DELETE) {
              $uploadModes[] = self::UPLOAD_MODE_MOVE;
            }
            if (!empty($dbFile)) {
              $uploadModes[] = self::UPLOAD_MODE_LINK;
            }
            $uploads[] = [
              'original_name' => $path,
              'upload_mode' => $uploadModes,
            ];
            continue 2;
          case self::UPLOAD_MODE_LINK:
            if (empty($dbFile)) {
              return self::grumble($this->l->t(
                'File "%s" is not backed by database-storage and thus cannot be linked.',
                $cloudFile->getName()
              ));
            }
            $originalName = $fileEntityId;
            $tmpName = null;
            break;
          case self::UPLOAD_MODE_MOVE:
            if (!($cloudFile->getPermissions() & CloudConstants::PERMISSION_DELETE)) {
              return self::grumble($this->l->t(
                'File "%s" cannot be deleted, moving it is therefor not possible.',
                $fileName
              ));
            }
            // the actual deletion should be post-poned until the stashed file
            // has been moved into place.
            // no break
          case self::UPLOAD_MODE_COPY:
            try {
              $uploadFile = $this->appStorage->newTemporaryFile($uploadFolder);
              $uploadFile->putContent($cloudFile->getContent());
            } catch (\Throwable $t) {
              return self::grumble($this->l->t('Could not copy cloud file "%s" to upload storage.', $fileName));
            }
            $originalName = $uploadMode == self::UPLOAD_MODE_MOVE ? $path : $fileName;
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
        $uploads[] = $fileRecord;
      }
    } else {
      if ($uploadMode != self::UPLOAD_MODE_COPY) {
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
        $file['upload_mode'] = self::UPLOAD_MODE_COPY;

        try {
          $uploadFile = $this->appStorage->newTemporaryFile($uploadFolder);
          $this->appStorage->moveFileSystemFile($file['tmp_name'], $uploadFile);
          $file['name'] = $uploadFile->getName();
          $file['tmp_name'] = AppStorage::PATH_SEP.$uploadFolder.AppStorage::PATH_SEP.$file['name'];
        } catch (\Throwable $t) {
          $file['error'] = 99;
          $file['str_error'] = $this->l->t('Couldn\'t save temporary file for: %s', $file['name']);
          continue;
        }
        $uploads[] = $file;
      }
    }
    return self::dataResponse($uploads);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

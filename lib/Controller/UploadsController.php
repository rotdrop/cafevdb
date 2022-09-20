<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IL10N;
use OCP\Files\FileInfo;

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
class UploadsController extends Controller {
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public const UPLOAD_KEY = 'files';
  public const MOVE_DEST_CLOUD = 'cloud';
  public const MOVE_DEST_DB = 'db';

  /** @var AppStorage */
  private $appStorage;

  public function __construct(
    $appName
    , IRequest $request
    , ConfigService $configService
    , AppStorage $appStorage
  ) {

    parent::__construct($appName, $request);

    $this->configService = $configService;
    $this->appStorage = $appStorage;
    $this->entityManager = null;
    $this->l = $this->l10N();
  }

  /**
   * @NoAdminRequired
   *
   * @param string $stashedFile
   * @param string $destinationPath
   * @param null|string $originalFileName
   * @param string $storage Either 'cloud' or 'db'. Route has default argument 'cloud'.
   * @param bool $encrypted Whether to store the data encrypted (DB only).
   * @param int $ownerId Musician-id of owner of encrypted file
   * @param string $appDirectory
   */
  public function move(string $stashedFile, string $destinationPath, ?string $originalFileName = null, string $storage = self::MOVE_DEST_CLOUD, bool $encrypted = false, int $ownerId = 0, string $appDirectory = AppStorage::UPLOAD_FOLDER)
  {
    $appFile = $this->appStorage->getFile($appDirectory, $stashedFile);
    switch ($storage) {
      case self::MOVE_DEST_CLOUD:
        /** @var UserStorage $userStorage */
        $userStorage = $this->di(UserStorage::class);

        $userStorage->putContent($destinationPath, $appFile->getContent());
        $downloadLink = $userStorage->getDownloadLink($destinationPath);
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
        /** @var Entities\EncryptedFile $dbFile */
        $dbFileClass = $encrypted ? Entities\EncryptedFile::class : Entities\File::class;
        $dbFile = new $dbFileClass(
          fileName: $destinationPath,
          data: $appFile->getContent(),
          mimeType: $appFile->getMimeType()
        );
        if ($encrypted && $ownerId > 0) {
          $owner = $this->getDatabaseRepository(Entities\Musician::class)->find($ownerId);
          $dbFile->addOwner($owner);
        }
        $dbFile->setOriginalFileName($originalFileName);

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
   * @NoAdminRequired
   */
  public function stash($cloudPaths = [], $appDirectory = AppStorage::UPLOAD_FOLDER)
  {
    $upload_max_filesize = \OCP\Util::computerFileSize(ini_get('upload_max_filesize'));
    $post_max_size = \OCP\Util::computerFileSize(ini_get('post_max_size'));
    $maxUploadFileSize = min($upload_max_filesize, $post_max_size);
    $maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);

    $uploads = [];
    if (!empty($cloudPaths)) {
      foreach ($cloudPaths as $path) {
        /** @var UserStorage $storage */
        $storage = $this->di(UserStorage::class);
        $files = [];

        /** @var OCP\Files\File $cloudFile */
        $cloudFile = $storage->get($path);
        if (empty($cloudFile)) {
          return self::grumble($this->l->t('File "%s" could not be found in cloud storage.', $path));
        }
        if ($cloudFile->getType() != FileInfo::TYPE_FILE) {
          return self::grumble($this->l->t('File "%s" is not a plain file, this is not yet implemented.'));
          }

        try {
          $uploadFile = $this->appStorage->newTemporaryFile($appDirectory);
          $uploadFile->putContent($cloudFile->getContent());
        } catch (\Throwable $t) {
          return self::grumble($this->l->t('Could not copy cloud file to upload storage.'));
        }

        // We emulate an uploaded file here:
        $fileRecord = [
          'name' => $uploadFile->getName(),
          'cloud_path' => $path, // additional info if maybe someone needs to know
          'original_name' => $cloudFile->getName(),
          'error' => 0,
          'tmp_name' => $uploadFile->getName(),
          'type' => $cloudFile->getMimetype(),
          'size' => $cloudFile->getSize(),
          'upload_max_file_size' => $maxUploadFileSize,
          'max_human_file_size'  => $maxHumanFileSize,
        ];
        $uploads[] = $fileRecord;
      }
    } else {
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

        $file['upload_max_file_size'] = $maxUploadFileSize;
        $file['max_human_file_size']  = $maxHumanFileSize;
        $file['original_name'] = $file['name']; // clone

        $file['str_error'] = Util::fileUploadError($file['error'], $this->l);
        if ($file['error'] != UPLOAD_ERR_OK) {
          continue;
        }

        try {
          $uploadFile = $this->appStorage->newTemporaryFile($appDirectory);
          $this->appStorage->moveFileSystemFile($file['tmp_name'], $uploadFile);
          $file['name'] = $uploadFile->getName();
          $file['tmp_name'] = AppStorage::PATH_SEP.$appDirectory.AppStorage::PATH_SEP.$file['name'];
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

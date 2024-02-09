<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use \PHP_IBAN\IBAN;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\UniqueConstraintViolationException;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Files\SimpleFS\ISimpleFile;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\TaxExemptionNotice as Entity;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Common\Util;

/** AJAX endpoint to support maintenance of tax exemption notices. */
class TaxExemptionNoticesController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Controller\FileUploadRowTrait;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;

  public const DOCUMENT_ACTION_UPLOAD = 'upload';
  public const DOCUMENT_ACTION_DELETE = 'delete';

  /** @var ReqeuestParameterService */
  private $parameterService;

  /** @var StorageFactory */
  private $storageFactory;

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    EntityManager $entityManager,
    StorageFactory $storageFactory,
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->storageFactory = $storageFactory;
    $this->l = $this->l10N();
  }

  /**
   * @param string $operation One of self::DOCUMENT_ACTION_UPLOAD or self::DOCUMENT_ACTION_DELETE.
   *
   * @param null|int $entityId The id of the database entity.
   *
   * @param string $data File upload data if this is a file-upload.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function documents(
    string $operation,
    ?int $entityId,
    string $data = '{}'
  ):Response {
    switch ($operation) {
      case self::DOCUMENT_ACTION_UPLOAD:
        // we mis-use the participant-data upload form, so the actual identifiers
        // are in the "data" parameter and have to be remapped.
        $uploadData = json_decode($data, true);
        $entityId = $uploadData['fieldId'];
        $fileName = $uploadData['fileName'];
        $files = $this->parameterService['files'];
        $filesAppPath = $uploadData['filesAppPath']??null;
        break;
      case self::DOCUMENT_ACTION_DELETE:
        $entityId = $this->parameterService['fieldId'];
        break;
    }

    $requiredKeys = [ 'entityId' ];
    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t('Required information "%s" not provided.', $required));
      }
    }

    /** @var Entity $entity */
    $entity = $this->findEntity(Entity::class, $entityId);

    if (empty($entity)) {
      return self::grumble($this->l->t('Unable to find the tax exemption notice with id "%1$d".', [ $entityId ]));
    }

    switch ($operation) {
      case self::DOCUMENT_ACTION_UPLOAD:

        $files = $this->prepareUploadInfo($files, $entityId, multiple: false);
        if ($files instanceof Http\Response) {
          // error generated
          return $files;
        }

        $file = array_shift($files); // only one
        if ($file['error'] != UPLOAD_ERR_OK) {
          return self::grumble($this->l->t('Upload error "%s".', $file['str_error']));
        }

        /** @var UserStorage $userStorage */
        $userStorage = $this->di(UserStorage::class);

        $originalFilePath = $file['original_name'] ?? null;
        $uploadMode = $file['upload_mode'] ?? UploadsController::UPLOAD_MODE_COPY;

        switch ($uploadMode) {
          case UploadsController::UPLOAD_MODE_MOVE:
            if (empty($originalFilePath)) {
              return self::grumble($this->l->t('Move operation requested, but the original file path has not been specified.'));
            }
            $originalFile = $userStorage->get($originalFilePath);
            if (empty($originalFile)) {
              return self::grumble($this->l->t('Move operation requested, but the original file "%s" cannot be found.', $originalFilePath));
            }
            break;
          case UploadsController::UPLOAD_MODE_LINK:
            $originalFileId = $file['original_name']; // ?? check if this is correct
            if (empty($originalFileId)) {
              return self::grumble($this->l->t('Link operation requested, but the id of the original file has not been specified.'));
            }
            $originalFile = $this->entityManager->find(Entities\File::class, $originalFileId);
            if (empty($originalFile)) {
              return self::grumble($this->l->t('Link operation requested, but the existing original file with id "%s" cannot be found.', $originalFileId));
            }
            $originalFilePath = $originalFile->getFileName();
            break;
          case UploadsController::UPLOAD_MODE_COPY:
            // this is the default, nothing special
            break;
        }

        $originalFileName = $originalFilePath ? basename($originalFilePath) : null;

        /** @var Entities\DatabaseStorageFile $databaseFile */
        $fileNodeEntity = $entity->getWrittenNotice();
        $fileEntity = $fileNodeEntity ? $fileNodeEntity->getFile() : null;

        $conflict = null;

        $this->entityManager->beginTransaction();
        try {

          $storage = $this->storageFactory->getTaxExemptionNoticesStorage();

          switch ($uploadMode) {
            case UploadsController::UPLOAD_MODE_MOVE:
              $this->entityManager->registerPreCommitAction(new Common\UndoableFileRemove($originalFilePath, gracefully: true));
              // no break
            case UploadsController::UPLOAD_MODE_COPY:
              $fileContent = $this->getUploadContent($file);

              /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
              $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
              $mimeType = $mimeTypeDetector->detectString($fileContent);

              if (!empty($fileEntity) && $fileEntity->getNumberOfLinks() > 1) {
                // if the file has multiple links then it is probably
                // better to remove the existing file rather than
                // overwriting a file which has multiple links.
                $fileNodeEntity->setFile(null); // unlink
                $fileEntity = null;
              }

              if (empty($fileEntity)) {
                $fileEntity = new Entities\EncryptedFile(
                  data: $fileContent,
                  mimeType: $mimeType,
                  owner: null,
                );
                $this->persist($fileEntity);
              } else {
                $conflict = 'replaced';
                $fileEntity
                  ->setMimeType($mimeType)
                  ->setSize(strlen($fileContent))
                  ->getFileData()->setData($fileContent);
              }
              $fileEntity->setFileName($originalFileName);

              break;
            case UploadsController::UPLOAD_MODE_LINK:
              $fileContent = null;
              /** @var Entities\EncryptedFile $originalFile */
              if (!empty($fileEntity) && $fileEntity->getId() == $originalFileId) {
                return self::grumble($this->l->t('Link operation requested, but the existing original file is the same as the target destination (%s@%s)', [
                  $originalFile->getFileName(), $originalFileId
                ]));
              }
              $fileEntity = $originalFile;
              break;
          }

          $mimeType = $fileEntity->getMimeType();
          if (!empty($fileNodeEntity)) {
            $fileNodeEntity->setFile($fileEntity);
          } else {
            $fileNodeEntity = $storage->addDocument($entity, $fileEntity, flush: false);
            $entity->setWrittenNotice($fileNodeEntity);
          }

          $this->flush();

          $fileName = $fileNodeEntity->getName();

          $this->entityManager->commit();
        } catch (\Throwable $t) {
          $this->logException($t);
          $this->entityManager->rollback();
          $exceptionChain = $this->exceptionChainData($t);
          $exceptionChain['message'] =
            $this->l->t('Error, caught an exception. No changes were performed.');
          return self::grumble($exceptionChain);
        }

        if ($uploadMode != UploadsController::UPLOAD_MODE_LINK) {
          $this->removeStashedFile($file);
        }

        $downloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
          'section' => 'database',
          'object' => $fileNodeEntity->getId(),
        ])
          . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
          . '&fileName=' . urlencode($fileName);

        $filesAppLink = '';
        try {
          if (!empty($filesAppPath)) {
            $filesAppLink = $userStorage->getFilesAppLink($filesAppPath, true);
          }
        } catch (\Throwable $t) {
          $this->logException($t, 'Unable to get files-app link for ' . $filesAppPath);
        }

        unset($file['tmp_name']);

        switch ($uploadMode) {
          case UploadsController::UPLOAD_MODE_COPY:
            $message = $this->l->t('Upload of "%s" as "%s" successful.', [ $file['name'], $fileName ]);
            break;
          case UploadsController::UPLOAD_MODE_MOVE:
            $message = $this->l->t('Move of "%s" to "%s" successful.', [ $originalFilePath, $fileName ]);
            break;
          case UploadsController::UPLOAD_MODE_LINK:
            $message = $this->l->t('Linking of file id "%s" to "%s" successful.', [ $originalFileId, $fileName ]);
            break;
        }
        $file['message'] = $message;
        $file['name'] = $fileName;

        $pathInfo = pathinfo($fileName);

        $file['meta'] = [
          'musicianId' => -1,
          'projectId' => -1,
          // 'pathChain' => $pathChain, ?? needed ??
          'dirName' => $pathInfo['dirname'],
          'baseName' => $pathInfo['basename'],
          'extension' => $pathInfo['extension']?:'',
          'fileName' => $pathInfo['filename'],
          'download' => $downloadLink,
          'filesApp' => $filesAppLink,
          'conflict' => $conflict,
          'messages' => $message,
        ];

        return self::dataResponse([ $file ]);
      case self::DOCUMENT_ACTION_DELETE:
        $fileNodeEntity = $entity->getWrittenNotice();
        if (empty($fileNodeEntity)) {
          // ok, it is not there ...
          return self::response($this->l->t('We have no supporting document for the entity "%1$s", so we cannot delete it.', (string)$entity));
        }

        $this->entityManager->beginTransaction();
        try {
          // ok, delete it
          $entity->setWrittenNotice(null);
          $this->remove($fileNodeEntity, flush: true);

          $this->entityManager->commit();

        } catch (\Throwable $t) {
          $this->logException($t);
          $this->entityManager->rollback();
          $exceptionChain = $this->exceptionChainData($t);
          $exceptionChain['message'] =
            $this->l->t('Error, caught an exception. No changes were performed.');
          return self::grumble($exceptionChain);
        }

        return self::response($this->l->t('Successfully deleted the written document for the entity "%1$s", please upload a new one!', (string)$entity));
    }
    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }
}

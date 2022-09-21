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

use \PHP_IBAN\IBAN;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\UniqueConstraintViolationException;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Storage\UserStorage;
use OCP\Files\SimpleFS\ISimpleFile;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Common\Util;

/** AJAX endpoint to support maintenance of payments. */
class PaymentsController extends Controller
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Controller\FileUploadRowTrait;

  public const DOCUMENT_ACTION_UPLOAD = 'upload';
  public const DOCUMENT_ACTION_DELETE = 'delete';

  /** @var ReqeuestParameterService */
  private $parameterService;

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    RequestParameterService $parameterService,
    ConfigService $configService,
    EntityManager $entityManager,
  ) {
    parent::__construct($appName, $request);
    $this->parameterService = $parameterService;
    $this->configService = $configService;
    $this->entityManager = $entityManager;
    $this->l = $this->l10N();
  }

  /**
   * @param string $operation One of self::DOCUMENT_ACTION_UPLOAD or self::DOCUMENT_ACTION_DELETE.
   *
   * @param null|int $musicianId The musician to work on, null if this is a file-upload.
   *
   * @param int $projectId The project to work on. This is ATM just
   * passed-through to the return value.
   *
   * @param null|int $compositePaymentId The payment to work on, null if this
   * is a file-upload.
   *
   * @param string $data File upload data if this is a file-upload.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function documents(
    string $operation,
    ?int $musicianId,
    int $projectId,
    ?int $compositePaymentId,
    string $data = '{}'
  ):Response {
    switch ($operation) {
      case self::DOCUMENT_ACTION_UPLOAD:
        // we mis-use the participant-data upload form, so the actual identifiers
        // are in the "data" parameter and have to be remapped.
        $uploadData = json_decode($data, true);
        $musicianId = $uploadData['fieldId'];
        $compositePaymentId = $uploadData['optionKey'];
        $supportingDocumentFileName = $uploadData['fileName'];
        $files = $this->parameterService['files'];
        $filesAppPath = $uploadData['filesAppPath']??null;
        break;
      case self::DOCUMENT_ACTION_DELETE:
        $compositePaymentId = $this->parameterService['optionKey'];
        break;
    }

    $requiredKeys = [ 'musicianId', 'compositePaymentId' ];
    foreach ($requiredKeys as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t('Required information "%s" not provided.', $required));
      }
    }

    /** @var Entities\CompositePayment $compositePayment */
    $compositePayment = $this->findEntity(Entities\CompositePayment::class, $compositePaymentId);

    if (empty($compositePayment)) {
      return self::grumble($this->l->t('Unable to find composite-payment for musician id "%1$d" with payment id "%2$d".', [ $musicianId, $compositePaymentId ]));
    }

    switch ($operation) {
      case self::DOCUMENT_ACTION_UPLOAD:

        $files = $this->prepareUploadInfo($files, $compositePaymentId, multiple: false);
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
            $originalFileId = $file['original_name'];
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

        /** @var Entities\EncryptedFile $supportingDocument */
        $supportingDocument = $compositePayment->getSupportingDocument();

        $conflict = null;

        $this->entityManager->beginTransaction();
        try {

          switch ($uploadMode) {
            case UploadsController::UPLOAD_MODE_MOVE:
              $this->entityManager->registerPreCommitAction(new Common\UndoableFileRemove($originalFilePath, gracefully: true));
              // no break
            case UploadsController::UPLOAD_MODE_COPY:
              $fileContent = $this->getUploadContent($file);

              /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
              $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
              $mimeType = $mimeTypeDetector->detectString($fileContent);

              if (!empty($supportingDocument) && $supportingDocument->getNumberOfLinks() > 1) {
                // if the file has multiple links then it is probably
                // better to remove the existing file rather than
                // overwriting a file which has multiple links.
                if (!empty($supportingDocument->getOriginalFileName())) {
                  // undo greedily modified filename
                  $supportingDocument->setFileName($supportingDocument->getOriginalFileName());
                }
                $compositePayment->setSupportingDocument(null); // will decrease the link-count
                $supportingDocument = null;
              }

              if (empty($supportingDocument)) {
                $supportingDocument = new Entities\EncryptedFile(
                  data: $fileContent,
                  mimeType: $mimeType,
                  owner: $compositePayment->getMusician()
                );
              } else {
                $conflict = 'replaced';
                $supportingDocument
                  ->setMimeType($mimeType)
                  ->setSize(strlen($fileContent))
                  ->getFileData()->setData($fileContent);
              }
              $supportingDocument->setOriginalFileName($originalFileName);

              break;
            case UploadsController::UPLOAD_MODE_LINK:
              $fileContent = null;
              /** @var Entities\EncryptedFile $originalFile */
              if (!empty($supportingDocument) && $supportingDocument->getId() == $originalFileId) {
                return self::grumble($this->l->t('Link operation requested, but the existing original file is the same as the target destination (%s@%s)', [
                  $originalFile->getFileName(), $originalFileId
                ]));
              }
              if (!empty($supportingDocument)) {
                if ($supportingDocument->getNumberOfLinks() == 0) {
                  $this->remove($supportingDocument, flush: true);
                } elseif (!empty($supportingDocument->getOriginalFileName())) {
                  // undo greedily modified filename
                  $supportingDocument->setFileName($supportingDocument->getOriginalFileName());
                }
              }
              $supportingDocument = $originalFile;
              $mimeType = $supportingDocument->getMimeType();
              break;
          }

          // somewhat questionable, as this way even when linking the
          // supporting document file-name will take precedence over the
          // "real" file-name
          $supportingDocumentFileName = basename($supportingDocumentFileName);
          $extension = Util::fileExtensionFromMimeType($mimeType);
          if (empty($extension) && $file['name']) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
          }
          if (!empty($extension)) {
            $supportingDocumentFileName = pathinfo($supportingDocumentFileName, PATHINFO_FILENAME) . '.' . $extension;
          }
          $originalFileName = $supportingDocument->getFileName();
          if (!empty($originalFileName) && $originalFileName != $supportingDocumentFileName) {
            $supportingDocument->setOriginalFileName($originalFileName);
          }
          $supportingDocument->setFileName($supportingDocumentFileName);

          $this->persist($supportingDocument);
          $compositePayment->setSupportingDocument($supportingDocument);
          $this->flush();

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
          'object' => $supportingDocument->getId(),
        ])
          . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
          . '&fileName=' . urlencode($supportingDocumentFileName);

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
            $message = $this->l->t('Upload of "%s" as "%s" successful.', [ $file['name'], $supportingDocumentFileName ]);
            break;
          case UploadsController::UPLOAD_MODE_MOVE:
            $message = $this->l->t('Move of "%s" to "%s" successful.', [ $originalFilePath, $supportingDocumentFileName ]);
            break;
          case UploadsController::UPLOAD_MODE_LINK:
            $message = $this->l->t('Linking of file id "%s" to "%s" successful.', [ $originalFileId, $supportingDocumentFileName ]);
            break;
        }
        $file['message'] = $message;
        $file['name'] = $supportingDocumentFileName;

        $pathInfo = pathinfo($supportingDocumentFileName);

        $file['meta'] = [
          'musicianId' => $musicianId,
          'projectId' => $projectId,
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
        $supportingDocument = $compositePayment->getSupportingDocument();
        if (empty($supportingDocument)) {
          // ok, it is not there ...
          return self::response($this->l->t('We have no supporting document for the payment "%1$s", so we cannot delete it.', $compositePaymentId));
        }

        // ok, delete it
        $compositePayment->setSupportingDocument(null);
        if ($supportingDocument->getNumberOfLinks() == 0) {
          $this->remove($supportingDocument, flush: true);
        } elseif (!empty($supportingDocument->getOriginalFileName())) {
          $supportingDocument->setFileName($supportingDocument->getOriginalFileName());
          $this->flush();
        }

        return self::response($this->l->t('Successfully deleted the supporting document for the payment "%1$s", please upload a new one!', $compositePaymentId));
    }
    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

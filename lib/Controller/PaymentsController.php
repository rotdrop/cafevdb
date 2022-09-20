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

use \PHP_IBAN\IBAN;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\UniqueConstraintViolationException;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Storage\UserStorage;
use OCP\Files\SimpleFS\ISimpleFile;

use OCA\CAFEVDB\Common\Util;

class PaymentsController extends Controller {
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Controller\FileUploadRowTrait;

  public const DOCUMENT_ACTION_UPLOAD = 'upload';
  public const DOCUMENT_ACTION_DELETE = 'delete';

  /** @var ReqeuestParameterService */
  private $parameterService;

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
   * @NoAdminRequired
   */
  public function documents($operation, $musicianId, $projectId, $compositePaymentId, string $data = '{}')
  {
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
        // the following should be made a service routine or Trait

        $files = $this->prepareUploadInfo($files, $compositePaymentId, multiple: false);
        if ($files instanceof Http\Response) {
          // error generated
          return $files;
        }

        $file = array_shift($files); // only one
        if ($file['error'] != UPLOAD_ERR_OK) {
          return self::grumble($this->l->t('Upload error "%s".', $file['str_error']));
        }

        // Ok, got it, set or replace the hard-copy file
        $fileContent = $this->getUploadContent($file);

        /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
        $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
        $mimeType = $mimeTypeDetector->detectString($fileContent);

        $conflict = null;
        /** @var Entities\EncryptedFile $supportingDocument */
        $supportingDocument = $compositePayment->getSupportingDocument();
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
        $supportingDocument->setOriginalFileName($file['original_name'] ?? null);

        $supportingDocumentFileName = basename($supportingDocumentFileName);
        $extension = Util::fileExtensionFromMimeType($mimeType);
        if (empty($extension) && $file['name']) {
          $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        }
        if (!empty($extension)) {
          $supportingDocumentFileName = pathinfo($supportingDocumentFileName, PATHINFO_FILENAME) . '.' . $extension;
        }

        $supportingDocument->setFileName($supportingDocumentFileName);

        $this->entityManager->beginTransaction();
        try {
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

        $this->removeStashedFile($file);

        $downloadLink = $this->urlGenerator()->linkToRoute($this->appName().'.downloads.get', [
          'section' => 'database',
          'object' => $supportingDocument->getId(),
        ])
          . '?requesttoken=' . urlencode(\OCP\Util::callRegister())
          . '&fileName=' . urlencode($supportingDocumentFileName);

        $filesAppLink = '';
        try {
          if (!empty($filesAppPath)) {
            /** @var UserStorage $userStorage */
            $userStorage = $this->di(UserStorage::class);
            $filesAppLink = $userStorage->getFilesAppLink($filesAppPath, true);
            }
        } catch (\Throwable $t) {
          $this->logException($t, 'Unable to get files-app link for ' . $filesAppPath);
        }

        unset($file['tmp_name']);
        $file['message'] = $this->l->t('Upload of "%s" as "%s" successful.',
                                       [ $file['name'], $supportingDocumentFileName ]);
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
          'messages' => $file['message'],
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
        $this->remove($supportingDocument, flush: true);

        return self::response($this->l->t('Successfully deleted the supporting document for the payment "%1$s", please upload a new one!', $compositePaymentId));
    }
    return self::grumble($this->l->t('UNIMPLEMENTED'));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

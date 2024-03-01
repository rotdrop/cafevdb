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

use UnexpectedValueException;

use \PHP_IBAN\IBAN;
use OCA\CAFEVDB\Wrapped\Doctrine\DBAL\Exception\UniqueConstraintViolationException;

use OCP\AppFramework\Controller;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Response;
use OCP\Files\SimpleFS\ISimpleFile;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\RequestParameterService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Storage\UserStorage;
use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;
use OCA\CAFEVDB\Storage\Database\Storage as DatabaseStorage;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Common\Util;

/** AJAX endpoint to support maintenance of tax exemption notices. */
class DocumentStorageUploadController extends Controller
{
  use \OCA\CAFEVDB\Toolkit\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Controller\FileUploadRowTrait;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;

  public const DOCUMENT_ACTION_UPLOAD = 'upload';
  public const DOCUMENT_ACTION_DELETE = 'delete';

  public const SECTION_FINANCE = 'finance';

  public const FINANCE_TOPIC_PAYMENTS = 'project-payments';
  public const FINANCE_TOPIC_EXEMPTION_NOTICES = 'tax-exemption-notices';
  public const FINANCE_TOPIC_DONATION_RECEIPTS = 'donation-receipts';

  public const TOPICS = [
    self::SECTION_FINANCE => [
      self::FINANCE_TOPIC_DONATION_RECEIPTS,
      self::FINANCE_TOPIC_EXEMPTION_NOTICES,
      self::FINANCE_TOPIC_PAYMENTS,
    ],
  ];

  private const ENTITIES = [
    self::SECTION_FINANCE => [
      self::FINANCE_TOPIC_DONATION_RECEIPTS => Entities\DonationReceipt::class,
      self::FINANCE_TOPIC_EXEMPTION_NOTICES => Entities\TaxExemptionNotice::class,
      self::FINANCE_TOPIC_PAYMENTS => Entities\CompositePayment::class,
    ],
  ];

  private const REQUIRED = [
    self::SECTION_FINANCE => [
      self::FINANCE_TOPIC_DONATION_RECEIPTS => [ 'entityId', ],
      self::FINANCE_TOPIC_EXEMPTION_NOTICES => [ 'entityId', ],
      self::FINANCE_TOPIC_PAYMENTS => [ 'entityId', 'musicianId' ],
    ],
  ];

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    protected ConfigService $configService,
    protected EntityManager $entityManager,
    private RequestParameterService $parameterService,
    private StorageFactory $storageFactory,
  ) {
    parent::__construct($appName, $request);
    $this->l = $this->l10N();
  }

  /**
   * @param string $section
   *
   * @param string $topic
   *
   * @param string $operation One of self::DOCUMENT_ACTION_UPLOAD or self::DOCUMENT_ACTION_DELETE.
   *
   * @param null|int $entityId The id of the database entity.
   *
   * @param null|int $musicianId Just passed on to the response.
   *
   * @param null|int $projectId Just passed on to the response.
   *
   * @param string $data File upload data if this is a file-upload.
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function documents(
    string $section,
    string $topic,
    string $operation,
    ?int $entityId,
    ?int $musicianId,
    ?int $projectId,
    string $data = '{}'
  ):Response {
    switch ($operation) {
      case self::DOCUMENT_ACTION_UPLOAD:
        // we mis-use the participant-data upload form, so the actual identifiers
        // are in the "data" parameter and have to be remapped.
        $uploadData = json_decode($data, true);
        $entityId = $uploadData['optionKey'];
        $fileName = $uploadData['fileName'];
        $files = $this->parameterService['files'];
        $filesAppPath = $uploadData['filesAppPath'] ?? null;
        break;
      case self::DOCUMENT_ACTION_DELETE:
        $entityId = $this->parameterService['optionKey'];
        break;
    }

    foreach (self::REQUIRED[$section][$topic] as $required) {
      if (empty(${$required})) {
        return self::grumble($this->l->t('Required information "%s" not provided.', $required));
      }
    }

    /** @var Entity $entity */
    $entity = $this->findEntity(self::ENTITIES[$section][$topic], $entityId);

    if (empty($entity)) {
      return self::grumble(
        $this->l->t(
          'Unable to find the database entity with id "%1$d" in section "%2$s" for topic "%3$s".',
          [ $entityId, $section, $topic ]
        ));
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

        /** @var Entities\DatabaseStorageFile $fileNodeEntity */
        $fileNodeEntity = $this->getDocument($entity);
        $fileEntity = $fileNodeEntity ? $fileNodeEntity->getFile() : null;

        $conflict = null;

        $this->entityManager->beginTransaction();
        try {

          $storage = $this->getStorage($section, $topic, $entity);

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
                  owner: $this->getOwner($entity),
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

          $fileNodeEntity = $this->addDocument($fileNodeEntity, $fileEntity, $entity, $storage);

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
          'musicianId' => $musicianId,
          'projectId' => $projectId,
          // 'pathChain' => $pathChain, ?? needed ??
          'dirName' => $pathInfo['dirname'],
          'baseName' => $pathInfo['basename'],
          'extension' => $pathInfo['extension']?:'',
          'fileName' => $pathInfo['filename'],
          'fileId'   => $fileNodeEntity->getId(),
          'storageBackend'  => 'db',
          'download' => $downloadLink,
          'filesApp' => $filesAppLink,
          'conflict' => $conflict,
          'messages' => $message,
        ];

        return self::dataResponse([ $file ]);
      case self::DOCUMENT_ACTION_DELETE:
        $fileNodeEntity = $this->getDocument($entity);
        if (empty($fileNodeEntity)) {
          // ok, it is not there ...
          return self::response($this->l->t('We have no supporting document for the entity "%1$s", so we cannot delete it.', (string)$entity));
        }

        $this->entityManager->beginTransaction();
        try {
          // ok, delete it
          $this->clearDocument($entity);
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

  /**
   * @param string $section
   *
   * @param string $topic
   *
   * @param mixed $entity
   *
   * @return DatabaseStorage
   *
   * @throws UnexpectedValueException
   */
  private function getStorage(string $section, string $topic, mixed $entity = null):DatabaseStorage
  {
    switch ($section) {
      case self::SECTION_FINANCE:
        switch ($topic) {
          case self::FINANCE_TOPIC_DONATION_RECEIPTS:
            return $this->storageFactory->getDonationReceiptsStorage();
          case self::FINANCE_TOPIC_EXEMPTION_NOTICES:
            return $this->storageFactory->getTaxExemptionNoticesStorage();
          case self::FINANCE_TOPIC_PAYMENTS:
            /** @var Entities\CompositePayment $entity */
            return $this->storageFactory->getProjectParticipantsStorage($entity->getProjectParticipant());
        }
        break;
    }
    throw new UnexpectedValueException(
      $this->l->t(
        'Support for file upload in section "%2$s" for the topic "%3$s" is unimplemented.',
        [ $section, $topic ],
      )
    );
  }

  /**
   * @param mixed $entity
   *
   * @return null|Entities\Musician
   *
   * @throws UnexpectedValueException
   */
  private function getOwner(mixed $entity):?Entities\Musician
  {
    switch (true) {
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\DonationReceipt $entity */
        return $entity->getDonation()->getMusician();
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\TaxExemptionNotice $entity */
        return null;
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        return $entity->getMusician();
    }
    throw new UnexpectedValueException(
      $this->l->t(
        'Support for file upload for entities of type "%1$s" is unimplemented.',
        get_class($entity),
      )
    );
  }

  /**
   * @param mixed $entity
   *
   * @return void
   *
   * @throws UnexpectedValueException
   */
  private function clearDocument(mixed $entity):void
  {
    switch (true) {
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\DonationReceipt $entity */
        $entity->setSupportingDocument(null);
        return;
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\TaxExemptionNotice $entity */
        $entity->setWrittenNotice(null);
        return;
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        $entity->setSupportingDocument(null);
        return;
    }
    throw new UnexpectedValueException(
      $this->l->t(
        'Support for file upload for entities of type "%1$s" is unimplemented.',
        get_class($entity),
      )
    );
  }

  /**
   * @param mixed $entity
   *
   * @return null|Entities\DatabaseStorageFile
   *
   * @throws UnexpectedValueException
   */
  private function getDocument(mixed $entity):?Entities\DatabaseStorageFile
  {
    switch (true) {
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\DonationReceipt $entity */
        return $entity->getWrittenNotice();
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\TaxExemptionNotice $entity */
        return $entity->getSupportingDocument();
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        return $entity->getSupportingDocument();
    }
    throw new UnexpectedValueException(
      $this->l->t(
        'Support for file upload for entities of type "%1$s" is unimplemented.',
        get_class($entity),
      )
    );
  }

  /**
   * @param null|Entities\DatabaseStorageFile $fileNodeEntity
   *
   * @param Entities\EncryptedFile $fileEntity
   *
   * @param mixed $entity
   *
   * @param DatabaseStorage $storage
   *
   * @return Entities\DatabaseStorageFile
   *
   * @throws UnexpectedValueException
   */
  private function addDocument(
    ?Entities\DatabaseStorageFile $fileNodeEntity,
    Entities\EncryptedFile $fileEntity,
    mixed $entity,
    DatabaseStorage $storage,
  ):Entities\DatabaseStorageFile {
    switch (true) {
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        if (!empty($fileNodeEntity)) {
          $fileNodeEntity->setFile($fileEntity);
        } else {
          $fileNodeEntity = $storage->addCompositePayment($entity, $fileEntity, flush: false);
          $entity->setSupportingDocument($fileNodeEntity);
        }
        return $fileNodeEntity;
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\DonationReceipt $entity */
        if (!empty($fileNodeEntity)) {
          $fileNodeEntity->setFile($fileEntity);
        } else {
          $fileNodeEntity = $storage->addDocument($entity, $fileEntity, flush: false);
          $entity->setSupportingDocument($fileNodeEntity);
        }
        return $fileNodeEntity;
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\TaxExemptionNotice $entity */
        if (!empty($fileNodeEntity)) {
          $fileNodeEntity->setFile($fileEntity);
        } else {
          $fileNodeEntity = $storage->addDocument($entity, $fileEntity, flush: false);
          $entity->setWrittenNotice($fileNodeEntity);
        }
        return $fileNodeEntity;
    }
    throw new UnexpectedValueException(
      $this->l->t(
        'Support for file upload for entities of type "%1$s" is unimplemented.',
        get_class($entity),
      )
    );
  }
}

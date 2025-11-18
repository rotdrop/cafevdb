<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020-2025 Claus-Justus Heine <himself@claus-justus-heine.de>
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
use UnexpectedValueException;


use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute as CoreAttributes;
use OCP\AppFramework\Http\Response;
use OCP\Files\File;
use OCP\IRequest;

use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Storage\Database\Factory as StorageFactory;
use OCA\CAFEVDB\Storage\Database\Storage as DatabaseStorage;
use OCA\CAFEVDB\Storage\UserStorage;

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
  public const FINANCE_TOPIC_INVOICES = 'invoices';

  public const TOPICS = [
    self::SECTION_FINANCE => [
      self::FINANCE_TOPIC_DONATION_RECEIPTS,
      self::FINANCE_TOPIC_EXEMPTION_NOTICES,
      self::FINANCE_TOPIC_INVOICES,
      self::FINANCE_TOPIC_PAYMENTS,
    ],
  ];

  private const ENTITIES = [
    self::SECTION_FINANCE => [
      self::FINANCE_TOPIC_DONATION_RECEIPTS => Entities\DonationReceipt::class,
      self::FINANCE_TOPIC_EXEMPTION_NOTICES => Entities\TaxExemptionNotice::class,
      self::FINANCE_TOPIC_INVOICES => Entities\Invoice::class,
      self::FINANCE_TOPIC_PAYMENTS => Entities\CompositePayment::class,
    ],
  ];

  private const REQUIRED = [
    self::SECTION_FINANCE => [
      self::FINANCE_TOPIC_DONATION_RECEIPTS => [ 'entityId', ],
      self::FINANCE_TOPIC_EXEMPTION_NOTICES => [ 'entityId', ],
      self::FINANCE_TOPIC_PAYMENTS => [ 'entityId', 'musicianId' ],
      self::FINANCE_TOPIC_INVOICES => [ 'entityId', ],
    ],
  ];

  /** {@inheritdoc} */
  public function __construct(
    $appName,
    IRequest $request,
    private StorageFactory $storageFactory,
    protected ConfigService $configService,
    protected EntityManager $entityManager,
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
   * @param null|int $musicianId Just passed on to the response.
   *
   * @param null|int $projectId Just passed on to the response.
   *
   * @param array|string $data File upload data if this is a file-upload.
   *
   * @param null|string $cloudFile If given just the full path to a file in
   * the cloud. request['files'] is ignored in this.case.
   *
   * @param string $uploadMode Upload mode for $cloudFile, link, copy, move.
   *
   * @param string $conflict fail, replace, rename on conflict.
   *
   * @return Response
   */
  #[CoreAttributes\NoAdminRequired]
  public function documents(
    string $section,
    string $topic,
    string $operation,
    ?int $musicianId,
    ?int $projectId,
    array|string $data = '{}',
    ?string $cloudFile = null,
    string $uploadMode = UploadsController::UPLOAD_MODE_COPY,
    string $conflict = EnumAddDocumentConflictAction::REPLACE->value,
  ):Response {
    switch ($operation) {
      case self::DOCUMENT_ACTION_UPLOAD:
        // we mis-use the participant-data upload form, so the actual identifiers
        // are in the "data" parameter and have to be remapped.
        // {
        //   "fieldId": 3,
        //   "optionKey": 3,
        //   "subDir": "",
        //   "fileName": "Rechnung-Test2024-002-1-EineFirmaBlah-TesterAddressbookIntegration",
        //   "fileBase": "Rechnung-Test2024-002-1-EineFirmaBlah-TesterAddressbookIntegration",
        //   "participantFolder": "/camerata/Finanzen/Rechnungen/2025",
        //   "filesAppPath": "/camerata/Finanzen/Rechnungen/2025",
        //   "entityField": "option-value",
        //   "storage": "db"
        // }
        $uploadData = is_string($data) ? json_decode($data, true) : $data;
        $entityId = $uploadData['optionKey'];
        $fileName = $uploadData['fileName'] ?? null;
        $files = $this->request['files'] ?? null;
        $filesAppPath = $uploadData['filesAppPath'] ?? null;
        break;
      case self::DOCUMENT_ACTION_DELETE:
        $entityId = $this->request['optionKey'];
        break;
    }

    foreach (self::REQUIRED[$section][$topic] as $required) {
      if (empty(${$required})) {
        throw new Exceptions\EnduserNotificationException(
          $this->l->t('Required information "%s" not provided.', $required),
        );
      }
    }

    $entity = $this->fetchEntity($section, $topic, $entityId);

    if (empty($entity)) {
      throw new Exceptions\EnduserNotificationException(
        $this->l->t(
          'Unable to find the database entity with id "%1$d" in section "%2$s" for topic "%3$s".',
          [ $entityId, $section, $topic ],
        ),
      );
    }

    switch ($operation) {
      case self::DOCUMENT_ACTION_UPLOAD:

        if (empty($cloudFile)) {
          $files = $this->prepareUploadInfo($files, $entityId, multiple: false);
          if ($files instanceof Http\Response) {
            // error generated
            return $files;
          }

          $file = array_shift($files); // only one
          if ($file['error'] != UPLOAD_ERR_OK) {
            throw new Exceptions\EnduserNotificationException(
              $this->l->t('Upload error "%s".', $file['str_error']),
            );
          }
          $originalFilePath = $file['original_name'] ?? null;
          if ($file['upload_mode']) {
            $uploadMode = $file['upload_mode'];
          }
        } else {
          $originalFilePath = $cloudFile;
          $file['original_name'] = $cloudFile;
          $file['name'] = basename($originalFilePath);
          $file['tmp_name'] = null;
        }

        /** @var UserStorage $userStorage */
        $userStorage = $this->di(UserStorage::class);

        switch ($uploadMode) {
          case UploadsController::UPLOAD_MODE_MOVE:
            if (empty($originalFilePath)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Move operation requested, but the original file path has not been specified.'),
              );
            }
            /** @var File $originalFile */
            $originalFile = $userStorage->get($originalFilePath);
            if (empty($originalFile)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Move operation requested, but the original file "%s" cannot be found.', $originalFilePath),
              );
            }
            break;
          case UploadsController::UPLOAD_MODE_LINK:
            $originalFileId = $originalFilePath;
            if (empty($originalFileId)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Link operation requested, but the id of the original file has not been specified.'),
              );
            }
            $originalFile = $this->entityManager->find(Entities\File::class, $originalFileId);
            if (empty($originalFile)) {
              throw new Exceptions\EnduserNotificationException(
                $this->l->t('Link operation requested, but the existing original file with id "%s" cannot be found.', $originalFileId),
              );
            }
            $originalFilePath = $originalFile->getFileName();
            break;
          case UploadsController::UPLOAD_MODE_COPY:
            if ($cloudFile) {
              /** @var File $originalFile */
              $originalFile = $userStorage->get($originalFilePath);
              if (empty($originalFile)) {
                throw new Exceptions\EnduserNotificationException(
                  $this->l->t('Copy operation requested, but the original file "%s" cannot be found.', $originalFilePath),
                );
              }
            }
            break;
        }

        $originalFileName = $originalFilePath ? basename($originalFilePath) : null;

        /** @var Entities\DatabaseStorageFile $fileNodeEntity */
        $fileNodeEntity = $this->getDocument($entity);
        $fileEntity = $fileNodeEntity ? $fileNodeEntity->getFile() : null;
        $actualConflict = empty($fileEntity) ? null : $conflict;

        $this->entityManager->beginTransaction();
        try {

          $storage = $this->getStorage($section, $topic, $entity);

          switch ($uploadMode) {
            case UploadsController::UPLOAD_MODE_MOVE:
              $this->entityManager->registerPreCommitAction(new Common\UndoableFileRemove($originalFilePath, gracefully: true));
              // no break
            case UploadsController::UPLOAD_MODE_COPY:
              if ($cloudFile) {
                $fileContent = $originalFile->getContent();
              } else {
                $fileContent = $this->getUploadContent($file);
              }

              /** @var \OCP\Files\IMimeTypeDetector $mimeTypeDetector */
              $mimeTypeDetector = $this->di(\OCP\Files\IMimeTypeDetector::class);
              $mimeType = $mimeTypeDetector->detectString($fileContent);

              if (!empty($fileEntity) && $fileEntity->getNumberOfLinks() > 1 && $conflict == DatabaseStorageFolder::ADD_DOCUMENT_CONFLICT_REPLACE) {
                // if the file has multiple links then it is probably
                // better to remove the existing file rather than
                // overwriting a file which has multiple links.
                $fileNodeEntity->setFile(null); // unlink
                $fileEntity = null;
              }

              if (empty($fileEntity) || $conflict == DatabaseStorageFolder::ADD_DOCUMENT_CONFLICT_RENAME) {
                $fileEntity = new Entities\EncryptedFile(
                  data: $fileContent,
                  mimeType: $mimeType,
                  owner: $this->getOwner($entity),
                );
                $this->persist($fileEntity);
              } elseif ($conflict == DatabaseStorageFolder::ADD_DOCUMENT_CONFLICT_REPLACE) {
                $fileEntity
                  ->setMimeType($mimeType)
                  ->setSize(strlen($fileContent))
                  ->getFileData()->setData($fileContent);
              } else {
                throw new Exceptions\EnduserNotificationException(
                  $this->l->t(
                    'The destination document already exists as "%s".', $fileNodeEntity->getPathName()
                  ),
                );
              }
              $fileEntity->setFileName($originalFileName);

              break;
            case UploadsController::UPLOAD_MODE_LINK:
              $fileContent = null;
              /** @var Entities\EncryptedFile $originalFile */
              if (!empty($fileEntity) && $fileEntity->getId() == $originalFileId) {
                throw new Exceptions\EnduserNotificationException(
                  $this->l->t(
                    'Link operation requested, but the existing original file is the same as the target destination (%s@%s)',
                    [
                      $originalFile->getFileName(), $originalFileId
                    ],
                  ),
                );
              }
              $fileEntity = $originalFile;
              break;
          }

          $fileNodeEntity = $this->addDocument($fileNodeEntity, $fileEntity, $entity, $storage, $conflict);

          $this->flush();

          $fileName = $fileNodeEntity->getName();

          $this->entityManager->commit();
        } catch (Throwable $t) {
          $this->logException($t);
          if ($this->entityManager->isTransactionActive()) {
            $this->entityManager->rollback();
          }
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Error, caught an exception. No changes were performed.'),
            previous: $t,
          );
        }

        if ($uploadMode != UploadsController::UPLOAD_MODE_LINK && empty($cloudFile)) {
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
        } catch (Throwable $t) {
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

        $file['meta'] = DTO\UploadFileMetaData::fromArray([
          'musicianId' => $musicianId,
          'projectId' => $projectId,
          // 'pathChain' => $pathChain, ?? needed ??
          'dirName' => $pathInfo['dirname'],
          'baseName' => $pathInfo['basename'],
          'extension' => $pathInfo['extension']?:'',
          'fileName' => $pathInfo['filename'],
          'fileId'   => $fileNodeEntity->getId(),
          'storageBackend'  => EnumFileStorageBackend::DB,
          'download' => $downloadLink,
          'filesApp' => $filesAppLink,
          'conflict' => $actualConflict,
          'messages' => $message,
        ]);

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

        } catch (Throwable $t) {
          $this->logException($t);
          $this->entityManager->rollback();
          throw new Exceptions\EnduserNotificationException(
            $this->l->t('Error, caught an exception. No changes were performed.'),
            previous: $t,
          );
        }

        return self::response($this->l->t('Successfully deleted the written document for the entity "%1$s", please upload a new one!', (string)$entity));
    }
    throw new Exceptions\EnduserNotificationException($this->l->t('UNIMPLEMENTED'));
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
          case self::FINANCE_TOPIC_INVOICES:
            return $this->storageFactory->getInvoicesStorage();
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
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        return $entity->getMusician();
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\DonationReceipt $entity */
        return $entity->getDonation()->getMusician();
      case ($entity instanceof Entities\Invoice):
        /** @var Entities\Invoice $entity */
        return $entity->getDebitor();
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\TaxExemptionNotice $entity */
        return null;
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
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        $entity->setSupportingDocument(null);
        return;
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\DonationReceipt $entity */
        $entity->setSupportingDocument(null);
        return;
      case ($entity instanceof Entities\Invoice):
        /** @var Entities\Invoice $entity */
        $entity->setWrittenInvoice(null);
        return;
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\TaxExemptionNotice $entity */
        $entity->setWrittenNotice(null);
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
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        return $entity->getSupportingDocument();
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\DonationReceipt $entity */
        return $entity->getSupportingDocument();
      case ($entity instanceof Entities\Invoice):
        /** @var Entities\Invoice $entity */
        return $entity->getWrittenInvoice();
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\TaxExemptionNotice $entity */
        return $entity->getWrittenNotice();
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
   * @param string $conflict
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
    string $conflict,
  ):Entities\DatabaseStorageFile {
    switch (true) {
      case ($entity instanceof Entities\CompositePayment):
        /** @var Entities\CompositePayment $entity */
        if (!empty($fileNodeEntity)) {
          $fileNodeEntity->setFile($fileEntity);
        } else {
          $fileNodeEntity = $storage->addCompositePayment($entity, $fileEntity, flush: false, conflict: $conflict);
          $entity->setSupportingDocument($fileNodeEntity);
        }
        return $fileNodeEntity;
      case ($entity instanceof Entities\DonationReceipt):
        /** @var Entities\DonationReceipt $entity */
        if (!empty($fileNodeEntity)) {
          $fileNodeEntity->setFile($fileEntity);
        } else {
          $fileNodeEntity = $storage->addDocument($entity, $fileEntity, flush: false, conflict: $conflict);
          $entity->setSupportingDocument($fileNodeEntity);
        }
        return $fileNodeEntity;
      case ($entity instanceof Entities\Invoice):
        /** @var Entities\Invoice $entity */
        $fileNodeEntity = $storage->addDocument($entity, $fileEntity, flush: false, conflict: $conflict);
        $entity->setWrittenInvoice($fileNodeEntity);
        return $fileNodeEntity;
      case ($entity instanceof Entities\TaxExemptionNotice):
        /** @var Entities\TaxExemptionNotice $entity */
        if (!empty($fileNodeEntity)) {
          $fileNodeEntity->setFile($fileEntity);
        } else {
          $fileNodeEntity = $storage->addDocument($entity, $fileEntity, flush: false, conflict: $conflict);
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

  /**
   * Fetch the associated databse entity by a unique identifier.
   *
   * @param string $section
   *
   * @param string $topic
   *
   * @param int|string $entityId
   *
   * @return mixed The entity if found, else null.
   */
  private function fetchEntity(string $section, string $topic, int|string $entityId):mixed
  {
    switch ($section) {
      case self::SECTION_FINANCE:
        switch ($topic) {
          case self::FINANCE_TOPIC_INVOICES:
            return $this->getDatabaseRepository(self::ENTITIES[$section][$topic])->findOneBy([
              '(|id' => $entityId,
              'invoiceNumber' => $entityId,
            ]);
          default:
            return $this->findEntity(self::ENTITIES[$section][$topic], $entityId);
        }
      default:
        return null;
    }
  }
}

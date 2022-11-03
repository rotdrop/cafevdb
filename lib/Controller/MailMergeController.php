<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2022 Claus-Justus Heine
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
/**
 * @file Handle various requests associated with asymmetric encryption
 */

namespace OCA\CAFEVDB\Controller;

use ZipStream\ZipStream;
use ZipStream\Option\Archive as ArchiveOptions;

use OCP\AppFramework\Http;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use OCP\ILogger;
use OCP\IL10N;

use OCP\Files\Node;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\FileInfo;

use OCP\Contacts\IManager as IContactsManager;
use OCP\IAddressBook;

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\ContactsService;
use OCA\CAFEVDB\Service\Finance\InstrumentInsuranceService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Documents\OpenDocumentFiller;
use OCA\CAFEVDB\Storage\UserStorage;

use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Common\Util;

/**
 * Make the stored personal data accessible for the web-interface. This is
 * meant for newer parts of the web-interface in contrast to the legacy PME
 * stuff.
 */
class MailMergeController extends Controller
{
  use \OCA\CAFEVDB\Traits\ResponseTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\FlattenEntityTrait;

  /**
   * @var bool
   *
   * @todo maybe add another download-option to the mail-merge menu
   */
  const AS_PDF = false;

  const OPERATION_DOWNLOAD = 'download';
  const OPERATION_CLOUD = 'cloud';
  const OPERATION_DATASET = 'dataset';

  /** @var OpenDocumentFiller */
  private $documentFiller;

  /** @var OrganizationalRolesService */
  private $rolesService;

  /** @var UserStorage */
  private $userStorage;

  /** @var InstrumentInsuranceService */
  private $insuranceService;

  /** @var IContactsManager */
  private $contactsManager;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    string $appName,
    IRequest $request,
    ?string $userId,
    IL10N $l10n,
    ILogger $logger,
    EntityManager $entityManager,
    ConfigService $configService,
    OrganizationalRolesService $rolesService,
    InstrumentInsuranceService $insuranceService,
    OpenDocumentFiller $documentFiller,
    UserStorage $storage,
    IContactsManager $contactsManager,
  ) {
    parent::__construct($appName, $request);
    $this->l = $l10n;
    $this->logger = $logger;
    $this->entityManager = $entityManager;
    $this->configService = $configService;
    $this->rolesService = $rolesService;
    $this->insuranceService = $insuranceService;
    $this->documentFiller = $documentFiller;
    $this->userStorage = $storage;
    $this->contactsManager = $contactsManager;
  }
  // phpcs:enable

  /**
   * Try to perform a mail-merge of a document which is assumed to need
   * somehow a sender/recipient context (i.e. a letter).
   *
   * @param int $fileId
   *
   * @param string $fileName
   *
   * @param int $senderId
   *
   * @param array $recipientIds
   *
   * @param int $projectId
   *
   * @param array $contactKeys
   *
   * @param array $addressBookUris
   *
   * @param string $operation
   *
   * @param null|int $limit
   *
   * @param null|int $offset
   *
   * @return Response
   *
   * @NoAdminRequired
   */
  public function merge(
    int $fileId,
    string $fileName,
    int $senderId,
    array $recipientIds = [],
    int $projectId = 0,
    array $contactKeys = [],
    array $addressBookUris = [],
    string $operation = self::OPERATION_DOWNLOAD,
    ?int $limit = null,
    ?int $offset = null,
  ):Response {

    $templateData = [];
    $blocks = [];

    $project = $projectId > 0
      ? $this->getDatabaseRepository(Entities\Project::class)->find($projectId)
      : null;

    $musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);

    /** @var Entities\Musician $sender */
    $sender = $musiciansRepository->find($senderId);
    $senderUserId = $sender->getUserIdSlug();

    foreach (OrganizationalRolesService::BOARD_MEMBERS as $role) {
      if ($this->rolesService->isDedicatedBoardMember($role, $senderUserId)) {
        $blocks['sender'] = 'org.' . $role;
        break;
      }
    }

    $senderInitials = array_reduce(preg_split('/[-_.\s]/', $sender->getPublicName(firstNameFirst: true), -1, PREG_SPLIT_NO_EMPTY), fn($initials, $item) => $initials . $item[0]);

    $timeStamp = $this->formatTimeStamp();
    $mailMergeCount = 0;
    $recipientSlug = '';

    try {

      if ($operation == self::OPERATION_CLOUD) {
        /** @var Folder */
        $cloudFolder = $this->ensureCloudDestinationFolder($project, $timeStamp);
      }

      if (empty($blocks['sender'])) {
        $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
        $templateData['sender'] = $this->flattenMusician($sender, only: []); // just the address data, no fancy things
        $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $filterState);
        $signature = $this->rolesService->dedicatedBoardMemberSignature(
          OrganizationalRolesService::BOARD_MEMBER_ROLE, $senderId
        );
        if (!empty($signature)) {
          $signature = 'data:'.$signature->mimeType().';base64,' . base64_encode($signature->data());
        }
        $templateData['sender']['signature'] = $signature;
      }

      if (!empty($project)) {
        $templateData['project'] = $this->flattenProject($project);
      }

      $noRecipients = $limit === 0 || (empty($recipientIds) && empty($contactKeys));

      if ($noRecipients) {
        switch ($operation) {
          case self::OPERATION_CLOUD:
          case self::OPERATION_DOWNLOAD:
            list($fileData, $mimeType, $filledFileName) = $this->documentFiller->fill($fileName, $templateData, $blocks, asPdf: self::AS_PDF);
            $filledFile = pathinfo($filledFileName);
            $filledFileName = implode('-', [ $timeStamp, $senderInitials, $filledFile['filename'], ]) . '.' . $filledFile['extension'];
            if ($operation == self::OPERATION_DOWNLOAD) {
              return $this->dataDownloadResponse($fileData, $filledFileName, $mimeType);
            } else {
              $cloudFolder->newFile($filledFileName, $fileData);
              $mailMergeCount = 1;
              return self::dataResponse([
                'message' => $this->l->n('Mail-merge successful, %n file substituted.', 'Mail-merge successful, %n files substituted.', $mailMergeCount),
                'cloudFolder' => substr(strchr($cloudFolder->getPath(), '/files/'), strlen('/files')),
                'count' => $mailMergeCount,
              ]);
            }
          case self::OPERATION_DATASET:
            $fillData = $this->documentFiller->fillData($templateData);
            $filledFileName = $fileName;
            $filledFile = pathinfo($filledFileName);
            $filledFileName = implode('-', [ $timeStamp, $senderInitials, $filledFile['filename'], ]) . '.' . 'json';
            if (!empty($blocks)) {
              $fillData['__blocks__'] = $blocks;
            }
            $fileData = json_encode($fillData);
            return $this->dataDownloadResponse($fileData, $filledFileName, 'application/json');
        }
      } else {

        if (count($recipientIds) == 1 && reset($recipientIds) == 0) {
          $criteria = [];
          if (!empty($project)) {
            $criteria[] = [ 'projectParticipation.project' => $project ];
          }
          $recipients = $musiciansRepository->findBy($criteria, limit: $limit, offset: $offset);
        } else {
          $recipients = $musiciansRepository->findBy([ 'id' => $recipientIds ], limit: $limit, offset: $offset);
        }

        $addressBookRecipients = $this->contactsToEntities($contactKeys, $addressBookUris);

        $recipients = array_merge($recipients, $addressBookRecipients);

        if (count($recipients) > 1) {
          $rootDirectory = implode('-', [
            $timeStamp,
            $senderInitials,
            pathinfo($fileName, PATHINFO_FILENAME),
          ]);
          switch ($operation) {
            case self::OPERATION_CLOUD:
              $cloudFolder = $cloudFolder->newFolder($rootDirectory);
              break;
            case self::OPERATION_DOWNLOAD:
            case self::OPERATION_DATASET:
              $dataStream = fopen("php://memory", 'w');
              $zipStreamOptions = new ArchiveOptions;
              $zipStreamOptions->setOutputStream($dataStream);
              $zipStream = new ZipStream(opt: $zipStreamOptions);
              break;
          }
        }

        /** @var Entities\Musician $recipient */
        foreach ($recipients as $recipient) {

          $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
          $recipientTemplateData = array_merge(
            $templateData, [
              'recipient' => $this->flattenMusician($recipient),
            ],
            [
              'instins' => $this->insuranceService->musicianOverview($recipient),
            ],
          );
          $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $filterState);

          switch ($operation) {
            case self::OPERATION_CLOUD:
            case self::OPERATION_DOWNLOAD:
              list($fileData, $mimeType, $filledFileName) = $this->documentFiller->fill($fileName, $recipientTemplateData, $blocks, asPdf: self::AS_PDF);
              break;
            case self::OPERATION_DATASET:
              $fillData = $this->documentFiller->fillData($recipientTemplateData);
              if (!empty($blocks)) {
                $fillData['__blocks__'] = $blocks;
              }
              $fileData = json_encode($fillData);
              $filledFileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . 'json';
              $mimeType = 'application/json';
              break;
          }

          $filledFile = pathinfo($filledFileName);
          $recipientSlug = Util::dashesToCamelCase($recipient->getUserIdSlug(), true, '_-.');
          $filledFileName = implode('-', [ $timeStamp, $senderInitials, $filledFile['filename'], $recipientSlug ]) . '.' . $filledFile['extension'];

          if (count($recipients) <= 1) {
            switch ($operation) {
              case self::OPERATION_CLOUD:
                $cloudFolder->newFile($filledFileName, $fileData);
                $mailMergeCount = 1;
                return self::dataResponse([
                  'message' => $this->l->n('Mail-merge successful, %n file substituted.', 'Mail-merge successful, %n files substituted.', $mailMergeCount),
                  'cloudFolder' => substr(strchr($cloudFolder->getPath(), '/files/'), strlen('/files')),
                  'count' => $mailMergeCount,
                ]);
              case self::OPERATION_DOWNLOAD:
              case self::OPERATION_DATASET:
                return $this->dataDownloadResponse($fileData, $filledFileName, $mimeType);
            }
          }

          switch ($operation) {
            case self::OPERATION_CLOUD:
              $cloudFolder->newFile($filledFileName, $fileData);
              break;
            case self::OPERATION_DOWNLOAD:
            case self::OPERATION_DATASET:
              $zipStream->addFile($rootDirectory . '/' . $filledFileName, $fileData);
          }
          $mailMergeCount++;
          $recipientSlug = ''; // reset for error message
        }

        switch ($operation) {
          case self::OPERATION_CLOUD:
            return self::dataResponse([
              'message' => $this->l->n('Mail-merge successful, %n file substituted.', 'Mail-merge successful, %n files substituted.', $mailMergeCount),
              'cloudFolder' => substr(strchr($cloudFolder->getPath(), '/files/'), strlen('/files')),
              'count' => $mailMergeCount,
            ]);
          case self::OPERATION_DOWNLOAD:
          case self::OPERATION_DATASET:
            $zipStream->finish();
            rewind($dataStream);
            $fileData = stream_get_contents($dataStream);
            fclose($dataStream);

            return $this->dataDownloadResponse($fileData, $rootDirectory . '.zip', 'application/zip');
        }
      }
    } catch (\Throwable $t) {
      $this->logException($t);
      return self::dataResponse([
        'message' => $this->l->t('Exception: "%s"', $t->getMessage()),
        'exception' => $this->exceptionChainData($t),
        'conversions' => $mailMergeCount,
        'failingRecipient' => $recipientSlug,
      ], Http::STATUS_BAD_REQUEST);
    }
  }

  /**
   * @param null|Entities\Project $project
   *
   * @param null|string $timeStamp
   *
   * @return Folder
   */
  private function ensureCloudDestinationFolder(?Entities\Project $project = null, ?string $timeStamp = null):Folder
  {
    $timeStamp = $timeStamp ?? $this->formatTimeStamp();
    $year = substr($timeStamp, 0, 4);
    if (empty($project)) {
      $pathChain = [
        $this->getSharedFolderPath(),
        $this->l->t('Documents'),
        $this->l->t('MailMerge'),
        $year,
      ];
    } else {
      /** @var ProjectService $projectService */
      $projectService = $this->di(ProjectService::class);
      $pathChain = [
        /* $projectFolder = */reset($projectService->ensureProjectFolders($project, only: ProjectService::FOLDER_TYPE_PROJECT, dry: true)),
        $this->l->t('MailMerge'),
      ];
    }

    return $this->userStorage->ensureFolderChain($pathChain);
  }

  /**
   * In order to simplify the program flow we generate fake musician entities
   * for each provided contact.
   *
   * @param array $contactKeys
   *
   * @param array $addressBookUris
   *
   * @return array
   */
  private function contactsToEntities(array $contactKeys, array $addressBookUris):array
  {
    $addressBooks = $this->contactsManager->getUserAddressBooks();
    $addressBooks = array_combine(
      array_map(fn($b) => $b->getKey(), $addressBooks),
      $addressBooks
    );

    $entities = [];

    foreach ($contactKeys as $contactInfo) {
      $bookKey = $contactInfo['book'];
      /** @var IAddressBook $addressBook */
      $addressBook = $addressBooks[$bookKey] ?? null;
      if ($addressBook) {
        $searchProperties = [];
        if ($contactInfo['uri'] ?? null) {
          $searchProperties[] = 'URI';
        }
        if ($contactInfo['uid'] ?? null) {
          $searchProperties[] = 'UID';
        }
        $key = $contactInfo['key'];
        $contact = $addressBook->search($key, $searchProperties, [
          'strict_search' => true,
          'types' => true,
        ]);
        if (!empty($contact)) {
          $contact = array_shift($contact);

          /** @var ContactsService $contactsService */
          $contactsService = $this->di(ContactsService::class);

          $entities[] = $contactsService->importCardData($contact);
        }
      }
    }

    return $entities;
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Documents\OpenDocumentFiller;

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

  const DESTINATION_DOWNLOAD = 'download';
  const DESTINATION_CLOUD = 'cloud';

  /** @var OpenDocumentFiller */
  private $documentFiller;

  /** @var OrganizationalRolesService */
  private $rolesService;

  public function __construct(
    string $appName
    , IRequest $request
    , $userId
    , IL10N $l10n
    , ILogger $logger
    , EntityManager $entityManager
    , ConfigService $configService
    , OrganizationalRolesService $rolesService
    , OpenDocumentFiller $documentFiller
  ) {
    parent::__construct($appName, $request);
    $this->l = $l10n;
    $this->logger = $logger;
    $this->entityManager = $entityManager;
    $this->configService = $configService;
    $this->rolesService = $rolesService;
    $this->documentFiller = $documentFiller;
  }

  /**
   * @NoAdminRequired
   *
   * Try to perform a mail-merge of a document which is assumed to need
   * somehow a sender/recipient context (i.e. a letter).
   */
  public function merge(int $fileId, string $fileName, int $senderId, array $recipientIds = [], int $projectId = 0, string $destination = self::DESTINATION_DOWNLOAD)
  {

    $this->logInfo('ARGS ' . $senderId . ' / ' . print_r($recipientIds, true) . ' / ' . $projectId);

    $templateData = [];
    $blocks = [];

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

    try {

      if (empty($blocks['sender'])) {
        $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
        $templateData['sender'] = $this->flattenMusician($sender);
        $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $filterState);
      }

      if (empty($recipientIds)) {
        list($fileData, $mimeType, $filledFileName) = $this->documentFiller->fill($fileName, $templateData, $blocks, asPdf: false);
        $filledFile = pathinfo($filledFileName);
        $filledFileName = implode('-', [ $timeStamp, $filledFile['filename'], $senderInitials, ]) . '.' . $filledFile['extension'];

        return $this->dataDownloadResponse($fileData, $filledFileName, $mimeType);
      } else {
        if (count($recipientIds) > 1) {
          $dataStream = fopen("php://memory", 'w');
          $zipStreamOptions = new ArchiveOptions;
          $zipStreamOptions->setOutputStream($dataStream);
          $zipStream = new ZipStream(opt: $zipStreamOptions);
          $rootDirectory = $timeStamp . '-' . $this->appName . '-' . 'mail-merge';
        }

        $recipients = $musiciansRepository->findBy([ 'id' => $recipientIds ]);

        /** @var Entities\Musician $recipient */
        foreach ($recipients as $recipient) {

          $recipientTemplateData = array_merge(
            $templateData, [
              'recipient' => $this->flattenMusician($recipient),
            ]);

          list($fileData, $mimeType, $filledFileName) = $this->documentFiller->fill($fileName, $recipientTemplateData, $blocks, asPdf: false);
          if (count($recipientIds) <= 1) {
            return $this->dataDownloadResponse($fileData, $filledFileName, $mimeType);
          }
          $filledFile = pathinfo($filledFileName);
          $recipientSlug = Util::dashesToCamelCase($recipient->getUserIdSlug(), true, '_-.');
          $filledFileName = implode('-', [ $timeStamp, $filledFile['filename'], $senderInitials, $recipientSlug ]) . '.' . $filledFile['extension'];
          $zipStream->addFile($rootDirectory . '/' . $filledFileName, $fileData);
        }

        $zipStream->finish();
        rewind($dataStream);
        $fileData = stream_get_contents($dataStream);
        fclose($dataStream);

        return $this->dataDownloadResponse($fileData, $rootDirectory . '.zip', 'application/zip');
      }
    } catch (\Throwable $t) {
      return self::dataResponse([
        'message' => $this->l->t('Exception: "%s"', $t->getMessage()),
        'exception' => $this->exceptionChainData($t),
      ], Http::STATUS_BAD_REQUEST);
    }
  }
}

// local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

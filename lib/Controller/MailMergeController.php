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

    if (empty($blocks['sender'])) {
      $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      $templateData['sender'] = $this->flattenMusician($sender);
      $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $filterState);
    }

    list($fileData, $mimeType, $fileName) = $this->documentFiller->fill($fileName, $templateData, $blocks, asPdf: false);

    return $this->dataDownloadResponse($fileData, $fileName, $mimeType);
  }
}

// local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

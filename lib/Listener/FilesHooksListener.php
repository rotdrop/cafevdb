<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2016, 2020, 2021, 2022 Claus-Justus Heine <himself@claus-justus-heine.de>
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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use OCP\Contacts\IManager as IContactsManager;

use OCA\Files\Event\LoadAdditionalScriptsEvent as HandledEvent;

use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

class FilesHooksListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\ContactsTrait;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;
  use \OCA\CAFEVDB\Traits\CloudAdminTrait;

  const EVENT = HandledEvent::class;

  const BASENAME = 'files-hooks';

  /** @var IAppContainer */
  private $appContainer;

  private $handled = false;

  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  public function handle(Event $event): void
  {
    if (!($event instanceOf HandledEvent)) {
      return;
    }
    /** @var HandledEvent $event */

    // this really only needs to be executed once per request.
    if ($this->handled) {
      return;
    }
    $this->handled = true;

    /** @var IUserSession $userSession */
    $userSession = $this->appContainer->get(IUserSession::class);
    $user = $userSession->getUser();

    if (empty($user)) {
      return;
    }

    $userId = $user->getUID();

    $authorization = $this->appContainer->get(AuthorizationService::class);
    if (!$authorization->authorized($userId)) {
      return;
    }

    $appName = $this->appContainer->get('appName');

    /** @var IInitialState $initialState */
    $initialState = $this->appContainer->get(IInitialState::class);

    /** @var EncryptionService $encryptionService */
    $encryptionService = $this->appContainer->get(EncryptionService::class);
    $this->logger = $this->appContainer->get(\OCP\ILogger::class);
    $this->l = $this->appContainer->get(\OCP\IL10N::class);

    $sharedFolder = $encryptionService->getConfigValue(ConfigService::SHARED_FOLDER, '');
    $templatesFolder = $encryptionService->getConfigValue(ConfigService::DOCUMENT_TEMPLATES_FOLDER, '');
    $financeFolder = $encryptionService->getConfigValue(ConfigService::FINANCE_FOLDER);
    $balancesFolder = $encryptionService->getConfigValue(ConfigService::BALANCES_FOLDER);
    $projectsFolder = $encryptionService->getConfigValue(ConfigService::PROJECTS_FOLDER);
    $supportingDocumentsFolder = $this->getSupportingDocumentsFolderName();

    /** @var EntityManager $entityManager */
    $entityManager = $this->appContainer->get(EntityManager::class);
    try {
      $musicianId = $entityManager->getRepository(Entities\Musician::class)->findIdByUserId($userId);
      // $this->logInfo('MUS ID ' . $musicianId);
    } catch (\Throwable $t) {
      // ignore
      $this->logException($t);
      $musicianId = 0;
    }

    /** @var IContactsManager $contactsManager */
    $contactsManager = $this->appContainer->get(IContactsManager::class);

    $sharedFolder = '/' . $sharedFolder;
    $templatesFolder = $sharedFolder . '/' . $templatesFolder;
    $financeFolder = $sharedFolder . '/' . $financeFolder;
    $balancesFolder = $financeFolder . '/' . $balancesFolder;
    $projectBalancesFolder = $balancesFolder . '/' . $projectsFolder;

    $initialState->provideInitialState('files', [
      'sharing' => [
        'files' => [
          'folders' => [
            // absolute paths
            'root' => $sharedFolder,
            'templates' => $templatesFolder,
            'finance' => $financeFolder,
            'balances' => $balancesFolder,
            'projectBalances' => $projectBalancesFolder,
          ],
          'subFolders' => [
            // relative paths
            'supportingDocuments' => $supportingDocumentsFolder,
          ],
        ],
      ],
      'personal' => [
        'userId' => $userId,
        'musicianId' => $musicianId,
      ],
      'contacts' => [
        'addressBooks' => self::flattenAdressBooks($contactsManager->getUserAddressBooks()),
      ],
    ]);

    // just admin contact and stuff to make the ajax error handlers work.
    // @todo Replace by more lightweight stuff
    $groupManager = $this->appContainer->get(\OCP\IGroupManager::class);
    $initialState->provideInitialState('CAFEVDB', [
      'adminContact' => $this->getCloudAdminContacts($groupManager, implode: true),
      'phpUserAgent' => $_SERVER['HTTP_USER_AGENT'], // @@todo get in javescript from request
    ]);

    /** @var AssetService $assetService */
    $assetService = $this->appContainer->get(AssetService::class);
    list('asset' => $scriptAsset,) = $assetService->getJSAsset(self::BASENAME);
    list('asset' => $styleAsset,) = $assetService->getCSSAsset(self::BASENAME);
    \OCP\Util::addScript($appName, $scriptAsset);
    \OCP\Util::addStyle($appName, $styleAsset);
  }
}



// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

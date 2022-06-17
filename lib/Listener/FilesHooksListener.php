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

    $sharedFolder = $encryptionService->getConfigValue(ConfigService::SHARED_FOLDER, '');
    $templatesFolder = $encryptionService->getConfigValue(ConfigService::DOCUMENT_TEMPLATES_FOLDER, '');

    $this->logger = $this->appContainer->get(\OCP\ILogger::class);

    /** @var EntityManager $entityManager */
    $entityManager = $this->appContainer->get(EntityManager::class);
    try {
      $musicianId = $entityManager->getRepository(Entities\Musician::class)->findIdByUserId($userId);
      $this->logInfo('MUS ID ' . $musicianId);
    } catch (\Throwable $t) {
      // ignore
      $this->logException($t);
      $musicianId = 0;
    }

    /** @var IContactsManager $contactsManager */
    $contactsManager = $this->appContainer->get(IContactsManager::class);

    $initialState->provideInitialState('files', [
      'sharing' => [
        'files' => [
          'root' => '/' . $sharedFolder,
          'templates' => '/' . $sharedFolder . '/' . $templatesFolder,
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

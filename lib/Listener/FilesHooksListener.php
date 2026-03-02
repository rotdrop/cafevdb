<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2026 Claus-Justus Heine
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

use Throwable;

use Psr\Log\LoggerInterface as ILogger;
use Psr\Log\LogLevel;

use Psr\Container\ContainerInterface;
use OCP\AppFramework\Services\IInitialState;
use OCP\Contacts\IManager as IContactsManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\IUserSession;

use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCA\Files\Event\LoadSidebar;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Controller\DTO\FilesInitialState;
use OCA\CAFEVDB\Controller\EnumInitialStateKey;
use OCA\CAFEVDB\Controller\EnumPersonalSettingsKey;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\L10N\AppL10N;
use OCA\CAFEVDB\Settings\ConfigConstants;

/** Listener for hooking up an additional context menu entry. */
class FilesHooksListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ApiRequestTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\CloudAdminTrait;
  use \OCA\CAFEVDB\Traits\ContactsTrait;
  use \OCA\CAFEVDB\Storage\Database\DatabaseStorageNodeNameTrait;

  public const EVENT = [
    LoadAdditionalScriptsEvent::class,
    LoadSidebar::class,
  ];

  private const ASSET_BASENAME = [
    LoadAdditionalScriptsEvent::class => [
      Constants::JS => 'files-hooks',
      Constants::CSS => null,
    ],
    LoadSidebar::class => [
      Constants::JS => 'files-sidebar-hooks',
      Constants::CSS => null,
    ],
  ];

  /** @var array */
  private $handled = [
    LoadAdditionalScriptsEvent::class => false,
    LoadSidebar::class => false,
  ];

  /** @var bool */
  private $initialStateEmitted = false;

  /**
   * @param ContainerInterface $appContainer The only argument in order to have a
   * small CTOR footprint.
   */
  public function __construct(
    protected ContainerInterface $appContainer,
  ) {
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.Superglobals)
   */
  public function handle(Event $event): void
  {
    $eventClass = get_class($event);
    if (!in_array($eventClass, self::EVENT)) {
      return;
    }

    // this really only needs to be executed once per request.
    if ($this->handled[$eventClass]) {
      return;
    }
    $this->handled[$eventClass] = true;

    $this->logger = $this->appContainer->get(ILogger::class);

    $request = $this->appContainer->get(IRequest::class);
    if ($this->isNonInteractiveRequest($request, LogLevel::DEBUG)) {
      return;
    }

    /** @var IUserSession $userSession */
    $userSession = $this->appContainer->get(IUserSession::class);

    if (!$userSession->isLoggedIn()) {
      // the scripts loaded here need authentication, so ...
      return;
    }

    $user = $userSession->getUser();
    if (empty($user)) {
      return;
    }

    $userId = $user->getUID();

    $authorization = $this->appContainer->get(AuthorizationService::class);
    if (!$authorization->authorized($userId, AuthorizationService::PERMISSION_FILESYSTEM)) {
      return;
    }

    $appName = $this->appContainer->get('appName');

    if (!$this->initialStateEmitted) {
      // This needs only to done once per request, the initial state is the
      // same for both request, at least ATM ...

      /** @var IInitialState $initialState */
      $initialState = $this->appContainer->get(IInitialState::class);

      /** @var EncryptionService $encryptionService */
      $encryptionService = $this->appContainer->get(EncryptionService::class);
      $this->logger = $this->appContainer->get(ILogger::class);
      $this->l = $this->appContainer->get(\OCP\IL10N::class);
      $appL10n = $this->appContainer->get(AppL10N::class);

      $sharedFolder = $encryptionService->getConfigValue(ConfigConstants::SHARED_FOLDER, '');
      $templatesFolder = $encryptionService->getConfigValue(ConfigConstants::DOCUMENT_TEMPLATES_FOLDER, '');
      $financeFolder = $encryptionService->getConfigValue(ConfigConstants::FINANCE_FOLDER);
      $balancesFolder = $encryptionService->getConfigValue(ConfigConstants::BALANCES_FOLDER);
      $projectsFolder = $encryptionService->getConfigValue(ConfigConstants::PROJECTS_FOLDER);
      $supportingDocumentsFolder = $this->getSupportingDocumentsFolderName();
      $projectParticipantsFolder = $encryptionService->getConfigValue(ConfigConstants::PROJECT_PARTICIPANTS_FOLDER);
      $debugMode = $encryptionService->getUserValue(EnumPersonalSettingsKey::DEBUG_MODE, 0, $userId);

      /** @var EntityManager $entityManager */
      $entityManager = $this->appContainer->get(EntityManager::class);
      try {
        $musician = $entityManager->getRepository(Entities\Musician::class)->findByUserId($userId);
      } catch (Throwable $t) {
        // ignore
        $this->logException($t);
        $musician = null;
      }
      if ($musician === null) {
        $this->logInfo('No Musician for uid ' . $userId);
        $musicianId = 0;
        $musicianPublicName = null;
        $musicianPersonalPublicName = null;
      } else {
        /** @var Entities\Musician $musician */
        $musicianId = $musician->getId();
        $musicianPublicName = $musician->getPublicName(firstNameFirst: false);
        $musicianPersonalPublicName = $musician->getPublicName(firstNameFirst: true);
      }

      /** @var IContactsManager $contactsManager */
      $contactsManager = $this->appContainer->get(IContactsManager::class);

      $sharedFolder = '/' . $sharedFolder;
      $templatesFolder = $sharedFolder . '/' . $templatesFolder;
      $financeFolder = $sharedFolder . '/' . $financeFolder;
      $balancesFolder = $financeFolder . '/' . $balancesFolder;
      $projectBalancesFolder = $balancesFolder . '/' . $projectsFolder;
      $projectManagementFolder = $sharedFolder . '/' . $projectsFolder;
      $invoicesFolder = $financeFolder . '/' . $appL10n->t('invoices');
      $donationReceiptsFolder = $financeFolder . '/' . $appL10n->t('donation-receipts');

      $initialState->provideInitialState(EnumInitialStateKey::FILES->value, FilesInitialState::fromArray([
        'sharing' => [
          'files' => [
            'folders' => [
              // absolute paths
              'root' => $sharedFolder,
              'balances' => $balancesFolder,
              'donationReceipts' => $donationReceiptsFolder,
              'finance' => $financeFolder,
              'invoices' => $invoicesFolder,
              'projectBalances' => $projectBalancesFolder,
              'projectManagement' => $projectManagementFolder,
              'templates' => $templatesFolder,
            ],
            'subFolders' => [
              // relative paths
              'supportingDocuments' => $supportingDocumentsFolder,
              'projectParticipants' => $projectParticipantsFolder,
            ],
          ],
        ],
        'personal' => [
          'userId' => $userId,
          'musicianId' => $musicianId,
          'musicianPublicName' => $musicianPublicName,
          'musicianPersonalPublicName' => $musicianPersonalPublicName,
        ],
        'contacts' => [
          'addressBooks' => self::flattenAddressBooks($contactsManager->getUserAddressBooks()),
        ],
        EnumPersonalSettingsKey::DEBUG_MODE->value => $debugMode,
      ]));

      // just admin contact and stuff to make the ajax error handlers work.
      // @todo Replace by more lightweight stuff
      $this->groupManager = $this->appContainer->get(\OCP\IGroupManager::class);
    }

    /** @var AssetService $assetService */
    $assetService = $this->appContainer->get(AssetService::class);
    $assetBasename = self::ASSET_BASENAME[$eventClass][Constants::JS];
    if ($assetBasename) {
      try {
        list('asset' => $scriptAsset,) = $assetService->getJSAsset($assetBasename);
        \OCP\Util::addScript($appName, $scriptAsset);
      } catch (Throwable $t) {
        $this->logException($t, 'Unable to add script asset ' . $assetBasename);
      }
    }
    $assetBasename = self::ASSET_BASENAME[$eventClass][Constants::CSS];
    if ($assetBasename) {
      try {
        list('asset' => $styleAsset,) = $assetService->getCSSAsset($assetBasename);
        \OCP\Util::addStyle($appName, $styleAsset);
      } catch (Throwable $t) {
        $this->logException($t, 'Unable to add style asset ' . $assetBasename);
      }
    }
  }
}

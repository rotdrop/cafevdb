<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2014-2026 Claus-Justus Heine
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

namespace OCA\CAFEVDB\AppInfo;

use Exception;

/*-*********************************************************
 *
 * Bootstrap
 *
 */

use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

/*
 *
 **********************************************************
 *
 * Navigation and settings depending on the group-membership
 *
 */
use OCP\Settings\IManager as ISettingsManager;

use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Settings\Personal;
use OCA\CAFEVDB\Settings\PersonalSection;

/*
 *
 **********************************************************
 *
 * Services and listeners.
 *
 */

use OCA\CAFEVDB\AddressBook\Registration as AddressBookRegistration;
use OCA\CAFEVDB\Crypto\Registration as CryptoRegistration;
use OCA\CAFEVDB\Database\Registration as DatabaseRegistration;
use OCA\CAFEVDB\Listener\Registration as ListenerRegistration;
use OCA\CAFEVDB\PageRenderer\Registration as PageRendererRegistration;
use OCA\CAFEVDB\Service\Registration as ServiceRegistration;
use OCA\CAFEVDB\Storage\Database\Registration as StorageRegistration;
use OCA\CAFEVDB\Toolkit\AppInfo\AbstractApplication;

/*
 *
 **********************************************************
 *
 */

use OCA\CAFEVDB\AddressBook\ContactsAddressBook;
use OCA\CAFEVDB\Middleware;

/*
 *
 **********************************************************
 *
 * Mount data-base storage
 *
 */

use OCP\Files\Config\IMountProviderCollection;

use OCA\CAFEVDB\Storage\Database\MountProvider as DatabaseMountProvider;

/*
 *
 **********************************************************
 *
 * Link to the members app
 *
 */
use OCA\CAFeVDBMembers;

// phpcs:disable PSR1.Files.SideEffects
include_once __DIR__ . '/../Toolkit/AppInfo/AbstractApplication.php';
// phpcs:enable PSR1.Files.SideEffects

/** {@inheritdoc} */
class Application extends AbstractApplication
{
  public const APP_ROOT_FOLDER = 'appRootFolder';

  public const MEMBERS_APP_NAME = 'membersAppName';

  protected static string $membersAppName;

  /**
   * Reads off the app-name from the info.xml file.
   *
   * @return string
   */
  public static function getMembersAppName(): string
  {
    return self::$membersAppName ?? (self::$membersAppName = CAFeVDBMembers\AppInfo\Application::getAppName());
  }

  /**
   * {@inheritdoc}
   *
   * Called later than "register".
   */
  public function boot(IBootContext $context): void
  {
    parent::boot($context);

    $context->injectFn(function(
      $userId,
      AuthorizationService $authorizationService,
      ISettingsManager $settingsManager,
    ) {
      if ($authorizationService->authorized($userId, AuthorizationService::PERMISSION_FRONTEND)) {
        $settingsManager->registerSection('personal', PersonalSection::class);
        $settingsManager->registerSetting('personal', Personal::class);
      }
    });

    $context->injectFn(function(
      \OCP\Contacts\IManager $contactsManager
    ) {
      $contactsManager->register(function() use ($contactsManager) {
        $addressBook = $this->getContainer()->get(ContactsAddressBook::class);
        if (!empty($addressBook)) {
          $contactsManager->registerAddressBook($addressBook);
        }
      });
    });

    $context->injectFn(function(IMountProviderCollection $mountProviderCollection, DatabaseMountProvider $mountProvider) {
      $mountProviderCollection->registerProvider($mountProvider, PHP_INT_MAX);
    });
  }

  /**
   * {@inheritdoc}
   *
   * Called earlier than boot, so anything initialized in the
   * "boot()" method must not be used here.
   */
  public function register(IRegistrationContext $context): void
  {
    parent::register($context);
    if ((include_once __DIR__ . '/../../vendor-wrapped/autoload.php') === false) {
      throw new Exception('Cannot include wrapped-autoload. Did you run install dependencies using composer?');
    }

    $context->registerService(self::APP_ROOT_FOLDER, function($c) {
      // ok, we are two levels below the top ...
      return dirname(dirname(__DIR__));
    });

    $context->registerService(self::MEMBERS_APP_NAME, fn($c) => self::getMembersAppName());

    // Register Middleware
    $context->registerMiddleWare(Middleware\ExceptionMiddleware::class); // must come first
    $context->registerMiddleWare(Middleware\SubAdminMiddleware::class);
    $context->registerMiddleWare(Middleware\GroupMemberMiddleware::class);
    $context->registerMiddleWare(Middleware\DebugModeMiddleware::class);
    $context->registerMiddleware(Middleware\ConfigLockMiddleware::class);
    $context->registerMiddleware(Middleware\ContentSecurityPolicyMiddleware::class);

    AddressBookRegistration::register($context);
    CryptoRegistration::register($context);
    DatabaseRegistration::register($context);
    ListenerRegistration::register($context);
    PageRendererRegistration::register($context);
    ServiceRegistration::register($context);
    StorageRegistration::register($context);

    $context->registerNotifierService(\OCA\CAFEVDB\Notifications\Notifier::class);
  }
}

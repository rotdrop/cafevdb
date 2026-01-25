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

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;

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
use OCA\CAFEVDB\Listener\Registration as ListenerRegistration;
use OCA\CAFEVDB\PageRenderer\Registration as PageRendererRegistration;
use OCA\CAFEVDB\Service\Registration as ServiceRegistration;
use OCA\CAFEVDB\Storage\Database\Registration as StorageRegistration;

/*
 *
 **********************************************************
 *
 */

use OCA\CAFEVDB\AddressBook\ContactsAddressBook;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Middleware;
use OCA\CAFEVDB\Wrapped\Doctrine\ORM\Utility\IdentifierFlattener;

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
if ((include_once __DIR__ . '/../../vendor/autoload.php') === false) {
  include_once __DIR__ . '/../Toolkit/Traits/AppNameTrait.php';
}

/** {@inheritdoc} */
class Application extends App implements IBootstrap
{
  use \OCA\CAFEVDB\Toolkit\Traits\AppNameTrait;

  public const APP_ROOT_FOLDER = 'appRootFolder';

  public const MEMBERS_APP_NAME = 'membersAppName';

  /** @var IAppContainer */
  protected static $appContainer;

  protected static string $appName;

  protected static string $membersAppName;

  /** {@inheritdoc} */
  public function __construct(array $urlParams = [])
  {
    self::getAppName();
    parent::__construct(self::$appName, $urlParams);
  }

  /**
   * Reads off the app-name from the info.xml file.
   *
   * @return string
   */
  public static function getAppName(): string
  {
    return self::$appName ?? (self::$appName = self::getAppInfoAppName(__DIR__));
  }

  /**
   * Reads off the app-name from the info.xml file.
   *
   * @return string
   */
  public static function getMembersAppName(): string
  {
    return self::$membersAppName ?? (self::$membersAppName = CAFeVDBMembers\AppInfo\Application::getAppName());
  }

  /** {@inheritdoc} */
  public function __destruct()
  {
    self::$appContainer = null;
  }

  /**
   * Static query of a service through the app container.
   *
   * @param string $service
   *
   * @return mixed
   */
  public static function get(string $service)
  {
    if (!(self::$appContainer instanceof IAppContainer)) {
      throw new Exception('Dependency injection not possible, app-container is empty.');
    }
    return self::$appContainer->get($service);
  }

  /**
   * {@inheritdoc}
   *
   * Called later than "register".
   */
  public function boot(IBootContext $context): void
  {
    self::$appContainer = $this->getContainer();

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
    if ((include_once __DIR__ . '/../../vendor/autoload.php') === false) {
      throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
    }
    if ((include_once __DIR__ . '/../../vendor-wrapped/autoload.php') === false) {
      throw new Exception('Cannot include wrapped-autoload. Did you run install dependencies using composer?');
    }

    $context->registerService(self::APP_ROOT_FOLDER, function($c) {
      // ok, we are two levels below the top ...
      return dirname(dirname(__DIR__));
    });

    $context->registerService(self::MEMBERS_APP_NAME, fn($c) => self::getMembersAppName());

    /* Doctrine DBAL needs a factory to be constructed. */
    $context->registerService(\OCA\CAFEVDB\Database\Connection::class, function($c) {
      return $c->query(EntityManager::class)->getConnection();
    });

    $context->registerService(IdentifierFlattener::class, function($c) {
      $entityManager = $c->query(EntityManager::class);
      return new IdentifierFlattener(
        $entityManager->getUnitOfWork(),
        $entityManager->getMetadataFactory(),
      );
    });

    // Register Middleware
    $context->registerMiddleWare(Middleware\ExceptionMiddleware::class); // must come first
    $context->registerMiddleWare(Middleware\SubAdminMiddleware::class);
    $context->registerMiddleWare(Middleware\GroupMemberMiddleware::class);
    $context->registerMiddleWare(Middleware\DebugModeMiddleware::class);
    $context->registerMiddleware(Middleware\ConfigLockMiddleware::class);
    $context->registerMiddleware(Middleware\ContentSecurityPolicyMiddleware::class);

    // Register listeners
    ListenerRegistration::register($context);

    // Register PageRenderer stuff
    PageRendererRegistration::register($context);

    // Register Service stuff
    ServiceRegistration::register($context);

    // Register Storage stuff
    StorageRegistration::register($context);

    // Register crypto implementation
    CryptoRegistration::register($context);

    AddressBookRegistration::register($context);

    $context->registerNotifierService(\OCA\CAFEVDB\Notifications\Notifier::class);
  }
}

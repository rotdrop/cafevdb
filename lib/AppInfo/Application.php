<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2014-2024 Claus-Justus Heine
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

use SimpleXMLElement;
use Exception;

/*-*********************************************************
 *
 * Bootstrap
 *
 */

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\IAppContainer;

use OCP\AppFramework\App;
use OCP\IL10N;

use OC\L10N\Events\TranslationNotFound;
use OCA\CAFEVDB\Listener\TranslationNotFoundListener;

/*
 *
 **********************************************************
 *
 * Navigation and settings depending on the group-membership
 *
 */
use OCP\INavigationManager;
use OCP\Settings\IManager as ISettingsManager;
use OCP\IURLGenerator;

use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\AssetService;
use OCA\CAFEVDB\Settings\Personal;
use OCA\CAFEVDB\Settings\PersonalSection;

/*
 *
 **********************************************************
 *
 * Events and listeners
 *
 */

use OCA\CAFEVDB\Listener\Registration as ListenerRegistration;
use OCA\CAFEVDB\PageRenderer\Registration as PageRendererRegistration;
use OCA\CAFEVDB\Service\Registration as ServiceRegistration;
use OCA\CAFEVDB\Crypto\Registration as CryptoRegistration;
use OCA\CAFEVDB\Storage\Database\Registration as StorageRegistration;

use OCP\EventDispatcher\IEventDispatcher;

/*
 *
 **********************************************************
 *
 */

use OCA\CAFEVDB\Service\DatabaseService;
use OCA\CAFEVDB\Database\EntityManager;

use OCA\CAFEVDB\Service\EventsService;

use OCA\CAFEVDB\Middleware\SubadminMiddleware;
use OCA\CAFEVDB\Middleware\GroupMemberMiddleware;
use OCA\CAFEVDB\Middleware;

use OCA\CAFEVDB\AddressBook\AddressBookProvider;

/*
 *
 **********************************************************
 *
 * Mount data-base storage
 *
 */

use OCP\Files\Config\IMountProviderCollection;
use OCA\CAFEVDB\Storage\Database\MountProvider as DatabaseMountProvider;

// phpcs:disable PSR1.Files.SideEffects
if ((include_once __DIR__ . '/../../vendor/autoload.php') === false) {
  include_once __DIR__ . '/../Toolkit/Traits/AppNameTrait.php';
}

/** {@inheritdoc} */
class Application extends App implements IBootstrap
{
  use \OCA\CAFEVDB\Toolkit\Traits\AppNameTrait;

  /** @var IAppContainer */
  protected static $appContainer;

  /** @var string */
  protected $appName;

  /** {@inheritdoc} */
  public function __construct(array $urlParams = [])
  {
    $this->appName = $this->getAppInfoAppName(__DIR__);
    parent::__construct($this->appName, $urlParams);
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
      IURLGenerator $urlGenerator,
      INavigationManager $navigationManager,
    ) {
      if ($authorizationService->authorized($userId, AuthorizationService::PERMISSION_FRONTEND)) {
        $navigationManager->add([
          'id' => $this->appName,
          'name' => 'CAFeVDB',
          'href' => $urlGenerator->linkToRoute(implode('.', [ $this->appName, 'page', 'index' ])),
          'icon' => $urlGenerator->imagePath($this->appName, 'app.svg'),
          'type' => 'link',
          'order' => 1,
        ]);
      }
    });

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
        $provider = $this->getContainer()->query(AddressBookProvider::class);
        $addressBook = $provider->getContactsAddressBook();
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
    if ((include_once __DIR__ . '/../Common/Functions.php') === false) {
      throw new Exception('Cannot include common functions.');
    }
    if ((include_once __DIR__ . '/../../vendor/autoload.php') === false) {
      throw new Exception('Cannot include autoload. Did you run install dependencies using composer?');
    }
    if ((include_once __DIR__ . '/../../vendor-wrapped/autoload.php') === false) {
      throw new Exception('Cannot include wrapped-autoload. Did you run install dependencies using composer?');
    }

    /* Doctrine DBAL needs a factory to be constructed. */
    $context->registerService(\OCA\CAFEVDB\Database\Connection::class, function($c) {
      return $c->query(EntityManager::class)->getConnection();
    });

    // Register Middleware
    $context->registerServiceAlias('SubadminMiddleware', SubadminMiddleware::class);
    $context->registerMiddleWare('SubadminMiddleware');
    $context->registerServiceAlias('GroupMemberMiddleware', GroupMemberMiddleware::class);
    $context->registerMiddleWare('GroupMemberMiddleware');
    $context->registerMiddleWare(Middleware\CSPViolationReporting::class);
    $context->registerMiddleware(Middleware\ConfigLockMiddleware::class);

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

    $context->registerNotifierService(\OCA\CAFEVDB\Notifications\Notifier::class);
  }
}

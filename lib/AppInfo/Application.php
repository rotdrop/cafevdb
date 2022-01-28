<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2022
 */

namespace OCA\CAFEVDB\AppInfo;

/**********************************************************
 *
 * Bootstrap
 *
 */

use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;

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

class Application extends App implements IBootstrap
{
  /** @var string */
  protected $appName;

  public function __construct (array $urlParams = [])
  {
    $infoXml = new \SimpleXMLElement(file_get_contents(__DIR__ . '/../../appinfo/info.xml'));
    $this->appName = (string)$infoXml->id;
    parent::__construct($this->appName, $urlParams);
  }

  // Called later than "register".
  public function boot(IBootContext $context): void
  {
    $context->injectFn(function(
      $userId
      , AuthorizationService $authorizationService
      , IURLGenerator $urlGenerator
      , INavigationManager $navigationManager
    ) {
      if ($authorizationService->authorized($userId))  {
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
      $userId
      , AuthorizationService $authorizationService
      , IURLGenerator $urlGenerator
      , ISettingsManager $settingsManager
    ) {
      if ($authorizationService->authorized($userId)) {
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

  // Called earlier than boot, so anything initialized in the
  // "boot()" method must not be used here.
  public function register(IRegistrationContext $context): void
  {
    if ((@include_once __DIR__ . '/../Common/Functions.php') === false) {
      throw new \Exception('Cannot include common functions.');
    }
    if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
      throw new \Exception('Cannot include autoload. Did you run install dependencies using composer?');
    }
    if ((@include_once __DIR__ . '/../../vendor-wrapped/autoload.php') === false) {
      throw new \Exception('Cannot include wrapped-autoload. Did you run install dependencies using composer?');
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

    // Register listeners
    ListenerRegistration::register($context);

    // Register PageRenderer stuff
    PageRendererRegistration::register($context);

    // Register Service stuff
    ServiceRegistration::register($context);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

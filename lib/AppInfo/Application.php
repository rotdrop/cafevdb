<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2021
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
 * Events and listeners
 *
 */

use OCA\CAFEVDB\Listener\Registration as ListenerRegistration;
use OCA\CAFEVDB\PageRenderer\Registration as PageRendererRegistration;

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

class Application extends App implements IBootstrap
{
  /** @var string */
  protected $appName;

  public function __construct (array $urlParams=array()) {
    $infoXml = new \SimpleXMLElement(file_get_contents(__DIR__ . '/../../appinfo/info.xml'));
    $this->appName = (string)$infoXml->id;
    parent::__construct($this->appName, $urlParams);
  }

  // Called later than "register".
  public function boot(IBootContext $context): void
  {
    //trigger_error("cafevdb app");

    $container = $context->getAppContainer();

    /* @var IEventDispatcher $eventDispatcher */
    $dispatcher = $container->query(IEventDispatcher::class);

    // @@todo remove
    $dispatcher->addListener(
      \OCP\AppFramework\Http\TemplateResponse::EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN,
      function() {
        //\OCA\CAFEVDB\Common\Util::addExternalScript("https://maps.google.com/maps/api/js?sensor=false");
      }
    );
  }

  // Called earlier than boot, so anything initialized in the
  // "boot()" method must not be used here.
  public function register(IRegistrationContext $context): void
  {
    if ((@include_once __DIR__ . '/../../vendor/autoload.php') === false) {
      throw new \Exception('Cannot include autoload. Did you run install dependencies using composer?');
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
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

<?php
/**
 * Nextcloud - cafevdb
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright Claus-Justus Heine 2014-2020
 */

namespace OCA\CAFEVDB\AppInfo;

use OCP\AppFramework\App;
use OCP\IL10N;
use OCP\EventDispatcher\IEventDispatcher;

use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedOutEvent;
use OCP\User\Events\PasswordUpdatedEvent;

use OCA\CAFEVDB\Listener\UserLoggedInEventListener;
use OCA\CAFEVDB\Listener\UserLoggedOutEventListener;
use OCA\CAFEVDB\Listener\PasswordUpdatedEventListener;

use OCA\CAFEVDB\Service\DatabaseService;
use OCA\CAFEVDB\Service\DatabaseFactory;

use OCA\CAFEVDB\Middleware\SubadminMiddleware;

class Application extends App {

    public function __construct (array $urlParams=array()) {
        parent::__construct('cafevdb', $urlParams);

        $container = $this->getContainer();

        // Register Middleware
        $container->registerAlias('SubadminMiddleware', SubadminMiddleware::class);
        $container->registerMiddleWare('SubadminMiddleware');

        /* @var IEventDispatcher $eventDispatcher */
        $dispatcher = $container->query(IEventDispatcher::class);
        $dispatcher->addServiceListener(UserLoggedInEvent::class, UserLoggedInEventListener::class);
        $dispatcher->addServiceListener(UserLoggedOutEvent::class, UserLoggedOutEventListener::class);
        $dispatcher->addServiceListener(PasswordUpdatedEvent::class, PasswordUpdatedEventListener::class);

        $container->registerService(DatabaseService::class, function($c) {
            $factory = $c->query('\OCA\CAFEVDB\Service\DatabaseFactory');
            return $factory->getService();
        });
    }

}

// Local Variables: ***
// c-basic-offset: 4 ***
// indent-tabs-mode: nil ***
// End: ***

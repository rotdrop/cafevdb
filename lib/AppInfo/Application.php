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

use OCA\DAV\Events\CalendarUpdatedEvent;

use OCA\CAFEVDB\Listener\UserLoggedInEventListener;
use OCA\CAFEVDB\Listener\UserLoggedOutEventListener;
use OCA\CAFEVDB\Listener\PasswordUpdatedEventListener;

use OCA\CAFEVDB\Service\DatabaseService;
use OCA\CAFEVDB\Service\DatabaseFactory;

use OCA\CAFEVDB\Service\EventsService;

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

        /* EventsServices listener */
        $dispatcher->addListener(
            CalendarUpdatedEvent::class, function(CalendarUpdatedEvent $event) use ($container) {
                $container->query(EventsService::class)->onCalendarUpdate($event);
            });

        /* Doctrine DBAL needs a factory to be constructed. */
        $container->registerService(DatabaseService::class, function($c) {
            return $c->query(DatabaseFactory::class)->getService();
        });
    }

}

// Local Variables: ***
// c-basic-offset: 4 ***
// indent-tabs-mode: nil ***
// End: ***

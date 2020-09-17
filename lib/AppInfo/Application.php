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

class Application extends App {

    public function __construct (array $urlParams=array()) {
        parent::__construct('cafevdb', $urlParams);

        $container = $this->getContainer();

        /* @var IEventDispatcher $eventDispatcher */
        $dispatcher = $container->query(IEventDispatcher::class);
        $dispatcher->addServiceListener(UserLoggedInEvent::class, UserLoggedInEventListener::class);
        $dispatcher->addServiceListener(UserLoggedOutEvent::class, UserLoggedOutEventListener::class);
        $dispatcher->addServiceListener(PasswordUpdatedEvent::class, PasswordUpdatedEventListener::class);

        // /**
        //  * Controllers
        //  */
        // $container->registerService('PageController', function($c) {
        //     return new PageController(
        //         $c->query('AppName'),
        //         $c->query('Request'),
        //         $c->query('L10N')
        //     );
        // });
    }

}

// Local Variables: ***
// c-basic-offset: 4 ***
// indent-tabs-mode: nil ***
// End: ***

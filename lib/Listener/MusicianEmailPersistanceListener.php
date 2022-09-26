<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021, 2022 Claus-Justus Heine
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
 */

namespace OCA\CAFEVDB\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\ILogger;

use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Events;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Maintenance\Sanitizers;
use OCA\CAFEVDB\Maintenance\SanitizerRegistration;

/** Make sure that known sanitizations are performed. */
class MusicianEmailPersistanceListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const EVENT = [
    Events\PreRemoveMusicianEmail::class,
    Events\PrePersistMusicianEmail::class,
  ];

  /** @var IAppContainer */
  private $appContainer;

  /**
   * @param IAppContainer $appContainer App-container in order to have a leight-weight constructor.
   */
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    $eventClass = get_class($event);
    if (array_search($eventClass, self::EVENT) === false) {
      return;
    }

    $sanitizers = SanitizerRegistration::getSanitizers(Entities\MusicianEmailAddress::class);

    /** @var Events\MusicianEmailEvent $event */
    switch (true) {
      case $event instanceof Events\PrePersistMusicianEmail:
        foreach ($sanitizers as $sanitizerClass) {
          try {
            /** @var Sanitizers\AbstractSanitizer $sanitizer */
            $sanitizer = $this->appContainer->get($sanitizerClass);
            $sanitizer->setEntity($event->getEntity());
            $sanitizer->sanitizePersist(flush: false);
          } catch (Exceptions\SanitizerNotNeededException $e) {
            // ignore
          } catch (Exceptions\SanitizerNotImplementedException $e) {
            // ignore
          }
        }
        break;
      case $event instanceof Events\PreRemoveMusicianEmail:
        foreach ($sanitizers as $sanitizerClass) {
          try {
            /** @var Sanitizers\AbstractSanitizer $sanitizer */
            $sanitizer = $this->appContainer->get($sanitizerClass);
            $sanitizer->setEntity($event->getEntity());
            $sanitizer->sanitizeRemove(flush: false);
          } catch (Exceptions\SanitizerNotNeededException $e) {
            // ignore
          } catch (Exceptions\SanitizerNotImplementedException $e) {
            // ignore
          }
        }
        break;
    }
  }
}

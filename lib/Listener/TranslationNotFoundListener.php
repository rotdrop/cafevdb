<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020, 2021 Claus-Justus Heine <himself@claus-justus-heine.de>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Listener;

use OCP\ILogger;
use OCP\IUserSession;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OC\L10N\Events\TranslationNotFound as HandledEvent;

use OCA\CAFEVDB\Service\L10N\TranslationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;

class TranslationNotFoundListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var string */
  protected $appName;

  /** @var IUser */
  private $user;

  public function __construct(
    $appName
    , IUserSession $userSession
    , ILogger $logger
  ) {
    $this->appName = $appName;
    $this->user = $userSession->getUser();
    $this->logger = $logger;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }
    $appName = $event->getAppName();
    if ($appName != $this->appName) {
      return;
    }
    if (empty($this->user)) {
      return;
    }
    $debugMode = ConfigService::DEBUG_NONE;
    try {
      $debugMode = \OC::$server->query(EncryptionService::class)->getConfigValue('debugmode', ConfigService::DEBUG_NONE);
    } catch (\Throwable $t) {
      // just ignore
    }
    if (($debugMode & ConfigService::DEBUG_L10N) == 0) {
      $this->logDebug('Debugging L10N is not enabled, bailing out');
      return;
    }
    $phrase = $event->getPhrase();
    $locale = $event->getLocale();
    $language = $event->getLanguage();
    $file = $event->getFile();
    $line = $event->getLine();
    $this->logDebug($appName.'; '.$phrase.'; '.$locale.'; '.$language.'; '.$file.'; '.$line);
    try {
      \OC::$server->query(TranslationService::class)->recordUntranslated($phrase, $locale, $file, $line);
    } catch (\Throwable $t) {
      //$this->logDebug('Ignoring data-base errors.');
      $this->logException($t);
    }
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2020, 2021, 2022, 2024 Claus-Justus Heine
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
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\CAFEVDB\Listener;

use Psr\Log\LoggerInterface as ILogger;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OC\L10N\Events\TranslationNotFound as HandledEvent;

use OCA\CAFEVDB\Service\L10N\TranslationService;
use OCA\CAFEVDB\Service\EncryptionService;
use OCA\CAFEVDB\Service\ConfigService;

/** Recorded  untranslated strings. */
class TranslationNotFoundListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var IAppContainer */
  protected $appContainer;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {

    /** @var HandledEvent $event */
    if (!($event instanceof HandledEvent)) {
      return;
    }

    $appName = $this->appContainer->get('appName');

    if ($appName != $event->getAppName()) {
      return;
    }

    /** @var EncryptionService $encryptionService */
    $encryptionService = $this->appContainer->get(EncryptionService::class);
    if (!$encryptionService->bound()) {
      // no point in trying to continue
      return;
    }

    $this->logger = $this->appContainer->get(ILogger::class);

    $debugMode = ConfigService::DEBUG_NONE;
    try {
      $debugMode = (int)$encryptionService->getConfigValue('debugmode', ConfigService::DEBUG_NONE);
    } catch (\Throwable $t) {
      // just ignore
    }
    if (($debugMode & ConfigService::DEBUG_L10N) == 0) {
      // $this->logDebug('Debugging L10N is not enabled, bailing out');
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

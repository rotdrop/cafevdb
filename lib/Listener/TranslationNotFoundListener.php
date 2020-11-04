<?php
/* Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2020 Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\IL10N;
use OCP\ILogger;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OC\L10N\Events\TranslationNotFound as HandledEvent;

use OCA\CAFEVDB\Service\TranslationService;

class TranslationNotFoundListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  /** @var EventsService */
  private $eventsService;

  /** @var string */
  protected $appName;

  /** @var OCA\CAFEVDB\Service\TranslationService */
  protected $translationService;

  public function __construct(
    string $appName
    , ILogger $logger
    , IL10N $l10n
    , TranslationService $translationService
  ) {
    $this->appName = $appName;
    $this->logger = $logger;
    $this->l = $l10n;
    $this->translationService = $translationService;
  }

  public function handle(Event $event): void {
    if (!($event instanceOf HandledEvent)) {
      return;
    }
    $appName = $event->getAppName();
    if ($appName != $this->appName) {
      return;
    }
    $phrase = $event->getPhrase();
    $locale = $event->getLocale();
    $language = $event->getLanguage();
    $file = $event->getFile();
    $line = $event->getLine();
    $this->logInfo(__METHOD__.": ".$appName.'; '.$phrase.'; '.$locale.'; '.$language.'; '.$file.'; '.$line);
    $this->translationService->recordUntranslated($phrase, $locale, $file, $line);
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

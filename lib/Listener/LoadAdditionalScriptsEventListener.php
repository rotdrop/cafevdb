<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

use Throwable;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent as HandledEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\AppFramework\IAppContainer;
use OCP\IRequest;
use Psr\Log\LoggerInterface as ILogger;
use Psr\Log\LogLevel;
use OCP\IConfig;
use OCP\AppFramework\Services\IInitialState;

use OCA\CAFEVDB\Service\AssetService;

/** Load additional scripts while running interactively. */
class LoadAdditionalScriptsEventListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\ApiRequestTrait;

  const EVENT = HandledEvent::class;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    private IAppContainer $appContainer,
  ) {
  }
  // phpcs:enable Squiz.Commenting.FunctionComment.Missing

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (!($event instanceof HandledEvent)) {
      return;
    }
    /** @var HandledEvent $event */

    $this->logger = $this->appContainer->get(ILogger::class);

    $request = $this->appContainer->get(IRequest::class);
    if ($this->isNonInteractiveRequest($request, LogLevel::DEBUG)) {
      return;
    }

    if (!$event->isLoggedIn()) {
      // the scripts loaded here need authentication, so ...
      return;
    }

    try {
      /** @var string $appName */
      $appName = $this->appContainer->get('appName');

      /** @var AssetService $assetService */
      $assetService = $this->appContainer->get(AssetService::class);

      $scriptFile = $assetService->getJSAsset('background-jobs')['asset'];
      \OCP\Util::addScript($appName, $scriptFile);
      $this->logDebug('Added script file "' . $scriptFile . '" to template');
    } catch (Throwable $t) {
      $this->logException($t, 'Unable add the refresh java script while running interactively.');
    }
  }
}

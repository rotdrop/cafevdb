<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2016, 2020-2025 Claus-Justus Heine
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
use Psr\Log\LoggerInterface;

use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent as HandledEvent;
use OCP\AppFramework\IAppContainer;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Documents\TemplateService;
use OCA\CAFEVDB\Service\ImagesService;
use OCA\CAFEVDB\Service\ConfigService;

/**
 * Install the navigation entries if the current user is logged and is
 * authorized.
 */
class BeforeTemplateRenderedListener implements IEventListener
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  const EVENT = HandledEvent::class;

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(
    protected IAppContainer $appContainer,
  ) {
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    if (!($event instanceof HandledEvent)) {
      return;
    }

    $this->logger = $this->appContainer->get(LoggerInterface::class);

    /** @var IUserSession $userSession */
    $userSession = $this->appContainer->get(IUserSession::class);
    $user = $userSession->getUser();
    if (empty($user)) {
      return;
    }
    $userId = $user->getUID();

    /** @var AuthorizationService $authorizationService */
    $authorizationService = $this->appContainer->get(AuthorizationService::class);
    if (!$authorizationService->authorized($userId, AuthorizationService::PERMISSION_FRONTEND)) {
      return;
    }

    /** @var INavigationManager $navigationManager */
    $navigationManager = $this->appContainer->get(INavigationManager::class);

    /** @var IURLGenerator $urlGenerator */
    $urlGenerator = $this->appContainer->get(IURLGenerator::class);

    /** @var string $appName */
    $appName = $this->appContainer->get('appName');

    $appIcon = $urlGenerator->imagePath($appName, 'app.svg');

    try {
      /** @var TemplateService $templateService */
      $templateService = $this->appContainer->get(TemplateService::class);
      $customLogo = $templateService->getDocumentTemplate(ConfigService::DOCUMENT_TEMPLATE_LOGO);

      if ($customLogo) {
        /** @var ImagesService $imagesService */
        $imagesService = $this->appContainer->get(ImagesService::class);
        $customLogo = $imagesService->dataUriFromFile($customLogo, ImagesService::SVG_OPTIMIZE|ImagesService::CONVERT_TO_SVG);
      }
    } catch (Throwable $t) {
      $this->logException($t);
    }

    $navEntry = [
      'id' => $appName,
      'name' => 'CAFeVDB',
      'href' => $urlGenerator->linkToRoute(implode('.', [ $appName, 'page', 'index' ])),
      'icon' => $customLogo ?? $appIcon,
      'type' => 'link',
      'order' => 1,
    ];
    // $this->logInfo('NAV ' . print_r($navEntry, true));

    $navigationManager->add($navEntry);
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright , 20212021, ,  Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\IUser;
use OCP\ILogger;
use OCP\IUserSession;
use OCP\Files\IRootFolder;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Listen to renamed and deleted events in order to keep the
 * configured document-templates synchronized with the cloud
 * file-system.
 */
class FileNodeListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = [ NodeDeletedEvent::class, NodeRenamedEvent::class ];

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
    $eventClass = null;
    foreach (self::EVENT as $handledEvent) {
      if (is_a($event, $handledEvent)) {
        $eventClass = $handledEvent;
      }
    }
    if (empty($eventClass)) {
      return;
    }
    if (empty($this->user)) {
      return;
    }

    /** @var ConfigService $configService */
    $configService = \OC::$server->query(ConfigService::class);
    if (empty($configService)) {
      return;
    }

    /** @var IRootFolder $rootFolder */
    $rootFolder = \OC::$server->query(IRootFolder::class);
    if (empty($rootFolder)) {
      return;
    }
    $userFolder = $rootFolder->getUserFolder($this->user->getUID())->getPath();

    $sharedFolder = $configService->getConfigValue(ConfigService::SHARED_FOLDER);
    $templatesFolder = $configService->getConfigValue(ConfigService::DOCUMENT_TEMPLATES_FOLDER);
    $templatesFolder = $userFolder . '/' . $sharedFolder . '/' . $templatesFolder . '/';

    switch ($eventClass) {
    case NodeDeletedEvent::class:
      /** @var NodeDeletedEvent $event */

      $path = $event->getNode()->getPath();
      $path = self::matchPrefixDirectory($path, $templatesFolder);
      if (empty($path)) {
        return;
      }
      list($templateKey,) = $this->matchDocumentTemplates($path, $configService);
      if (empty($templateKey)) {
        return;
      }

      $this->logInfo('REMOVE CONFIG VALUE FOR '.$templateKey);
      $configService->deleteConfigValue($templateKey);

      break;
    case NodeRenamedEvent::class:
      /** @var NodeRenamedEvent $event */

      $sourcePath = $event->getSource()->getPath();
      $targetPath = $event->getTarget()->getPath();

      $sourcePath = self::matchPrefixDirectory($sourcePath, $templatesFolder);
      if (empty($sourcePath)) {
        return;
      }

      list($templateKey, $subFolder) = $this->matchDocumentTemplates($sourcePath, $configService);
      if (empty($templateKey)) {
        return;
      }

      $targetPath = self::matchPrefixDirectory($targetPath, $templatesFolder . $subFolder);
      if (empty($targetPath)) {
        $this->logInfo('REMOVE CONFIG VALUE FOR '.$templateKey);
        $configService->deleteConfigValue($templateKey);
      } else {
        $this->logInfo('CHANGE CONFIG VALUE FOR '.$templateKey.' from '.$sourcePath.' to '.$targetPath);
        $configService->setConfigValue($templateKey, $targetPath);
      }

      break;
    }
  }

  /**
   * Find the template key $path is referring to by looking at the
   * configured (i.e. uploaded) template files.
   *
   * @param string $path The relative path w.r.t. to the templates
   * folder.
   *
   * @param ConfigService $configService Global config-service object.
   *
   * @return null|string The config-key $path is referring to or null
   * if no key could be determine.
   */
  private function matchDocumentTemplates($path, $configService)
  {
    foreach (ConfigService::DOCUMENT_TEMPLATES as $templateKey => $templateInfo) {
      $subFolder = $templateInfo['folder']??'';
      $subFolderName = empty($subFolder) ? '' : $configService->getConfigValue($subFolder);
      if (!empty($subFolderName)) {
        $subFolderName .= '/';
      }
      $templateFile = $subFolderName . $configService->getConfigValue($templateKey);
      if ($path === $templateFile) {
        return [ $templateKey, $subFolderName ];
      }
    }
    $this->logInfo('NOT A SPECIAL FILE ' . $path);
    return [ null, null ];
  }

  /**
   * @param string $path The path to match
   *
   * @param string $folderPrefix The folder-prefix to compare the
   * first part of the string to.
   *
   * @return null|string The sub-string after remove the $folderPrefix
   * or null if $folderPrefix is not the first part of the string.
   */
  private static function matchPrefixDirectory($path, $folderPrefix)
  {
    if (strpos($path, $folderPrefix) !== 0) {
      return null;
    }
    return substr($path, strlen($folderPrefix));
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

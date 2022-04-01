<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright , 2021, 2022,  Claus-Justus Heine <himself@claus-justus-heine.de>
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
use OCP\Files\Events\Node\NodeWrittenEvent;
// use OCP\Files\Events\Node\NodeCopiedEvent; covered by NodeWrittenEvent
use OCP\Files\Events\Node\NodeRenamedEvent; // covered by NodeWrittenEvent, but we need the source path
use OCP\Files\Events\Node\NodeDeletedEvent; // not covered by NodeWrittenEvent
use OCP\Files\Events\Node\NodeTouchedEvent; // not covered by NodeWrittenEvent
use OCP\IUser;
use OCP\ILogger;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Files\IRootFolder;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Service\MailingListsService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Listen to renamed and deleted events in order to keep the
 * configured document-templates synchronized with the cloud
 * file-system.
 */
class MailingListsAutoResponsesListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  const EVENT = [ NodeRenamedEvent::class, NodeWrittenEvent::class, NodeDeletedEvent::class, NodeTouchedEvent::class ];

  /** @var IL10N */
  private $l;

  /** @var IUser */
  private $user;

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
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

    // initialize only now in order to keep the overhead for unhandled events small
    $this->user = $this->appContainer->get(IUserSession::class)->getUser();
    if (empty($this->user)) {
      return;
    }
    $this->appName = $this->appContainer->get('appName');
    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    $remove = false;
    $eventNode = null;
    switch ($eventClass) {
      case NodeDeletedEvent::class:
        /** @var NodeDeletedEvent $event */
        $eventNode = $event->getNode();
        $remove = true;
        break;
      case NodeRenamedEvent::class:
        /** @var NodeRenamedEvent $event */
        $eventNode = $event->getSource();
        $remove = true;
        // rename gets another NodeWrittenEvent
        break;
      case NodeWrittenEvent::class:
        /** @var NodeWrittenEvent $event */
        $eventNode = $event->getNode();
        break;
      case NodeTouchedEvent::class:
        /** @var NodeTouchedEvent $event */
        $eventNode = $event->getNode();
        break;
      default:
        return;
    }

    // Can ony use plain text files for the autoresponses.
    $eventMimeType = $eventNode->getMimetype();
    if ($eventMimeType != 'text/plain' && $eventMimeType != 'text/markdown') {
      $this->logInfo('NOT A PLAIN TEXT FILE ' . $eventBaseName);
      return;
    }

    $eventPath = $eventNode->getPath();

    // first look at the base name, it must start with one of the known prefixes.
    $eventBaseName = basename($eventPath);
    if (!str_starts_with($eventBaseName, MailingListsService::TEMPLATE_FILE_PREFIX)) {
      $this->logInfo('NOT A TEMPLATE FILE ' . $eventBaseName);
      return;
    }

    /** @var ConfigService $configService */
    $configService = \OC::$server->query(ConfigService::class);
    if (empty($configService)) {
      return;
    }

    /** @var IRootFolder $rootFolder */
    $rootFolder = $this->appContainer->get(IRootFolder::class);
    if (empty($rootFolder)) {
      return;
    }
    $userFolder = $rootFolder->getUserFolder($this->user->getUID())->getPath();

    /** @var MailingListsService $listsService */
    $listsService = $this->appContainer->get(MailingListsService::class);

    $folderPath = rtrim($userFolder . $listsService->templateFolderPath(''), '/');
    if (!$this->matchPrefixDirectory($eventPath, $folderPath)) {
      // not an autoresponse file
      $this->logInfo('UNMATCHED ' . $eventPath . ' not in ' . $folderPath);
      return;
    }

    foreach ([MailingListsService::TYPE_ANNOUNCEMENTS, MailingListsService::TYPE_PROJECTS] as $listType) {
      $templateFolderPath = $listsService->templateFolderPath($this->l->t($listType));
      $folderPath = $userFolder . $templateFolderPath;
      if (!$this->matchPrefixDirectory($eventPath, $folderPath)) {
        $this->logInfo('UNMATCHED ' . $eventPath . ' not in ' . $folderPath);
        continue;
      }
      if ($listType == MailingListsService::TYPE_PROJECTS) {
        $this->logError('Mailing-list type ' . $listType . ' not yet handled');
        continue;
      }
      $template = pathinfo($eventBaseName, PATHINFO_FILENAME);
      $lists = [ $configService->getConfigValue('announcementsMailingList'), ];

      if ($remove) {
        foreach ($lists as $list) {
          $listsService->setMessageTemplate($list, $template, null);
        }
      } else {
        $folderShareUri = $listsService->ensureTemplateFolder($templateFolderPath);
        $templateUri = $folderShareUri . '?path=/&files=' . $eventBaseName;
        foreach ($lists as $list) {
          $listsService->setMessageTemplate($list, $template, $templateUri);
          $this->logInfo('SHARE URI ' . $templateUri);
        }
      }
      break;
    }
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

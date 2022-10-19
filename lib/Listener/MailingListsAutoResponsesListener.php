<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright , 2021, 2022,  Claus-Justus Heine <himself@claus-justus-heine.de>
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
 */

namespace OCA\CAFEVDB\Listener;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\IUser;
use OCP\ILogger;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Files\IRootFolder;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
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

  const EVENT = [
    NodeRenamedEvent::class,
    NodeCopiedEvent::class,
    NodeWrittenEvent::class,
    NodeDeletedEvent::class,
    NodeTouchedEvent::class,
    NodeCreatedEvent::class,
  ];

  const PROJECTS = MailingListsService::TEMPLATE_TYPE_PROJECTS;
  const ANNOUNCEMENTS = MailingListsService::TEMPLATE_TYPE_ANNOUNCEMENTS;

  private const ADD_KEY = 'add';
  private const DEL_KEY = 'remove';

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
    $nodes = [];
    $eventClass = get_class($event);
    switch ($eventClass) {
      case NodeDeletedEvent::class:
        /** @var NodeDeletedEvent $event */
        $nodes[self::DEL_KEY] = $event->getNode();
        break;
      case NodeRenamedEvent::class:
        /** @var NodeRenamedEvent $event */
        $nodes[self::DEL_KEY] = $event->getSource();
        $nodes[self::ADD_KEY] = $event->getTarget();
        $remove = true;
        // rename gets another NodeWrittenEvent
        break;
      case NodeWrittenEvent::class:
        /** @var NodeWrittenEvent $event */
        $nodes[self::ADD_KEY] = $event->getNode();
        break;
      case NodeCopiedEvent::class:
        /** @var NodeCopiedEvent $event */
        $nodes[self::ADD_KEY] = $event->getTarget();
        break;
      case NodeCreatedEvent::class:
      case NodeTouchedEvent::class:
        /** @var NodeTouchedEvent $event */
        $nodes[self::ADD_KEY] = $event->getNode();
        break;
      default:
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

    /** @var \OCP\Files\Node $node */
    foreach ($nodes as $key => $node) {
      if (false && $node instanceof \OC\Files\Node\NonExistingFile) {
        unset($nodes[$key]);
        continue;
      }
      $nodePath = $node->getPath();
      if ($key == self::ADD_KEY)  {
        // Can ony use plain text files for the autoresponses.
        try {
          $eventMimeType = $node->getMimetype();
        } catch (\Throwable $t) {
          // ignore
          $eventMimeType = null;
        }
        if (!empty($eventMimeType) && $eventMimeType != 'text/plain' && $eventMimeType != 'text/markdown') {
          unset($nodes[$key]);
          continue;
        }
      }
      $template = pathinfo($nodePath, PATHINFO_FILENAME);
      // first look at the base name, it must start with one of the known prefixes.
      if (!str_starts_with($template, MailingListsService::TEMPLATE_FILE_PREFIX)) {
        unset($nodes[$key]);
        continue;
      }
    }

    if (empty($nodes)) {
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

    $baseFolderPath = $listsService->templateFolderPath(MailingListsService::TEMPLATE_TYPE_UNSPECIFIC);
    $baseFolderShareUri = $listsService->ensureTemplateFolder($baseFolderPath);
    $baseFolderPath = $userFolder . $baseFolderPath;

    foreach ($nodes as $key => $node) {
      $nodePath = $node->getPath();
      $nodeBase = basename($nodePath);

      if (!$this->matchPrefixDirectory($nodePath, $baseFolderPath)) {
        // not an autoresponse file
        return;
      }

      foreach ([self::ANNOUNCEMENTS, self::PROJECTS] as $listType) {
        $templateFolderPath = $listsService->templateFolderPath($this->l->t($listType));
        $folderPath = $userFolder . $templateFolderPath;

        if (!$this->matchPrefixDirectory($nodePath, $folderPath)) {
          continue;
        }
        if ($listType == MailingListsService::TEMPLATE_TYPE_PROJECTS) {
          if (empty($projectsRepository)) {
            /** @var EntityManager $entityManager */
            $entityManager = $this->appContainer->get(EntityManager::class);

            /** @var Repositories\ProjectsRepository $projectsRepository */
            $projectsRepository = $entityManager->getRepository(Entities\Project::class);

            $projectLists = $projectsRepository->fetchMailingListIds();
          }
          $lists = $projectLists;
        } else {
          $lists = [ $configService->getConfigValue('announcementsMailingList'), ];
        }

        $template = pathinfo($nodeBase, PATHINFO_FILENAME);

        try {
          if ($key == self::DEL_KEY) {
            foreach ($lists as $list) {
              $listsService->setMessageTemplate($list, $template, null);
              $this->logInfo('Removed ' . $template . ' from list ' . $list);
            }
          } else {
            $templateFolderBase = basename($templateFolderPath);
            $templateUri = $baseFolderShareUri . '/download?path=/' . $templateFolderBase . '&files=' . $nodeBase;
            foreach ($lists as $list) {
              $listsService->setMessageTemplate($list, $template, $templateUri);
              $this->logInfo('Added ' . $template . ' to list ' . $list . ', URI ' . $templateUri);
            }
          }
          break;
        } catch (\Throwable $t) {
          $this->logError('Unable to modify template ' . $template);
        }
      }
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

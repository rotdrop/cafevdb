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
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\IUser;
use OCP\ILogger;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Files\IRootFolder;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Storage\UserStorage;

use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Constants;

/**
 * Listen to changes to the configured participant-field cloud-folder
 * directories and update the DB contents accordingly.
 *
 */
class ParticipantFieldCloudFolderListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\LoggerTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  const EVENT = [ NodeRenamedEvent::class, NodeCopiedEvent::class, NodeDeletedEvent::class, NodeTouchedEvent::class ];

  private const ADD_KEY = 'add';
  private const DEL_KEY = 'remove';

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
      case NodeCopiedEvent::class:
        /** @var NodeCopiedEvent $event */
        $nodes[self::ADD_KEY] = $event->getTarget();
        break;
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
    }

    if (empty($nodes)) {
      return;
    }

    $this->configService = $this->appContainer->get(ConfigService::class);
    if (empty($this->configService)) {
      return;
    }

    /** @var IRootFolder $rootFolder */
    $rootFolder = $this->appContainer->get(IRootFolder::class);
    if (empty($rootFolder)) {
      return;
    }
    $userFolder = $rootFolder->getUserFolder($this->user->getUID())->getPath();
    $projectsPath = $userFolder . $this->getProjectsFolderPath();

    foreach ($nodes as $key => $node) {
      $nodePath = $node->getPath();
      $postFixPath = self::matchPrefixDirectory($nodePath, $projectsPath);
      if ($postFixPath === null) {
        unset($nodes[$key]);
      }
      $nodes[$key] = $postFixPath;
    }
    if (empty($nodes)) {
      return;
    }

    $participantsFolder = Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER) . Constants::PATH_SEP;
    foreach ($nodes as $key => $nodePath) {
      if (strpos($nodePath, $participantsFolder) === false) {
        unset($nodes[$key]);
      }
    }
    if (empty($nodes)) {
      return;
    }

    // ok now something remains, get project-name and musician user-id
    $criteria = [];
    foreach ($nodes as $key => $nodePath) {
      $parts = explode(Constants::PATH_SEP, trim($nodePath, Constants::PATH_SEP));
      $projectYear = $parts[0];
      $projectName = $parts[1];
      $userIdSlug = $parts[3];
      $fieldName = $parts[4];
      $nodes[$key] = $parts[5];
      $criteria[$key] = [
        'field.name' => $fieldName,
        'project.year' => $projectYear,
        'project.name' => $projectName,
        'musician.userIdSlug' => $userIdSlug,
      ];
      $flatCriteria[$key] = implode(':', $criteria[$key]);
    }

    $this->entityManager = $this->appContainer->get(EntityManager::class);
    $fieldDataRepository = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class);
    try {
      $fieldData = [];
      foreach (array_keys($nodes) as $key) {
        $flatCriterion = $flatCriteria[$key];
        if (!isset($fieldData[$flatCriterion])) {
          $fieldData[$flatCriterion] = $fieldDataRepository->findOneBy($criteria[$key]);
        }
      }

      $key = self::ADD_KEY;
      if (isset($nodes[$key])) {
        $baseName = $nodes[$key];
        /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
        $fieldDatum = $fieldData[$flatCriteria[$key]];
        if (empty($fieldDatum)) {
          $criterion = $criteria[$key];
          $musician = $this->getDatabaseRepository(Entities\Musician::class)->findOneBy([
            'userIdSlug' => $criterion['musician.userIdSlug']
          ]);
          $project = $this->getDatabaseRepository(Entities\Project::class)->findOneBy([
            'name' => $criterion['project.name'],
            'year' => $criterion['project.year'],
          ]);
          /** @var Entities\ProjectParticipantField $field */
          $field = $this->getDatabaseRepository(Entities\ProjectParticipantField::class)->findOneBy([
            'project' => $project,
            'name' => $criterion['field.name'],
          ]);
          $dataOption = $field->getDataOption();
          $fieldDatum = (new Entities\ProjectParticipantFieldDatum)
            ->setProject($project)
            ->setMusician($musician)
            ->setField($field)
            ->setDataOption($dataOption);
        }
        $files = json_decode($fieldDatum->getOptionValue(), true);
        $files[] = $baseName;
        sort($files);
        $fieldDatum->setOptionValue(json_encode($files));
        $this->persist($fieldDatum);
      }

      $key = self::DEL_KEY;
      if (isset($nodes[$key])) {
        $baseName = $nodes[$key];
        $fieldDatum = $fieldData[$flatCriteria[$key]] ?? null;
        if (!empty($fieldDatum)) {
          $files = json_decode($fieldDatum->getOptionValue(), true);
          Util::unsetValue($files, $baseName);
          if (empty($files)) {
            $this->remove($fieldDatum);
          } else {
            $fieldDatum->setOptionValue(json_encode($files));
          }
        }
      }
      $this->flush();
    } catch (\Throwable $t) {
      $this->logException($t, 'Unable to update field-data for ' . print_r($flatCriteria, true) . '.');
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

  public function logger()
  {
    return $this->logger;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

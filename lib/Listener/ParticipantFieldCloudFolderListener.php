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
use OCP\Files;
use OCP\Files\Folder as CloudFolder;
use OCP\Files\File as CloudFile;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\BeforeNodeCopiedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeTouchedEvent;
use OCP\Files\Events\Node\BeforeNodeCreatedEvent;
use OCP\IUser;
use OCP\ILogger;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Files\IRootFolder;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
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

  const EVENT = [
    NodeRenamedEvent::class,
    NodeCopiedEvent::class,
    NodeDeletedEvent::class,
    NodeTouchedEvent::class,
    NodeCreatedEvent::class,
    BeforeNodeRenamedEvent::class,
    BeforeNodeCopiedEvent::class,
    BeforeNodeDeletedEvent::class,
    BeforeNodeTouchedEvent::class,
    BeforeNodeCreatedEvent::class,
  ];

  private const ADD_KEY = 'add';
  private const DEL_KEY = 'remove';

  private const PROJECT_YEAR_PART = 0;
  private const PROJECT_NAME_PART = 1;
  private const USER_ID_PART = 3;
  private const FIELD_NAME_PART = 4;
  private const BASE_NAME_PART = 5;

  /** @var IUser */
  private $user;

  /** @var string */
  private $appName;

  /** @var IAppContainer */
  private $appContainer;

  /**
   * @var array<int, Entities\ProjectParticipantField>
   * Per request cache for the Before... and ... events
   */
  private $fields = [];

  /**
   * @var array<int, Entities\ProjectParticipantFieldDatum>
   * Per request cache for the Before... and ... events
   */
  private $fieldData = [];

  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }

  public function handle(Event $event): void {
    $nodes = [];
    $eventClass = get_class($event);
    $checkOnly = false;
    switch ($eventClass) {
      case BeforeNodeDeletedEvent::class:
        $checkOnly = true;
      case NodeDeletedEvent::class:
        /** @var NodeDeletedEvent $event */
        $nodes[self::DEL_KEY] = $event->getNode();
        break;
      case BeforeNodeRenamedEvent::class:
        $checkOnly = true;
      case NodeRenamedEvent::class:
        /** @var NodeRenamedEvent $event */
        $nodes[self::DEL_KEY] = $event->getSource();
        $nodes[self::ADD_KEY] = $event->getTarget();
        $remove = true;
        // rename gets another NodeWrittenEvent
        break;
      case BeforeNodeCopiedEvent::class:
        $checkOnly = true;
      case NodeCopiedEvent::class:
        /** @var NodeCopiedEvent $event */
        $nodes[self::ADD_KEY] = $event->getTarget();
        break;
      case BeforeNodeTouchedEvent::class:
        $checkOnly = true;
      case NodeTouchedEvent::class:
        /** @var NodeTouchedEvent $event */
        $nodes[self::ADD_KEY] = $event->getNode();
        break;
      case BeforeNodeCreatedEvent::class:
        $checkOnly = true;
      case NodeCreatedEvent::class:
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
      try {
        $nodeType = $node->getType();
      } catch (\OCP\Files\NotFoundException) {
        $nodeType = ($node instanceof CloudFile) ? Files\FileInfo::TYPE_FILE : Files\FileInfo::TYPE_FOLDER;
      }

      $nodes[$key] = [
        'fullPath' => $nodePath,
        'path' => $postFixPath,
        'type' => $nodeType,
      ];
    }
    if (empty($nodes)) {
      return;
    }

    $participantsFolder = Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER) . Constants::PATH_SEP;
    foreach ($nodes as $key => $nodeInfo) {
      $nodePath = $nodeInfo['path'];
      if (strpos($nodePath, $participantsFolder) === false) {
        unset($nodes[$key]);
      }
    }
    if (empty($nodes)) {
      return;
    }

    // ok now something remains, get project-name and musician user-id
    $criteria = [];
    foreach ($nodes as $key => &$nodeInfo) {
      $nodePath = $nodeInfo['path'];
      $parts = explode(Constants::PATH_SEP, trim($nodePath, Constants::PATH_SEP));
      $baseName = $part[self::BASE_NAME_PART] ?? null;
      $nodeInfo['baseName'] = $baseName;

      $criteria[$key] = [
        'field.name' => $parts[self::FIELD_NAME_PART] ?? null,
        'project.year' => $parts[self::PROJECT_YEAR_PART],
        'project.name' => $parts[self::PROJECT_NAME_PART],
        'musician.userIdSlug' => $parts[self::USER_ID_PART],
      ];
      $flatCriteria[$key] = implode(':', $criteria[$key]);
    }
    unset($nodeInfo); // break reference

    $this->entityManager = $this->appContainer->get(EntityManager::class);
    $fieldsRepository = $this->getDatabaseRepository(Entities\ProjectParticipantField::class);
    $fieldDataRepository = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class);
    try {
      $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      $fields = [];
      $fieldData = [];
      foreach (array_keys($nodes) as $key) {
        $flatCriterion = $flatCriteria[$key];
        if (!isset($fieldData[$flatCriterion])) {
          $criterion = $criteria[$key];
          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          if (empty($this->fieldData[$flatCriterion])) {
            $fieldDatum = $this->fieldData[$flatCriterion] = $fieldDataRepository->findOneBy($criterion);
          }
          if (empty($this->fields[$flatCriterion])) {
            if (empty($fieldDatum)) {
              $field = $this->fields[$flatCriterion] = $fieldsRepository->findOneBy([
                'name' => $criterion['field.name'],
                'project.year' => $criterion['project.year'],
                'project.name' => $criterion['project.name'],
              ]);
            } else {
              $field = $this->fields[$flatCriterion] = $fieldDatum->getField();
            }
            if (!empty($field)) {
              /** @var Entities\ProjectParticipantField $field */
              if ($field->getDataType() != FieldType::CLOUD_FOLDER) {
                $this->fields[$flatCriterion] = null;
              }
            }
          }
        }
      }

      $key = self::ADD_KEY;
      if (isset($nodes[$key])) {
        $node = $nodes[$key];
        $baseName = $node['baseName'];
        $flatCriterion = $flatCriteria[$key];
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->fields[$flatCriterion];
        if (!empty($field)) { // can only do something if the field exists

          if (empty($baseName) && $node['type'] == Files\FileInfo::TYPE_FILE) {
            // disallow creating files with conflicting name
            $message = $this->l->t('"%1$s" belongs to the participant-field "%2$s" and must be a folder, your are trying to create a file (%3$s).', [
              $node['path'],
              $field->getName(),
              $eventClass,
            ]);
            $hint = $message; // perhaps we want to change this ...
            // only \OCP\HintException and \OC\ServerNotAvailableException can cancel the operation.
            throw new \OCP\HintException($message, $hint);
          }

          if (!$checkOnly && !empty($baseName)) { // work only on the folder-contents, not the folder itself
            /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
            $fieldDatum = $this->fieldData[$flatCriterion];
            if (empty($fieldDatum)) {
              $criterion = $criteria[$key];
              $musician = $this->getDatabaseRepository(Entities\Musician::class)->findOneBy([
                'userIdSlug' => $criterion['musician.userIdSlug']
              ]);
              $project = $field->getProject();
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
        }
      }

      $key = self::DEL_KEY;
      if (isset($nodes[$key])) {
        $node = $nodes[$key];
        $baseName = $node['baseName'];
        $flatCriterion = $flatCriteria[$key];
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->fields[$flatCriterion];

        if (!empty($field)) { // can only do something if the field exists

          if (empty($baseName) && $node['type'] == Files\FileInfo::TYPE_FOLDER) {
            // the folder itself must not be deleted except if the field is
            // deleted. But then the folder is deleted from the pre-commit
            // hook and thus we are inside the current commit which already
            // has deleted the field. So: if the field still exists at this
            // point the folder must not be deleted (safe it is a file)
            $message = $this->l->t('The folder "%1$s" belongs to the participant-field "%2$s" and must not be deleted (%3$s).', [
              $node['path'],
              $field->getName(),
              $eventClass,
            ]);
            $hint = $message;
            // only \OCP\HintException and \OC\ServerNotAvailableException can cancel the operation.
            throw new \OCP\HintException($message, $hint);
          }

          if (!$checkOnly && !empty($baseName)) { // work only on the folder-contents, not the folder itself
            /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
            $fieldDatum = $this->fieldData[$flatCriterion] ?? null;
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
        }
      }
      $this->flush();
      $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
    } catch (\OCP\HintException $e) {
      $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
      throw $e;
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

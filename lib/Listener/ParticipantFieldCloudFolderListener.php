<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2021-2023 Claus-Justus Heine
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

use OCP\HintException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files;
use OCP\Files\Node as FileSystemNode;
use OCP\Files\Folder as CloudFolder;
use OCP\Files\File as CloudFile;
use OCP\Files\FileInfo;
use OCP\Files\Events\Node\NodeCopiedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeTouchedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\FolderCreatedEvent;
use OCP\Files\Events\Node\BeforeNodeCopiedEvent;
use OCP\Files\Events\Node\BeforeNodeRenamedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\BeforeNodeTouchedEvent;
use OCP\Files\Events\Node\BeforeNodeCreatedEvent;
use OCP\Files\Events\Node\BeforeFolderCreatedEvent;
use OCP\IUser;
use Psr\Log\LoggerInterface as ILogger;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Files\IRootFolder;
use OCP\AppFramework\IAppContainer;

use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumProjectTemporalType as ProjectType;
use OCA\CAFEVDB\Database\EntityManager;

use OCA\CAFEVDB\Service\AuthorizationService;
use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\MusicianService;
use OCA\CAFEVDB\Service\ProjectParticipantFieldsService;

use OCA\CAFEVDB\Common\UndoableFileReplace;
use OCA\CAFEVDB\Common\UndoableFileRemove;
use OCA\CAFEVDB\Common\UndoableFileRename;
use OCA\CAFEVDB\Common;
use OCA\CAFEVDB\Constants;

/**
 * Listen to changes to the configured participant-field cloud-folder
 * directories and update the DB contents accordingly.
 *
 * Currently performed tasks:
 * - disallow removal of sub-directories tied to CLOUD_FOLDER fields
 * - disallow removal of sub-directories tied to CLOUD_FILE fields if a
 *   field-value (file) is set for the respective musician
 * - update of fiels-values when a file with the correct name is added or removed
 * - disallow tweaking the README.md which is taken from the field's tooltip
 *
 * @todo To make this work BeforeFolderCreatedEvents are needed, and the
 * trashbin restore must emit a BeforeNodeRenamedEvent.
 */
class ParticipantFieldCloudFolderListener implements IEventListener
{
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;
  use \OCA\CAFEVDB\Toolkit\Traits\UserRootFolderTrait;

  const EVENT = [
    NodeRenamedEvent::class,
    NodeCopiedEvent::class,
    NodeDeletedEvent::class,
    NodeCreatedEvent::class,
    FolderCreatedEvent::class,
    BeforeNodeRenamedEvent::class,
    BeforeNodeCopiedEvent::class,
    BeforeNodeDeletedEvent::class,
    BeforeNodeCreatedEvent::class,
    BeforeFolderCreatedEvent::class,
  ];

  private const ADD_KEY = 'add';
  private const DEL_KEY = 'remove';

  private const NODE_FULL_PATH = 'fullPath';
  private const NODE_PARTIAL_PATH = 'path';
  private const NODE_TYPE = 'type';
  private const NODE_BASE_NAME = 'baseName';

  private const PROJECT_YEAR_PART = 0;
  private const PROJECT_NAME_PART = 1;
  private const USER_ID_PART = 3;
  private const FIELD_NAME_PART = 4;
  private const BASE_NAME_PART = 5;

  /** @var IUser */
  private $user;

  /** @var string */
  protected $appName;

  /** @var IAppContainer */
  private $appContainer;

  /** @var Repositories\ProjectParticipantFieldsRepository */
  private $fieldsRepository;

  /** @var Repositories\ProjectParticipantFieldDataRepository */
  private $fieldDataRepository;

  /** @var Repositories\MusiciansRepository */
  private $musiciansRepository;

  /**
   * @var array<string, Entities\ProjectParticipantField>
   * Per request cache for the Before... and ... events
   */
  private $fields = [];

  /**
   * @var array<string, Entities\ProjectParticipantFieldDatum>
   * Per request cache for the Before... and ... events
   */
  private $fieldData = [];

  /**
   * @var array<int, array<string, Entities\ProjectParticipantFieldDataOption> >
   * Per request cache for field-options by file-name.
   */
  private $fieldOptionsByFileName = [];

  /**
   * @var array<string, Entities\Musician>
   * Cache of musicians by userid
   */
  private $musicians = [];

  /** @var IRootFolder */
  protected $rootFolder;

  /**
   * @var string
   *
   * To distinguish mkdir from file creation we listen on FolderCreated
   * events, remember the path and ignore the subsequent Created event.
   */
  private $ignoreCreatedPaths = [];

  // phpcs:disable Squiz.Commenting.FunctionComment.Missing
  public function __construct(IAppContainer $appContainer)
  {
    $this->appContainer = $appContainer;
  }
  // phpcs:enable

  /** {@inheritdoc} */
  public function handle(Event $event):void
  {
    $nodes = [];
    $eventClass = get_class($event);
    $checkOnly = false;
    switch ($eventClass) {
      case BeforeNodeDeletedEvent::class:
        $checkOnly = true;
        // fallthrough
      case NodeDeletedEvent::class:
        /** @var NodeDeletedEvent $event */
        /** @var FileSystemNode $node */
        $node = $event->getNode();
        $nodes[self::DEL_KEY] = [
          self::NODE_FULL_PATH => $node->getPath(),
          self::NODE_TYPE => $node->getType(),
        ];
        break;
      case BeforeNodeRenamedEvent::class:
        $checkOnly = true;
        // fallthrough
      case NodeRenamedEvent::class:
        /** @var NodeRenamedEvent $event */
        /** @var FileSystemNode $source */
        $source = $event->getSource();
        $nodes[self::DEL_KEY] = [
          self::NODE_FULL_PATH => $source->getPath(),
          self::NODE_TYPE => $source->getType(),
        ];
        /** @var FileSystemNode $target */
        $target = $event->getTarget();
        $nodes[self::ADD_KEY] = [
          self::NODE_FULL_PATH => $target->getPath(),
          self::NODE_TYPE => $source->getType(), // "source" to avoid NonExistingFile isssues
        ];
        $this->ignoreCreatedPaths[$target->getPath()] = true; // ignore the following Created Event
        break;
      case BeforeNodeCopiedEvent::class:
        $checkOnly = true;
        // fallthrough
      case NodeCopiedEvent::class:
        /** @var NodeCopiedEvent $event */
        /** @var FileSystemNode $source */
        $source = $event->getSource();
        /** @var FileSystemNode $target */
        $target = $event->getTarget();
        $nodes[self::ADD_KEY] = [
          self::NODE_FULL_PATH => $target->getPath(),
          self::NODE_TYPE => $source->getType(), // "source" to avoid NonExistingFile isssues
        ];
        $this->ignoreCreatedPaths[$target->getPath()] = true; // ignore the following Created Event
        break;
      case BeforeFolderCreatedEvent::class:
        $checkOnly = true;
        // fallthrough
      case FolderCreatedEvent::class:
        /** @var NodeTouchedEvent $event */
        $path = $event->getNode()->getPath();
        $nodes[self::ADD_KEY] = [
          self::NODE_FULL_PATH => $path,
          self::NODE_TYPE => FileInfo::TYPE_FOLDER,
        ];
        $this->ignoreCreatedPaths[$path] = true; // ignore the following Created Event
        break;
      case BeforeNodeCreatedEvent::class:
        $checkOnly = true;
        // fallthrough
      case NodeCreatedEvent::class:
        /** @var NodeCreatedEvent $event */
        $path = $event->getNode()->getPath();
        $ignoredPath = $this->ignoreCreatedPaths[$path] ?? false;
        unset($this->ignoreCreatedPaths[$path]);
        if ($ignoredPath) {
          return;
        }
        $nodes[self::ADD_KEY] = [
          self::NODE_FULL_PATH => $path,
          self::NODE_TYPE => FileInfo::TYPE_FILE,
        ];
        break;
      default:
        return;
    }

    if (!$this->initialize()) {
      return; // something went wrong
    }

    $userFolder = Constants::PATH_SEPARATOR . $this->getUserFolderPath($this->user->getUID());

    // Bail out if the folder being worked on is actually the
    // top-level user-folder
    foreach ($nodes as $key => $nodeInfo) {
      if ($nodeInfo[self::NODE_FULL_PATH] == $userFolder) {
        return;
      }
    }

    $projectsPath = $this->getProjectsFolderPath();

    // strip the user-folder and match with the projects path
    foreach ($nodes as $key => &$nodeInfo) {
      $nodePath = self::matchPrefixDirectory($nodeInfo[self::NODE_FULL_PATH], $userFolder);
      $postFixPath = self::matchPrefixDirectory($nodePath, $projectsPath);
      if ($postFixPath === null) {
        unset($nodes[$key]);
      }
      $nodeInfo[self::NODE_FULL_PATH] = $nodePath;
      $nodeInfo[self::NODE_PARTIAL_PATH] = $postFixPath;
    }
    if (empty($nodes)) {
      return;
    }
    unset($nodeInfo); // break reference

    $participantsFolder = Constants::PATH_SEP . $this->getConfigValue(ConfigService::PROJECT_PARTICIPANTS_FOLDER) . Constants::PATH_SEP;
    foreach ($nodes as $key => $nodeInfo) {
      $nodePath = $nodeInfo[self::NODE_PARTIAL_PATH];
      if (strpos($nodePath, $participantsFolder) === false) {
        unset($nodes[$key]);
      }
    }
    if (empty($nodes)) {
      return;
    }

    // ok now something remains, get project-name and musician user-id
    $criteria = [];
    $flatCriteria = [];
    foreach ($nodes as $key => &$nodeInfo) {
      $nodePath = trim($nodeInfo[self::NODE_PARTIAL_PATH], Constants::PATH_SEP);
      $parts = explode(Constants::PATH_SEP, $nodePath);
      $projectYear = (int)$parts[self::PROJECT_YEAR_PART];
      if ($projectYear < 1000 || $projectYear > 9999) {
        // not a valid year, assume non-temporary project
        array_unshift($parts, ProjectType::PERMANENT);
        $projectYear = null;
        $projectType = ProjectType::PERMANENT;
      } else {
        $projectType = [ ProjectType::TEMPORARY, ProjectType::TEMPLATE ];
      }
      $baseName = $parts[self::BASE_NAME_PART] ?? null;
      $userIdSlug = $parts[self::USER_ID_PART];
      $fieldName = $parts[self::FIELD_NAME_PART] ?? null;
      if (empty($baseName) && !empty($fieldName) && MusicianService::isSlugifiedFileName($fieldName, $userIdSlug)) {
        $baseName = $fieldName;
        $fieldName = MusicianService::unSlugifyFileName($fieldName, $userIdSlug, keepExtension: false);
      }

      $nodeInfo[self::NODE_BASE_NAME] = $baseName;

      $criteria[$key] = [
        'field.name' => $fieldName,
        'project.year' => $projectYear,
        'project.type' => $projectType,
        'project.name' => $parts[self::PROJECT_NAME_PART],
        'musician.userIdSlug' => $userIdSlug,
      ];
      $flatCriteria[$key] = $this->flattenCriterion($criteria[$key]);
    }
    unset($nodeInfo); // break reference

    $this->initializeDatabaseAccess();
    if ($this->entityManager->isOwnTransactionActive()) {
      return; // perhaps move this more to the top ...
    }

    $throw = null;
    $rollBack = false;
    if (!$checkOnly) {
      $this->entityManager->beginTransaction();
    }
    try {
      $softDeleteableState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

      $key = self::ADD_KEY;
      if (isset($nodes[$key])) {
        $node = $nodes[$key];
        $baseName = $node[self::NODE_BASE_NAME];
        $criterion = $criteria[$key];
        $userIdSlug = $criterion['musician.userIdSlug'];
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->getField($criterion);

        if (!empty($field)) { // can only do something if the field exists
          $fieldType = $field->getDataType();
          $fieldMultiplicity = $field->getMultiplicity();
          if ($checkOnly) {
            if (empty($baseName)) {
              if ($node[self::NODE_TYPE] == Files\FileInfo::TYPE_FILE) {
                // disallow creating files with a conflicting name
                $message = $this->l->t('"%1$s" belongs to the participant-field "%2$s" and must be a folder, your are trying to create a file (%3$s).', [
                  $node[self::NODE_FULL_PATH],
                  $field->getName(),
                  $eventClass,
                ]);
                $hint = $message; // perhaps we want to change this ...
                // only \OCP\HintException and \OC\ServerNotAvailableException can cancel the operation.
                throw new HintException($message, $hint);
              }
            } elseif ($baseName !== Constants::README_NAME) { // baseName given
              if ($fieldType == FieldType::CLOUD_FILE && $fieldMultiplicity != FieldMultiplicity::SIMPLE) {
                // check if the name matches one of the registered options
                if (null == $this->getOptionFromFileName($baseName, $userIdSlug, $field)) {
                  $message = $this->l->t('The current folder "%1$s" belongs to the participant-field "%2$s", but "%3$s" is not one of the registered allowed file-names.', [
                    dirname($node[self::NODE_FULL_PATH]),
                    $field->getName(),
                    $node[self::NODE_FULL_PATH],
                  ]);
                  $hint = $message;
                  throw new HintException($message, $hint);
                }
              }
            }
          }

          if (!$checkOnly && empty($baseName)) {
            // created the README if the field has a tooltip
            $readMe = Common\Util::htmlToMarkDown($field->getTooltip());
            $path = $node[self::NODE_FULL_PATH] . Constants::PATH_SEP . Constants::README_NAME;
            $readMeGenerator = new Common\UndoableTextFileUpdate($path, $readMe, gracefully: true, mkdir: false);
            $readMeGenerator->initialize($this->appContainer);
            $readMeGenerator->do();
          }

          if (!$checkOnly && !empty($baseName) && $baseName !== Constants::README_NAME) { // work only on the folder-contents, not the folder itself
            if ($fieldType == FieldType::CLOUD_FOLDER) {
              /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
              $fieldDatum = $this->getFieldDatum($criterion);
              if (empty($fieldDatum)) {
                $musician = $this->getMusician($criterion);
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
              $fieldDatum->setDeleted(null);
              $this->persist($fieldDatum);
            } else { // CLOUD_FILE
              $musician = $this->getMusician($criterion);
              $folderName = dirname($node[self::NODE_FULL_PATH]);
              if ($fieldMultiplicity == FieldMultiplicity::SIMPLE) {
                $fieldData = $field->getMusicianFieldData($musician);
                $fieldOption = $field->getDataOption(); // only one

                $finalFileName = MusicianService::slugifyFileName($field->getName(), $userIdSlug);
                $finalBaseName = $finalFileName . '.' . pathinfo($baseName, PATHINFO_EXTENSION);
              } else {
                $fieldOption = $this->getOptionFromFileName($baseName, $userIdSlug, $field);
                $fieldData = $fieldOption->getMusicianFieldData($musician);

                $finalFileName = MusicianService::slugifyFileName($fieldOption->getLabel(), $userIdSlug);
                $finalBaseName = $finalFileName . '.' . pathinfo($baseName, PATHINFO_EXTENSION);
              }

              /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
              foreach ($fieldData as $fieldDatum) {
                // handle the case where just the extension changes
                $oldBaseName = $fieldDatum->getOptionValue();
                if ($oldBaseName !== $baseName) {
                  $this->entityManager->registerPreCommitAction(
                    new UndoableFileRemove($folderName . Constants::PATH_SEP . $oldBaseName, gracefully: true)
                  );
                }
              }
              if ($fieldData->count() !== 1) {
                foreach ($fieldData as $fieldDatum) {
                  $this->remove($fieldDatum, hard: true);
                }
                $project = $field->getProject();
                $fieldDatum = new Entities\ProjectParticipantFieldDatum;
                $fieldDatum->setField($field)
                  ->setDataOption($fieldOption)
                  ->setMusician($musician)
                  ->setProject($project);
                $field->getFieldData()->add($fieldDatum);
                $fieldOption->getFieldData()->add($fieldDatum);
                $project->getParticipantFieldsData()->add($fieldDatum);
                $musician->getProjectParticipantFieldsData()->add($fieldDatum);
              } else {
                $fieldDatum = $fieldData->first();
              }
              $fieldDatum->setOptionValue($finalBaseName);
              $fieldDatum->setDeleted(null);
              $this->persist($fieldDatum);
              if ($finalBaseName != $baseName) { // THIS CANNOT HAPPEN?
                $this->logInfo('WHY SHOULD EVER BE "' . $finalBaseName . '" BE DIFFERENT FROM "' . $baseName . '"?');
                $this->entityManager->registerPreCommitAction(
                  new UndoableFileRename(
                    $folderName . Constants::PATH_SEP . $baseName,
                    $folderName . Constants::PATH_SEP . $finalBaseName,
                    gracefully: true
                  )
                );
              }
            }
          }
        }
      }

      $key = self::DEL_KEY;
      if (isset($nodes[$key])) {
        $node = $nodes[$key];
        $baseName = $node[self::NODE_BASE_NAME];
        $criterion = $criteria[$key];
        /** @var Entities\ProjectParticipantField $field */
        $field = $this->getField($criterion);
        if (!empty($field)) { // can only do something if the field exists
          $fieldType = $field->getDataType();
          $fieldMultiplicity = $field->getMultiplicity();
          if ($checkOnly) {
            if (empty($baseName) && $node[self::NODE_TYPE] == Files\FileInfo::TYPE_FOLDER) {
              // the folder itself must not be deleted except if the field is
              // deleted. But then the folder is deleted from the pre-commit
              // hook and thus we are inside the current commit which already
              // has deleted the field. So: if the field still exists at this
              // point the folder must not be deleted (safe it is a file)
              //
              // However, if the folder belongs to a multi-value CLOUD_FILE
              // field then it may be deleted if there is no related field-data.
              $preventDeletion = true;
              if ($fieldType == FieldType::CLOUD_FILE) {
                $musician = $this->getMusician($criterion);
                $fieldData = $field->getMusicianFieldData($musician);
                if ($fieldData->count() == 0) {
                  $preventDeletion = false;
                }
              }
              if ($preventDeletion) {
                $message = $this->l->t('The folder "%1$s" belongs to the participant-field "%2$s" and must not be deleted (%3$s).', [
                  $node[self::NODE_PARTIAL_PATH],
                  $field->getName(),
                  $eventClass,
                ]);
                $hint = $message;
                // only \OCP\HintException and \OC\ServerNotAvailableException can cancel the operation.
                throw new HintException($message, $hint);
              }
            }
          }

          if (!$checkOnly && !empty($baseName) && $baseName !== Constants::README_NAME) {
            // work only on the relevant folder-contents, not the folder itself
            if ($fieldType == FieldType::CLOUD_FOLDER) {
              /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
              $fieldDatum = $this->getFieldDatum($criterion);
              if (!empty($fieldDatum)) {
                $files = json_decode($fieldDatum->getOptionValue(), true);
                Common\Util::unsetValue($files, $baseName);
                if (empty($files)) {
                  $this->remove($fieldDatum, hard: true);
                } else {
                  $fieldDatum->setOptionValue(json_encode($files));
                  $fieldDatum->setDeleted(null);
                }
              }
            } else { // CLOUD_FILE
              $musician = $this->getMusician($criterion);
              if ($fieldMultiplicity == FieldMultiplicity::SIMPLE) {
                $fieldData = $field->getMusicianFieldData($musician);
                $fieldOption = $field->getDataOption();
              } else {
                $fieldOption = $this->getOptionFromFileName($baseName, $userIdSlug, $field);
                $fieldData = $fieldOption->getMusicianFieldData($musician);
              }
              // just delete all field-data pointing to this option ...
              foreach ($fieldData as $fieldDatum) {
                $this->remove($fieldDatum, hard: true);
                $fieldOption->getFieldData()->removeElement($fieldDatum);
                $field->getFieldData()->removeElement($fieldDatum);
              }
            }
          }
        }
      }
      if (!$checkOnly) {
        $this->flush();
        $this->entityManager->commit();
      }
    } catch (\OCP\HintException $e) {
      $throw = $e;
      $rollBack = !$checkOnly;
    } catch (\Throwable $t) {
      $this->logException($t, 'Unable to update field-data for ' . print_r($flatCriteria, true) . '.');
      $rollBack = !$checkOnly;
    } finally {
      $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER, $softDeleteableState);
      if ($rollBack) {
        $this->entityManager->rollback();
      }
      !empty($throw) && throw $throw;
    }
  }

  /**
   * @param null|string $path The path to match.
   *
   * @param string $folderPrefix The folder-prefix to compare the
   * first part of the string to.
   *
   * @return null|string The sub-string after remove the $folderPrefix
   * or null if $folderPrefix is not the first part of the string.
   */
  private static function matchPrefixDirectory(?string $path, string $folderPrefix)
  {
    $prefixLen = strlen($folderPrefix);
    if (substr($path, 0, $prefixLen) == $folderPrefix) {
      return substr($path, $prefixLen);
    }
    return null;
  }

  /**
   * @param array $criterion
   *
   * @return null|Entities\Musician
   */
  private function getMusician(array $criterion):?Entities\Musician
  {
    $userIdSlug = $criterion['musician.userIdSlug'];
    if (empty($this->musicians[$userIdSlug])) {
      $this->musicians[$userIdSlug] = $this->musiciansRepository->findOneBy([
        'userIdSlug' => $userIdSlug,
      ]);
    }
    return $this->musicians[$userIdSlug];
  }

  /**
   * @param array $criterion
   *
   * @return string
   */
  private static function flattenCriterion(array $criterion):string
  {
    return json_encode($criterion);
  }

  /**
   * @param array $criterion
   *
   * @return null|Entities\ProjectParticipantFieldDatum
   */
  private function getFieldDatum(array $criterion):?Entities\ProjectParticipantFieldDatum
  {
    $flatCriterion = self::flattenCriterion($criterion);
    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    if (empty($this->fieldData[$flatCriterion])) {
      $criterion = array_filter($criterion, fn($x) => $x !== null);
      $this->fieldData[$flatCriterion] = $this->fieldDataRepository->findOneBy($criterion);
    }
    return $this->fieldData[$flatCriterion];
  }

  /**
   * @param Entities\ProjectParticipantField $field
   *
   * @return bool
   */
  private static function isCloudFileField(Entities\ProjectParticipantField $field):bool
  {
    return $field->getDataType() == FieldType::CLOUD_FOLDER || $field->getDataType() == FieldType::CLOUD_FILE;
  }

  /**
   * @param array $criterion
   *
   * @return null|Entities\ProjectParticipantField
   */
  private function getField(array $criterion):?Entities\ProjectParticipantField
  {
    $fieldCriterion = [
      'name' => $criterion['field.name'],
      'project.type' => $criterion['project.type'],
      'project.name' => $criterion['project.name'],
    ];
    if ($criterion['project.year'] !== null) {
      $fieldCriterion['project.year'] = $criterion['project.year'];
    }
    $flatFieldCriterion = self::flattenCriterion($fieldCriterion);
    if (!isset($this->fields[$flatFieldCriterion])) { // isset() as the actual value may be null
      $fieldDatum = $this->getFieldDatum($criterion);
      /** @var Entities\ProjectParticipantField $field */
      if (empty($fieldDatum)) {
        $field = $this->fields[$flatFieldCriterion] = $this->fieldsRepository->findOneBy($fieldCriterion);
      } else {
        $field = $this->fields[$flatFieldCriterion] = $fieldDatum->getField();
      }
      if (!empty($field)) {
        if (!self::isCloudFileField($field)) {
          $this->fields[$flatFieldCriterion] = null;
        }
      }
    }
    return $this->fields[$flatFieldCriterion];
  }

  /**
   * Do some lazy initialization in order to have a leight-weight constructor.
   *
   * Bail out if the user does not belong to the orchestra group.
   *
   * @return bool Success status. The parent should just terminate execution
   * if \false is returned.
   */
  private function initialize():bool
  {
    // initialize only now in order to keep the overhead for unhandled events small
    $this->user = $this->appContainer->get(IUserSession::class)->getUser();
    if (empty($this->user)) {
      return false;
    }

    /** @var AuthorizationService $authorizationService */
    $authorizationService = $this->appContainer->get(AuthorizationService::class);
    if (!$authorizationService->authorized($this->user->getUID())) {
      return false;
    }

    $this->appName = $this->appContainer->get('appName');
    $this->logger = $this->appContainer->get(ILogger::class);
    $this->l = $this->appContainer->get(IL10N::class);

    $this->configService = $this->appContainer->get(ConfigService::class);
    if (empty($this->configService)) {
      return false;
    }

    /** @var IRootFolder $rootFolder */
    $this->rootFolder = $this->appContainer->get(IRootFolder::class);
    if (empty($this->rootFolder)) {
      return false;
    }
    return true;
  }

  /**
   * @see ParticipantFieldCloudFolderListener::initialize()
   *
   * @return void
   */
  private function initializeDatabaseAccess():void
  {
    if (!empty($this->entityManager)) {
      return;
    }
    $this->entityManager = $this->appContainer->get(EntityManager::class);
    $this->fieldsRepository = $this->getDatabaseRepository(Entities\ProjectParticipantField::class);
    $this->fieldDataRepository = $this->getDatabaseRepository(Entities\ProjectParticipantFieldDatum::class);
    $this->musiciansRepository = $this->getDatabaseRepository(Entities\Musician::class);
  }

  /**
   * Reconstruct the field from the label, assuming the file-name has the
   * format LABEL-JohnDoe.EXT.
   *
   * @param string $path
   *
   * @param string $userIdSlug
   *
   * @param Entities\ProjectParticipantField $field
   *
   * @return null|Entities\ProjectParticipantFieldDataOption
   */
  private function getOptionFromFileName(
    string $path,
    string $userIdSlug,
    Entities\ProjectParticipantField $field,
  ):?Entities\ProjectParticipantFieldDataOption {
    $pathInfo = pathinfo($path);
    $fileName = $pathInfo['filename'];
    if (empty($this->fieldOptionsByFileName[$field->getId()][$fileName])) {
      $musicianPostfix = MusicianService::slugifyFileName('', $userIdSlug);
      $labelPrefix = substr($fileName, 0, strpos($fileName, $musicianPostfix));
      $option = $field->getOptionByLabel($labelPrefix);
      $this->fieldOptionsByFileName[$field->getId()][$fileName] = $option;
    }
    return $this->fieldOptionsByFileName[$field->getId()][$fileName];
  }
}

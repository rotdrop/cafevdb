<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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

namespace OCA\CAFEVDB\Storage\Database;

use DateTimeImmutable;
use DateTimeInterface;

use OCP\EventDispatcher\IEventDispatcher;

// F I X M E: those are not public, but ...
use OC\Files\Storage\Common as AbstractStorage;
use OC\Files\Storage\PolyFill\CopyDirectory;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Service\OrganizationalRolesService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Database\Doctrine\Util as DBUtil;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Exceptions;
use OCA\CAFEVDB\Events;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class ProjectParticipantsStorage extends Storage
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait;
  use ProjectParticipantsStorageTrait;

  /** @var Entities\Musician */
  private $musician;

  /** @var Entities\Project */
  private $project;

  /** @var Entities\ProjectParticipant */
  private $participant;

  /** @var ProjectService */
  private $projectService;

  /** @var Entities\DatabaseStorageFolder */
  private $rootFolder;

  /** @var array */
  private $files = [];

  /** @var bool Whether the current user belongs to the treasurer group. */
  private $isTreasurer;

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);
    $this->participant = $params['participant'];
    $this->project = $this->participant->getProject();
    $this->musician = $this->participant->getMusician();
    $this->projectService = $this->di(ProjectService::class);

    $shortId = substr($this->getId(), strlen(parent::getId()));
    $rootStorage = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findOneBy([ 'storageId' => $shortId ]);
    if (!empty($rootStorage)) {
      $this->rootFolder = $rootStorage->getRoot();
    }

    $organizationalRolesService = $this->di(OrganizationalRolesService::class);
    $userId = $this->entityManager->getUserId();
    $this->isTreasurer = $organizationalRolesService->isTreasurer($userId, allowGroupAccess: true);
    /** @var IEventDispatcher $eventDispatcher */
    $eventDispatcher = $this->di(IEventDispatcher::class);
    $eventDispatcher->addListener(Events\EntityManagerBoundEvent::class, function(Events\EntityManagerBoundEvent $event) {
      $this->logDebug('Entity-manager shoot down, re-fetching cached entities.');
      // the mount provider currently disables soft-deleteable filter ...
      $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      try {
        $projectId = $this->participant->getProject()->getId();
        $musicianId = $this->participant->getMusician()->getId();
        $this->clearDatabaseRepository();
        $shortId = substr($this->getId(), strlen(parent::getId()));
        $rootStorage = $this->getDatabaseRepository(Entities\DatabaseStorage::class)->findOneBy([ 'storageId' => $shortId ]);
        if (!empty($rootStorage)) {
          $this->rootFolder = $rootStorage->getRoot();
        }
        $this->participant = $this->getDatabaseRepository(Entities\ProjectParticipant::class)
          ->find([
            'project' => $projectId,
            'musician' => $musicianId,
          ]);
        $this->project = $this->participant->getProject();
        $this->musician = $this->participant->getMusician();
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function fileFromFileName(?string $name)
  {
    $name = $this->buildPath($name);
    if ($name == self::PATH_SEPARATOR) {
      $dirName = '';
      $baseName = '.';
    } else {
      list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);
    }
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);

    if (empty($this->files[$dirName])) {
      $this->findFiles($dirName);
    }

    return ($this->files[$dirName][$baseName]
            ?? ($this->files[$dirName][$baseName . self::PATH_SEPARATOR]
                ?? null));
  }

  /**
   * Helper functino in order to migrate to DatabaseStorageDirEntry.
   *
   * @param int $id
   *
   * @return null|DateTimeInterface
   */
  private function getSepaDebitMandatesChanged(int $id):?DateTimeInterface
  {
    $connection = $this->entityManager->getConnection();
    $sql = 'SELECT t.sepa_debit_mandates_changed
FROM Musicians t
WHERE t.id = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $id);
    try {
      $value = $stmt->executeQuery()->fetchOne();
    } catch (InvalidFieldNameException $t) {
      $this->logException($t, 'Column does not exist, migration probably has already been applied.');
      return self::ensureDate(null);
    }
    return self::convertToDateTime($value);
  }

  /**
   * Helper functino in order to migrate to DatabaseStorageDirEntry.
   *
   * @param int $id
   *
   * @return null|DateTimeInterface
   */
  private function getPaymentsChanged(int $id):?DateTimeInterface
  {
    $connection = $this->entityManager->getConnection();
    $sql = 'SELECT t.payments_changed
FROM Musicians t
WHERE t.id = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $id);
    try {
      $value = $stmt->executeQuery()->fetchOne();
    } catch (InvalidFieldNameException $t) {
      $this->logException($t, 'Column does not exist, migration probably has already been applied.');
      return self::ensureDate(null);
    }
    return self::convertToDateTime($value);
  }

  /**
   * Helper functino in order to migrate to DatabaseStorageDirEntry.
   *
   * @param int $projectId
   *
   * @param int $musicianId
   *
   * @return null|DateTimeInterface
   */
  private function getParticipantFieldsDataChanged(int $projectId, int $musicianId):?DateTimeInterface
  {
    $connection = $this->entityManager->getConnection();
    $sql = 'SELECT t.participant_fields_data_changed
FROM ProjectParticipants t
WHERE t.project_id = ? AND t.musician_id = ?';
    $stmt = $connection->prepare($sql);
    $stmt->bindValue(1, $projectId);
    $stmt->bindValue(2, $musicianId);
    try {
      $value = $stmt->executeQuery()->fetchOne();
    } catch (InvalidFieldNameException $t) {
      $this->logException($t, 'Column does not exist, migration probably has already been applied.');
      return self::ensureDate(null);
    }
    return self::convertToDateTime($value);
  }

  /**
   * These are now only used to migrate the stuff to the new
   * DatabaseStorageDirEntries tables.
   *
   * @return array
   */
  protected function getListingGenerators():array
  {
    // Arguably, these should be classes, but as PHP does not support multiple
    // inheritance this really would produce a lot of boiler-plate-code.
    return [
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getSupportingDocumentsFolderName(),
          $this->getReceivablesFolderName(),
        ],
        'parentModificationTime' => fn() => $this->getParticipantFieldsDataChanged($this->project->getId(), $this->musician->getId()),
        'hasLeafNodes' => fn() => !$this->participant->getParticipantFieldsData()->forAll(
          fn($key, Entities\ProjectParticipantFieldDatum $fieldDatum) => empty($fieldDatum->getSupportingDocument())
        ),
        'createLeafNodes' => function($dirName, $subDirectoryPath) {
          $modificationTime = $this->getParticipantFieldsDataChanged($this->project->getId(), $this->musician->getId());
          $activeFieldData = $this->participant->getParticipantFieldsData()->filter(
            fn(Entities\ProjectParticipantFieldDatum $fieldDatum) => !empty($fieldDatum->getSupportingDocument())
          );

          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          foreach ($activeFieldData as $fieldDatum) {
            $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
            if (empty($fileInfo)) {
              continue; // should not happen here because of ->filter().
            }
            $fileName = $this->buildPath($fileInfo['pathName']);

            list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
            if ($fileDirName == $dirName) {
              $this->files[$dirName][$baseName] = $fileInfo['file'];
            } elseif (strpos($fileDirName, $dirName) === 0) {
              list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
              $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
            }
          }
        },
      ]),
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getSupportingDocumentsFolderName(),
          $this->getReceivablesFolderName(),
        ],
        'parentModificationTime' => fn() => $this->getParticipantFieldsDataChanged($this->project->getId(), $this->musician->getId()),
        'hasLeafNodes' => fn() => !$this->participant->getParticipantFieldsData()->forAll(
          fn($key, Entities\ProjectParticipantFieldDatum $fieldDatum) => empty($fieldDatum->getSupportingDocument())
        ),
        'createLeafNodes' => function($dirName, $subDirectoryPath) {
          $modificationTime = $this->getParticipantFieldsDataChanged($this->project->getId(), $this->musician->getId());
          $activeFieldData = $this->participant->getParticipantFieldsData()->filter(
            fn(Entities\ProjectParticipantFieldDatum $fieldDatum) => !empty($fieldDatum->getSupportingDocument())
          );

          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          foreach ($activeFieldData as $fieldDatum) {
            $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
            if (empty($fileInfo)) {
              continue; // should not happen here because of ->filter().
            }
            $fileName = $this->buildPath($fileInfo['pathName']);

            list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
            if ($fileDirName == $dirName) {
              $this->files[$dirName][$baseName] = $fileInfo['file'];
            } elseif (strpos($fileDirName, $dirName) === 0) {
              list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
              $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
            }
          }
        },
      ]),
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getSupportingDocumentsFolderName(),
          $this->getBankTransactionsFolderName(),
        ],
        'parentModificationTime' => fn() => $this->getPaymentsChanged($this->musician->getId()),
        'hasLeafNodes' => fn() => $this->isTreasurer && !$this->musician->getPayments()->forAll(
          fn($key, Entities\CompositePayment $compositePayment) => (
            $compositePayment->getProjectPayments()->matching(
              DBUtil::criteriaWhere([ 'project' => $this->project ])
            )->count() == 0
            || empty($compositePayment->getSupportingDocument())
          )
        ),
        'createLeafNodes' => function($dirName, $subDirectoryPath) {
          /** @var Entities\CompositePayment $compositePayment */
          foreach ($this->musician->getPayments() as $compositePayment) {
            $projectPayments = $compositePayment->getProjectPayments()->matching(
              DBUtil::criteriaWhere([ 'project' => $this->project ])
            );
            if ($projectPayments->count() == 0) {
              continue;
            }
            $file = $compositePayment->getSupportingDocument();
            if (empty($file)) {
              continue;
            }
            // enforce the "correct" file-name
            $dbFileName = $file->getFileName();
            $baseName = $this->getPaymentRecordFileName($compositePayment) . '.' . pathinfo($dbFileName, PATHINFO_EXTENSION);
            $fileName = $this->buildPath($subDirectoryPath . self::PATH_SEPARATOR . $baseName);
            list('basename' => $baseName) = self::pathInfo($fileName);
            $this->files[$dirName][$baseName] = $file;
          }
        },
      ]),
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getDebitMandatesFolderName(),
        ],
        'parentModificationTime' => function() {
          $modificationTime = $this->getSepaDebitMandatesChanged($this->musician->getId());
          /** @var Entities\SepaDebitMandate $debitMandate */
          foreach ($this->musician->getSepaDebitMandates() as $debitMandate) {
            $writtenMandate = $debitMandate->getWrittenMandate();
            if (!empty($writtenMandate)) {
              $modificationTime = max($modificationTime, self::ensureDate($writtenMandate->getUpdated()));
            }
          }
          return $modificationTime;
        },
        'hasLeafNodes' => function() {
          if (!$this->isTreasurer) {
            return false;
          }
          $membersProjectId = $this->getClubMembersProjectId();
          $projectId = $this->project->getId();
          return !$this->musician->getSepaDebitMandates()->forAll(
            function($key, Entities\SepaDebitMandate $debitMandate) use ($membersProjectId, $projectId) {
              $mandateProjectId = $debitMandate->getProject()->getId();
              return $mandateProjectId != $membersProjectId && $mandateProjectId != $projectId && empty($debitMandate->getWrittenMandate());
            }
          );
        },
        'createLeafNodes' => function($dirName, $subDirectoryPath) {
          $projectId = $this->project->getId();
          $membersProjectId = $this->getClubMembersProjectId();
          /** @var Entities\SepaDebitMandate $debitMandate */
          $projectMandates = $this->musician->getSepaDebitMandates()->filter(
            function($debitMandate) use ($membersProjectId, $projectId) {
              $mandateProjectId = $debitMandate->getProject()->getId();
              return $mandateProjectId === $membersProjectId || $mandateProjectId === $projectId;
            });
          foreach ($projectMandates as $debitMandate) {
            $file = $debitMandate->getWrittenMandate();
            if (empty($file)) {
              continue;
            }
            // enforce the "correct" file-name
            $extension = '.' . pathinfo($file->getFileName(), PATHINFO_EXTENSION);
            $baseName = $this->getDebitMandateFileName($debitMandate) . $extension;
            $fileName = $this->buildPath($subDirectoryPath . self::PATH_SEPARATOR . $baseName);
            list('basename' => $baseName) = self::pathInfo($fileName);
            $this->files[$dirName][$baseName] = $file;
          }
        },
      ]),
      new ParticipantsStorageGenerator([
        'skipDepthIfOther' => 1,
        'pathChain' => [],
        'parentModificationTime' => fn() => $this->getParticipantFieldsDataChanged($this->project->getId(), $this->musician->getId()),
        'hasLeafNodes' => fn() => $this->participant->getParticipantFieldsData()->filter(
          function(Entities\ProjectParticipantFieldDatum $fieldDatum) {
            if ($fieldDatum->getField()->getDataType() == FieldType::SERVICE_FEE) {
              return false;
            }
            if (empty($this->projectService->participantFileInfo($fieldDatum, includeDeleted: true))) {
              return false;
            }
            return true;
          })->count() > 0,
        'createLeafNodes' => function($dirName, $subDirectoryPath) {

          $modificationTime = $this->getParticipantFieldsDataChanged($this->project->getId(), $this->musician->getId());

          /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
          foreach ($this->participant->getParticipantFieldsData() as $fieldDatum) {

            if ($fieldDatum->getField()->getDataType() == FieldType::SERVICE_FEE) {
              continue;
            }

            $fileInfo = $this->projectService->participantFileInfo($fieldDatum, includeDeleted: true);
            if (empty($fileInfo)) {
              continue;
            }

            $fileName = $this->buildPath($fileInfo['pathName']);

            list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
            if ($fileDirName == $dirName) {
              $this->files[$dirName][$baseName] = $fileInfo['file'];
            } elseif (strpos($fileDirName, $dirName) === 0) {
              list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
              $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
            }
          }
        },
      ]),
    ];
  }

  /**
   * @return array A flat array of directory names with non-empty content.
   */
  public function getNonEmptyDirectories():array
  {
    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $pathArray = [];

    $generators = $this->getListingGenerators();
    /** @var ParticipantsStorageGenerator $generator */
    foreach ($generators as $generator) {
      if (!$generator->hasLeafNodes()) {
        continue;
      }
      $pathArray[implode(self::PATH_SEPARATOR, $generator->pathChain())] = [
        'mtime' => $generator->parentModificationTime(),
        'pathChain' => $generator->pathChain(),
      ];
    }
    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $pathArray;
  }

  /**
   * {@inheritdoc}
   *
   * We expose all found documents in the projectParticipantFieldsData(),
   * payments() and the debitMandates(). Changes including deletions are
   * tracked in dedicated fields of the ProjectParticipant and Musician
   * entity.
   */
  protected function findFiles(string $dirName):array
  {
    $dirName = self::normalizeDirectoryName($dirName);
    if (!empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $dirComponents = Util::explode(self::PATH_SEPARATOR, $dirName);

    /** @var Entities\DatabaseStorageFolder $folderDirEntry */
    $folderDirEntry = $this->rootFolder;
    if (empty($dirName) && empty($this->root)) {
      $this->files[$dirName] = [ '.' => $folderDirEntry ?? new DirectoryNode('.', new DateTimeImmutable('@1')) ];
      if (empty($folderDirEntry)) {
        return $this->files[$dirName];
      }
    } else {
      foreach ($dirComponents as $component) {
        $folderDirEntry = $folderDirEntry->getFolderByName($component);
        if (empty($folderDirEntry)) {
          throw new Exceptions\DatabaseEntityNotFoundException($this->l->t(
            'Unable to find directory entry for folder "%s".', $dirName
          ));
        }
      }
      $this->files[$dirName] = [ '.' => $folderDirEntry ];
    }

    $dirEntries = $folderDirEntry->getDirectoryEntries();

    /** @var Entities\DatabaseStorageDirEntry $dirEntry */
    foreach ($dirEntries as $dirEntry) {

      $baseName = $dirEntry->getName();
      $fileName = $this->buildPath($dirName . self::PATH_SEPARATOR . $baseName);
      list('basename' => $baseName) = self::pathInfo($fileName);

      if ($dirEntry instanceof Entities\DatabaseStorageFolder) {
        /** @var Entities\DatabaseStorageFolder $dirEntry */
        // add a directory entry
        $baseName .= self::PATH_SEPARATOR;
        $this->files[$dirName][$baseName] = $dirEntry;
      } else {
        /** @var Entities\DatabaseStorageFile $dirEntry */
        $this->files[$dirName][$baseName] = $dirEntry;
      }
    }

    return $this->files[$dirName];
  }

  /**
   * In order to aid database migration processes this funtction will remain
   * here until the base layout of the database is changed.
   *
   * @param string $dirName
   *
   * @return DateTimeInterface
   *
   * @see \OCA\CAFEVDB\Maintenance\Migrations\RootDirectoryEntries
   */
  public function getDirectoryModificationTimeForMigration(string $dirName):DateTimeInterface
  {
    $isTreasurer = $this->isTreasurer;
    $this->isTreasurer = true;
    $result = $this->getDirectoryModificationTime($dirName);
    $this->isTreasurer = $isTreasurer;

    return $result;
  }

  /**
   * In order to aid database migration processes this funtction will remain
   * here until the base layout of the database is changed.
   *
   * @param string $dirName
   *
   * @return array
   *
   * @see \OCA\CAFEVDB\Maintenance\Migrations\RootDirectoryEntries
   */
  public function findFilesForMigration(string $dirName):array
  {
    $dirName = self::normalizeDirectoryName($dirName);
    if (false && !empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $this->files[$dirName] = [
      '.' => new DirectoryNode('.', new DateTimeImmutable('@1')),
    ];

    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $dirInfos = $this->getListingGenerators();

    $depth = count(Util::explode(self::PATH_SEPARATOR, $dirName));
    $subDirMatch = false;
    /** @var ParticipantsStorageGenerator $dirInfo */
    foreach ($dirInfos as $dirInfo) {
      $pathChain = $dirInfo->pathChain();
      $subDirectoryPath = '';
      while (strpos($dirName, $subDirectoryPath) === 0 && !empty($pathChain)) {
        $subDirectoryPath .= self::PATH_SEPARATOR . array_shift($pathChain);
        list('dirname' => $subDirectoryPath) = self::pathInfo($this->buildPath($subDirectoryPath . self::PATH_SEPARATOR . '_'));
      }
      if (strpos($dirName, $subDirectoryPath) === 0 && empty($pathChain)) {
        if ($subDirMatch && $dirInfo->skipDepthIfOther() > 0 && $depth >= $dirInfo->skipDepthIfOther()) {
          continue;
        }
        // create leaf entries
        $dirInfo->createLeafNodes($dirName, $subDirectoryPath);
      } elseif (strpos($subDirectoryPath, $dirName) === 0) {
        // create parent
        $modificationTime = $dirInfo->parentModificationTime();
        $hasLeafNodes = $dirInfo->hasLeafNodes();
        if (!empty($modificationTime) && !$hasLeafNodes) {
          // just update the time-stamp of the parent in order to trigger
          // update after deleting records.
          $this->files[$dirName]['.']->updateModificationTime($modificationTime);
        } elseif (!empty($modificationTime) || $hasLeafNodes) {
          // add a directory entry
          list($baseName) = explode(self::PATH_SEPARATOR, substr($subDirectoryPath, strlen($dirName)), 1);
          if (empty($this->files[$dirName][$baseName])) {
            $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
          } else {
            $this->files[$dirName][$baseName]->updateModificationTime($modificationTime);
          }
        }
        $subDirMatch = true;
      }
    }

    if (!empty($modificationTime)) {
      // update the time-stamp of the parent.
      $this->files[$dirName]['.']->updateModificationTime($modificationTime);
    }

    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $this->files[$dirName];
  }

  /**
   * {@inheritdoc}
   */
  protected function getStorageModificationDateTime():DateTimeInterface
  {
    return self::ensureDate(empty($this->rootFolder) ? null : $this->rootFolder->getUpdated());
  }

  /** {@inheritdoc} */
  public function getId()
  {
    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    $result = parent::getId()
      . implode(self::PATH_SEPARATOR, [
        $this->project->getName(), 'participants', $this->participant->getMusician()->getUserIdSlug(),
      ])
      . self::PATH_SEPARATOR;
    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    return $result;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

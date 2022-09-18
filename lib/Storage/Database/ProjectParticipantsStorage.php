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

namespace OCA\CAFEVDB\Storage\Database;

use \DateTimeImmutable;

use OCP\EventDispatcher\IEventDispatcher;

// FIXME: those are not public, but ...
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

  /** @var OrganizationalRolesService */
  private $organizationalRolesService;

  /** @var array */
  private $files = [];

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);
    $this->participant = $params['participant'];
    $this->project = $this->participant->getProject();
    $this->musician = $this->participant->getMusician();
    $this->projectService = $this->di(ProjectService::class);
    $this->organizationalRolesService = $this->di(OrganizationalRolesService::class);
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
  protected function fileFromFileName(?string $name)
  {
    $name = $this->buildPath($name);
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);

    if (empty($this->files[$dirName])) {
      $this->findFiles($dirName);
    }

    return ($this->files[$dirName][$baseName]
            ?? ($this->files[$dirName][$baseName . self::PATH_SEPARATOR]
                ?? null));
  }

  /**
   * {@inheritdoc}
   *
   * We expose all found documents in the projectParticipantFieldsData(),
   * payments() and the debitMandates(). Changes including deletions are
   * tracked in dedicated fields of the ProjectParticipant and Musician
   * entity.
   */
  protected function findFiles(string $dirName)
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

    $userId = $this->entityManager->getUserId();
    $isTreasurer = $this->organizationalRolesService->isTreasurer($userId, allowGroupAccess: true);

    $dirInfos = [
      [
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getSupportingDocumentsFolderName(),
          $this->getReceivablesFolderName(),
        ],
        'parentModificationTime' => fn() => $this->participant->getParticipantFieldsDataChanged(),
        'hasLeafNodes' => fn() => !$this->participant->getParticipantFieldsData()->forAll(
          fn($key, Entities\ProjectParticipantFieldDatum $fieldDatum) => empty($fieldDatum->getSupportingDocument())
        ),
        'createLeafNodes' => function($subDirectoryPath) use ($dirName) {
          $modificationTime = $this->participant->getParticipantFieldsDataChanged();
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
      ],
      [
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getSupportingDocumentsFolderName(),
          $this->getBankTransactionsFolderName(),
        ],
        'parentModificationTime' => fn() => $this->musician->getPaymentsChanged(),
        'hasLeafNodes' => fn() => $isTreasurer && !$this->musician->getPayments()->forAll(
          fn($key, Entities\CompositePayment $compositePayment) => (
            $compositePayment->getProjectPayments()->matching(
              DBUtil::criteriaWhere([ 'project' => $this->project ])
            )->count() == 0
            || empty($compositePayment->getSupportingDocument())
          )
        ),
        'createLeafNodes' => function($subDirectoryPath) use ($dirName) {
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
      ],
      [
        'skipDepthIfOther' => -1,
        'pathChain' => [
          $this->getDebitMandatesFolderName(),
        ],
        'parentModificationTime' => fn() => $this->musician->getSepaDebitMandatesChanged(),
        'hasLeafNodes' => function() use ($isTreasurer) {
          if (!$isTreasurer) {
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
        'createLeafNodes' => function($subDirectoryPath) use ($dirName) {
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
      ],
      [
        'skipDepthIfOther' => 1,
        'pathChain' => [],
        'parenModificationTime' => fn() => $this->participant->getParticipantFieldsDataChanged(),
        'hasLeafNodes' => fn() => true, // don't care top-level
        'createLeafNodes' => function($subDirectoryPath) use ($dirName) {

          $modificationTime = $this->participant->getParticipantFieldsDataChanged();

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
      ],
    ];

    $depth = count(Util::explode(self::PATH_SEPARATOR, $dirName));
    $subDirMatch = false;
    foreach ($dirInfos as $dirInfo) {
      $pathChain = $dirInfo['pathChain'];
      $subDirectoryPath = '';
      while (strpos($dirName, $subDirectoryPath) === 0 && !empty($pathChain)) {
        $subDirectoryPath .= self::PATH_SEPARATOR . array_shift($pathChain);
        list('dirname' => $subDirectoryPath) = self::pathInfo($this->buildPath($subDirectoryPath . self::PATH_SEPARATOR . '_'));
      }
      if (strpos($dirName, $subDirectoryPath) === 0 && empty($pathChain)) {
        if ($subDirMatch && $dirInfo['skipDepthIfOther'] > 0 && $depth >= $dirInfo['skipDepthIfOther']) {
          continue;
        }
        // create leaf entries
        $dirInfo['createLeafNodes']($subDirectoryPath);
      } elseif (strpos($subDirectoryPath, $dirName) === 0) {
        // create parent
        $modificationTime = $dirInfo['parentModificationTime']();
        $hasLeafNodes = $dirInfo['hasLeafNodes']();
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
  protected function getStorageModificationDateTime():\DateTimeInterface
  {
    $mtime = max(
      self::ensureDate($this->participant->getParticipantFieldsDataChanged()),
      self::ensureDate($this->musician->getSepaDebitMandatesChanged()),
      self::ensureDate($this->musician->getPaymentsChanged()),
    );

    return $mtime;
  }

  /** {@inheritdoc} */
  public function getId()
  {
    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    $result = $this->appName()
      . '::'
      . 'database-storage/'
      . $this->project->getName()
      . 'participants/'
      . $this->participant->getMusician()->getUserIdSlug()
      . self::PATH_SEPARATOR;
    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    return $result;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

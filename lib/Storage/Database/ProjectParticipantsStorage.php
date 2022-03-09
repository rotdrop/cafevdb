<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, 2022, Claus-Justus Heine <himself@claus-justus-heine.de>
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
      $filterState = $this->disableFilter('soft-deleteable');
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
      $filterState && $this->enableFilter('soft-deleteable');
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
    if (!empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $this->files[$dirName] = [
      '.' => new DirectoryNode('.', new \DateTimeImmutable('@0')),
    ];

    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter('soft-deleteable');

    $dirMatch = false;

    $userId = $this->entityManager->getUserId();
    if ($this->organizationalRolesService->isTreasurer($userId, allowGroupAccess: true)) {

      if (!$dirMatch) {
        // add supporting documents for composite payments (may belong to more
        // than one project, at least technically)

        $paymentRecordsDirectory = $this->getPaymentRecordsFolderName();
        list('dirname' => $fileDirName) = self::pathInfo($this->buildPath($paymentRecordsDirectory . self::PATH_SEPARATOR . '_'));

        if (strpos($fileDirName, $dirName) === 0) {
          $dirMatch = true;
          $payments = $this->musician->getPayments();
          if ($fileDirName != $dirName) {
            // parent directory, just add the subdirectory with proper mtime if there ever has been an entry

            $modificationTime = $this->musician->getPaymentsChanged();
            if (!empty($modificationTime) && $payments->count() == 0) {
              // just update the time-stamp of the parent
              $this->files[$dirName]['.']->updateModificationTime($modificationTime);
            } else if (!empty($modificationTime) || $payments->count() > 0) {
              // add a directory entry
              list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
              $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
            }
          } else {
            // payments-directory, loop over documents
            /** @var Entities\CompositePayment $compositePayment */
            foreach ($payments as $compositePayment) {
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
              $fileName = $this->buildPath($paymentRecordsDirectory . self::PATH_SEPARATOR . $baseName);
              list('basename' => $baseName) = self::pathInfo($fileName);
              $this->files[$dirName][$baseName] = $file;
            }
          }
        }
      }

      if (!$dirMatch) {

        // also link in the debit-mandate hard-copies in their own sub-folder
        // subDir: $this->l->t('DebitMandates'),
        // Link in all project-related and the general debit-mandates

        $debitMandatesDirectory = $this->getDebitMandatesFolderName();
        list('dirname' => $fileDirName) = self::pathInfo($this->buildPath($debitMandatesDirectory . self::PATH_SEPARATOR . '_'));

        if (strpos($fileDirName, $dirName) === 0) {
          $dirMatch = true;

          $membersProjectId = $this->getClubMembersProjectId();
          $projectId = $this->project->getId();

          /** @var Entities\SepaDebitMandate $debitMandate */
          $projectMandates = $this->musician->getSepaDebitMandates()->filter(
            function($debitMandate) use ($membersProjectId, $projectId) {
              $mandateProjectId = $debitMandate->getProject()->getId();
              return $mandateProjectId === $membersProjectId || $mandateProjectId === $projectId;
            });

          if ($fileDirName != $dirName) {
            // parent directory, just add the subdirectory with proper mtime if there ever has been an entry

            $modificationTime = $this->musician->getSepaDebitMandatesChanged();
            if (!empty($modificationTime) && $projectMandates->count() == 0) {
              // just update the time-stamp of the parent
              $this->files[$dirName]['.']->updateModificationTime($modificationTime);
            } else if (!empty($modificationTime) || $projectMandates->count() > 0) {
              // add a directory entry
              list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
              $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
            }
          } else {

            /** @var Entities\SepaDebitMandate $debitMandate */
            foreach ($projectMandates as $debitMandate) {
              $file = $debitMandate->getWrittenMandate();
              if (empty($file)) {
                continue;
              }
              // enforce the "correct" file-name
              $extension = '.' . pathinfo($file->getFileName(), PATHINFO_EXTENSION);
              $baseName = $this->getDebitMandateFileName($debitMandate) . $extension;
              $fileName = $this->buildPath($debitMandatesDirectory . self::PATH_SEPARATOR . $baseName);
              list('basename' => $baseName) = self::pathInfo($fileName);
              $this->files[$dirName][$baseName] = $file;
            }
          }
        }
      }
    }

    if (!$dirMatch) {
      $modificationTime = $this->participant->getParticipantFieldsDataChanged();

      /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
      foreach ($this->participant->getParticipantFieldsData() as $fieldDatum) {

        $fileInfo = $this->projectService->participantFileInfo($fieldDatum);
        if (empty($fileInfo)) {
          continue;
        }

        $fileName = $this->buildPath($fileInfo['pathName']);
        list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
        if ($fileDirName == $dirName) {
          $this->files[$dirName][$baseName] = $fileInfo['file'];
        } else if (strpos($fileDirName, $dirName) === 0) {
          list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
          $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
        }
      }
      if (!empty($modificationTime)) {
        // update the time-stamp of the parent
        $this->files[$dirName]['.']->updateModificationTime($modificationTime);
      }
    }

    $filterState && $this->enableFilter('soft-deleteable');

    return $this->files[$dirName];
  }

  /**
   * {@inheritdoc}
   *
   */
  protected function getStorageModificationDateTime():\DateTimeInterface
  {
    $mtime = max(
      $this->participant->getParticipantFieldsDataChanged(),
      $this->musician->getSepaDebitMandatesChanged(),
      $this->musician->getPaymentsChanged()
    );
    return $mtime;
  }

  /** {@inheritdoc} */
  public function getId()
  {
    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter('soft-deleteable');
    $result = $this->appName()
      . '::'
      . 'database-storage/'
      . $this->project->getName()
      . 'participants/'
      . $this->participant->getMusician()->getUserIdSlug()
      . self::PATH_SEPARATOR;
    $filterState && $this->enableFilter('soft-deleteable');
    return $result;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

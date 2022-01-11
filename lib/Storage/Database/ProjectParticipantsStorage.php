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
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldDataType as FieldType;
use OCA\CAFEVDB\Database\Doctrine\DBAL\Types\EnumParticipantFieldMultiplicity as FieldMultiplicity;
use OCA\CAFEVDB\Common\Util;
use OCA\CAFEVDB\Events;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class ProjectParticipantsStorage extends Storage
{
  /** @var Entities\Project */
  private $project;

  /** @var Entities\ProjectParticipant */
  private $participant;

  /** @var ProjectService */
  private $projectService;

  /** @var array */
  private $files = [];

  public function __construct($params)
  {
    parent::__construct($params);
    $this->participant = $params['participant'];
    $this->project = $this->participant->getProject();
    $this->projectService = $this->di(ProjectService::class);
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
   */
  protected function findFiles(string $dirName)
  {
    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter('soft-deleteable');

    $dirName = self::normalizeDirectoryName($dirName);
    $this->files[$dirName] = [];
    /** @var Entities\ProjectParticipantFieldDatum $fieldDatum */
    foreach ($this->participant->getParticipantFieldsData() as $fieldDatum) {
      if ($fieldDatum->isDeleted()) {
        continue;
      }
      /** @var Entities\ProjectParticipantField $field */
      $field = $fieldDatum->getField();
      if ($field->isDeleted()) {
        continue;
      }
      $dataType = $field->getDataType();
      switch ($dataType) {
      case FieldType::DB_FILE:
        $fileId = (int)$fieldDatum->getOptionValue();
        $file = $this->filesRepository->find($fileId);
        break;
      case FieldType::SERVICE_FEE:
        $file = $fieldDatum->getSupportingDocument();
        break;
      default:
        $file = null;
      }
      /** @var Entities\File $file */
      if (!empty($file)) {

        $dbFileName = $file->getFileName();
        $fieldName = $field->getName();

        if ($field->getMultiplicity() == FieldMultiplicity::SIMPLE) {
          // construct the file-name from the field-name
          $fileName = $this->projectService->participantFilename($fieldName, $this->project, $this->participant->getMusician()) . '.' . pathinfo($dbFileName, PATHINFO_EXTENSION);
        } else {
          // construct the file-name from the option label if non-empty or the file-name of the DB-file
          /** @var Entities\ProjectParticipantFieldDataOption $fieldOption */
          $fieldOption = $fieldDatum->getDataOption();
          $optionLabel = $fieldOption->getLabel();
          if (!empty($optionLabel)) {
            $baseName = $this->projectService->participantFilename($fieldOption->getLabel(), $this->project, $this->participant->getMusician()) . '.' . pathinfo($dbFileName, PATHINFO_EXTENSION);
          } else {
            $baseName = basename($dbFileName);
          }
          $fileName = $fieldName . self::PATH_SEPARATOR . $baseName;
        }
        $fileName = $this->buildPath($fileName);
        list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
        if ($fileDirName == $dirName) {
          $this->files[$dirName][$baseName] = $file;
        } else if (strpos($fileDirName, $dirName) === 0) {
          list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
          $this->files[$dirName][$baseName] = $baseName;
        }
      }
    }

    // also link in the debit-mandate hard-copies in their own sub-folder
    // subDir: $this->l->t('DebitMandates'),
    // Link in all project-related and the general debit-mandates

    $debitMandatesDirectory = $this->l->t('DebitMandates');
    $membersProjectId = $this->getClubMembersProjectId();
    $projectId = $this->participant->getProject()->getId();

    /** @var Entities\SepaDebitMandate $debitMandate */
    foreach ($this->participant->getMusician()->getSepaDebitMandates() as $debitMandate) {
      $mandateProjectId = $debitMandate->getProject()->getId();
      if ($mandateProjectId !== $membersProjectId && $mandateProjectId !== $projectId) {
        continue;
      }
      $file = $debitMandate->getWrittenMandate();
      if (empty($file)) {
        continue;
      }
      // enforce the "correct" file-name
      $dbFileName = $file->getFileName();
      $baseName = $debitMandate->getMandateReference() . '.' . pathinfo($dbFileName, PATHINFO_EXTENSION);
      $fileName = $this->buildPath($debitMandatesDirectory . self::PATH_SEPARATOR . $baseName);
      list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
      if ($fileDirName == $dirName) {
        $this->files[$dirName][$baseName] = $file;
      } else if (strpos($fileDirName, $dirName) === 0) {
        list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
        $this->files[$dirName][$baseName] = $baseName;
      }
    }

    $filterState && $this->enableFilter('soft-deleteable');

    return $this->files[$dirName];
  }

  /**
   * {@inheritdoc}
   */
  protected function getStorageModificationTime():int
  {
    return $this->getDirectoryModificationTime('')->getTimestamp();
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

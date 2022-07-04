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

// FIXME: those are not public, but ...
use OC\Files\Storage\Common as AbstractStorage;
use OC\Files\Storage\PolyFill\CopyDirectory;
use OCP\EventDispatcher\IEventDispatcher;

use Icewind\Streams\CallbackWrapper;
use Icewind\Streams\CountWrapper;
use Icewind\Streams\IteratorDirectory;

use OCA\CAFEVDB\Service\ProjectService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Repositories;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class ProjectBalanceSupportingDocumentsStorage extends Storage
{
  /** @var ProjectService */
  private $projectService;

  /** @var Entities\Project */
  private $project;

  /** @var Repositories\ProjectBalanceSupportingDocumentsRepository */
  private $supportingDocumentsRepository;

  /** @var array */
  private $files = [];

  public function __construct($params)
  {
    parent::__construct($params);
    $this->project = $params['project'];
    $this->projectService = $this->di(ProjectService::class);
    $this->transactionsRepository = $this->getDatabaseRepository(Entities\ProjectBalanceSupportingDocument::class);
    /** @var IEventDispatcher $eventDispatcher */
    $eventDispatcher = $this->di(IEventDispatcher::class);
    $eventDispatcher->addListener(Events\EntityManagerBoundEvent::class, function(Events\EntityManagerBoundEvent $event) {
      $this->logDebug('Entity-manager shoot down, re-fetching cached entities.');
      // the mount provider currently disables soft-deleteable filter ...
      $filterState = $this->disableFilter('soft-deleteable');
      try {
        $projectId = $this->project->getId();
        $this->clearDatabaseRepository();
        $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
        $this->transactionsRepository = $this->getDatabaseRepository(Entities\ProjectBalanceSupportingDocument::class);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      $filterState && $this->enableFilter('soft-deleteable');
    });
  }

  /**
   * {@inheritdoc}
   */
  protected function fileFromFileName(string $name)
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
    $dirName = self::normalizeDirectoryName($dirName);
    if (!empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $this->files[$dirName] = [
      '.' => new DirectoryNode('.', new \DateTimeImmutable('@1')),
    ];

    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter('soft-deleteable');

    $documents = $this->project->getFinancialBalanceSupportingDocuments();

    /** @var Entities\ProjectBalanceSupportingDocument $document */
    foreach ($documents as $document) {

      $documentFiles = $document->getDocuments();

      $documentFileName = sprintf('%s-%03d', $this->project->getName(), $document->getSequence());
      $fileDirName = self::pathInfo($this->buildPath($documentFileName));
      if (empty($dirName) || str_starts_with($fileDirName, $dirName)) {
        if ($fileDirName != $dirName) {
          // parent directory, just add the subdirectory with proper mtime
          // if there ever has been an entry
          $modificationTime = $this->project->getFinancialBalanceSupportingDocumentsChanged();
          if (!empty($modificationTime) && $documentFiles->count() == 0) {
            // just update the time-stamp of the parent
            $this->files[$dirName]['.']->updateModificationTime($modificationTime);
            } else if (!empty($modificationTime || $documentFiles->count() > 0)) {
            // add a directory entry
            list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
            $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
          }
        } else {
          // add entries for all files in the directory
          /** @var Entities\EncryptedFile $file */
          foreach ($documentFiles as $file) {
            // enforce the "correct" file-name
            $baseName = pathinfo($file->getFileName(), PATHINFO_BASENAME);
            $fileName = $this->buildPath($documentFileName . self::PATH_SEPARATOR . $baseName);
            list('basename' => $baseName) = self::pathInfo($fileName);
            $this->files[$dirName][$baseName] = $file;
          }
        }
        break; // stop after first matching directory
      }
    }

    $filterState && $this->enableFilter('soft-deleteable');

    return $this->files[$dirName];
  }

  protected function getStorageModificationDateTime():?\DateTimeInterface
  {
    return $this->project->getFinancialBalanceSupportingDocumentsChanged();
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return $this->appName()
      . '::'
      . 'database-storage/finance/balances/projects/' . $this->project->getName()
      . self::PATH_SEPARATOR;
  }

  public function isUpdatable($path) {
    return $path != '' && $this->file_exists($path);
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

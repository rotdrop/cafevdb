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
      $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
      try {
        $projectId = $this->project->getId();
        $this->clearDatabaseRepository();
        $this->project = $this->getDatabaseRepository(Entities\Project::class)->find($projectId);
        $this->transactionsRepository = $this->getDatabaseRepository(Entities\ProjectBalanceSupportingDocument::class);
      } catch (\Throwable $t) {
        $this->logException($t);
      }
      $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function fileFromFileName(string $name)
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

  private function setFileNameCache(string $name, $node)
  {
    $name = $this->buildPath($name);
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);

    $this->files[$dirName][$baseName] = $node;
  }

  private function unsetFileNameCache(string $name)
  {
    $name = $this->buildPath($name);
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);

    if (isset($this->files[$dirName][$baseName])) {
      unset($this->files[$dirName][$baseName]);
    } else if (isset($this->files[$dirName][$baseName . self::PATH_SEPARATOR ])) {
      unset($this->files[$dirName][$baseName . self::PATH_SEPARATOR]);
    }
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
    if (empty($dirName)) {
      $modificationTime = $this->project->getFinancialBalanceSupportingDocumentsChanged();
      $this->files[$dirName]['.']->updateModificationTime($modificationTime);
    }

    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $documents = $this->project->getFinancialBalanceSupportingDocuments();


    /** @var Entities\ProjectBalanceSupportingDocument $document */
    foreach ($documents as $document) {

      $documentFileName = sprintf('%s-%03d', $this->project->getName(), $document->getSequence());

      // $this->logInfo('DOCUMENT ' . $documentFileName);

      list('dirname' => $fileDirName) = self::pathInfo($this->buildPath($documentFileName) . self::PATH_SEPARATOR . '_');
      // $this->logInfo('FILE DIR NAME ' . $fileDirName);

      if (empty($dirName) || str_starts_with($fileDirName, $dirName)) {
        if ($fileDirName != $dirName) {
          // add a directory entry, $dirName is actually just the root ''
          $modificationTime = $document->getDocumentsChanged();
          list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
          $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
        } else {
          // add entries for all files in the directory
          $documentFiles = $document->getDocuments();
          /** @var Entities\EncryptedFile $file */
          foreach ($documentFiles as $file) {
            // enforce the "correct" file-name
            $baseName = pathinfo($file->getFileName(), PATHINFO_BASENAME);
            $fileName = $this->buildPath($documentFileName . self::PATH_SEPARATOR . $baseName);
            list('basename' => $baseName) = self::pathInfo($fileName);
            // $this->logInfo('ADD ' . $dirName . ' || ' . $baseName);
            $this->files[$dirName][$baseName] = $file;
          }
          break; // stop after first matching directory
        }
      }
    }

    $filterState && $this->enableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    return $this->files[$dirName];
  }

  protected function getStorageModificationDateTime():?\DateTimeInterface
  {
    return $this->project->getFinancialBalanceSupportingDocumentsChanged();
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return parent::getId()
      . implode(self::PATH_SEPARATOR, [
        'finance', 'balances', 'projects', $this->project->getName(),
      ])
      . self::PATH_SEPARATOR;
  }

  public function isUpdatable($path) {
    $result = $this->file_exists($path);
    return $result;
  }

  /**
   * Return the sequence number if the given path refers to a document
   * container directory, otherwise return null.
   */
  private function isContainerDirectory($path):?int
  {
    if (preg_match('|^/?' . $this->project->getName() . '-' . '(\d{3})/?$|', $path, $matches)) {
      return $matches[1];
    }
    return null;
  }

  public function mkdir($path)
  {
    $sequence = $this->isContainerDirectory($path);
    if ($sequence === null) {
      return false;
    }
    try {
      /** @var Entities\ProjectBalanceSupportingDocument $documentContainer */
      $documentContainer = (new Entities\ProjectBalanceSupportingDocument)
        ->setProject($this->project)
        ->setSequence($sequence);
      $this->getDatabaseRepository(Entities\ProjectBalanceSupportingDocument::class)->persist($documentContainer);
      $this->flush();

      // update the local cache
      list('basename' => $baseName, 'dirname' => $dirName) = self::pathinfo($path);
      $this->setFileNameCache($path, new DirectoryNode($baseName, $documentContainer->getDocumentsChanged()));
    } catch (\Throwable $t) {
      $this->logException($t);
      return false;
    }

    return true;
  }

  public function rmdir($path)
  {
    $sequence = $this->isContainerDirectory($path);
    if ($sequence === null) {
      return false;
    }

    $this->entityManager->beginTransaction();
    try {
      $entityReference = $this->entityManager->getReference(
        Entities\ProjectBalanceSupportingDocument::class, [
          'project' => $this->project,
          'sequence' => $sequence,
        ]);
      $this->entityManager->remove($entityReference);
      $this->flush();

      $this->entityManager->commit();

    } catch (\Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    // update the local cache
    $this->unsetFileNameCache($path);

    return true;
  }

  private function findContainer(int $sequence):?Entities\ProjectBalanceSupportingDocument
  {
    return $this->getDatabaseRepository(Entities\ProjectBalanceSupportingDocument::class)
      ->find([ 'project' => $this->project, 'sequence' => $sequence ]);
  }

  /**
   * Rename nodes inside the same storage, we have two "legal" cases:
   *
   * a) both paths are a top-level directory, then just change the sequence
   * number of the supporting document, if both paths obey the allowed naming
   * scheme.
   *
   * b) both paths refer to files inside top-level directories, then either
   * just rename the file or move it to the other document container.
   */
  public function rename($path1, $path2)
  {
    $path1 = $this->buildPath($path1);
    $path2 = $this->buildPath($path2);
    list('dirname' => $dirName1, 'basename' => $baseName1) = self::pathinfo($path1);
    list('dirname' => $dirName2, 'basename' => $baseName2) = self::pathinfo($path2);

    $sequence1 = $this->isContainerDirectory($path1);
    $sequence2 = $this->isContainerDirectory($path2);

    if (($sequence1 === null) !== ($sequence2 === null)) {
      // illegal, regular files can only reside inside the top-level directories.
      return false;
    }

    try {
      if ($sequence1 !== null && $sequence2 !== null) {
        /** @var DirectoryNode $directory */
        $directory = $this->fileFromFileName($path1);
        $containerEntity = $this->findContainer($sequence1);
        if (empty($directory) || empty($containerEntity)) {
          return false;
        }

        // a little tricky as sequence belongs to the identifiers, hence we
        // have to create a new entity and delete the old one.

        $this->entityManager->beginTransaction();

        $renamedContainer = new Entities\ProjectBalanceSupportingDocument($this->project, $sequence2);

        $oldDocuments = $containerEntity->getDocuments();
        $newDocuments = $renamedContainer->getDocuments();
        foreach ($oldDocuments as $document) {
          $newDocuments->add($document);
          $oldDocuments->removeElement($document);
        }
        $this->flush();

        $this->persist($renamedContainer);
        $this->entityManager->remove($containerEntity);
        $this->flush();

        $this->entityManager->commit();

        // update our local files cache
        $directory->name = $baseName2;
        $this->setFileNameCache($path2, $directory);
      } else if ($sequence1 === null && $sequence2 === null) {
        /** @var Entities\EncryptedFile $file */
        $file = $this->fileFromFileName($path1);
        if (empty($file)) {
          return false;
        }
        if ($sequence1 === $sequence2) {
          // rename inside same directory just changes the name
          $file->setFileName($baseName2); // actually rename
          $this->flush();
        } else {
          // move document to other container
        }
        $this->flush(); // commit the changes

        // update our local files cache
        $this->setFileNameCache($path2, $file);
      }

      // update our local files cache
      $this->unsetFileNameCache($path1);

    } catch (\Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    return true;
  }

  public function touch($path, $mtime = null)
  {
    if ($this->is_dir($path)) {
      return false;
    }
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathinfo($path);
    $sequence = $this->isContainerDirectory($dirName);
    if ($sequence === false) {
      return false;
    }
    $file = $this->fileFromFileName($path);
    try {
      if (empty($file)) {
        $containerEntity = $this->findContainer($sequence);
        if (empty($containerEntity)) {
          return false;
        }
        $file = new Entities\EncryptedFile($baseName, '', '');
        if ($mtime !== null) {
          $file->setCreated($mtime);
        }
        $containerEntity->addDocument($file);
      }
      if ($mtime !== false) {
        $file->setUpdated($mtime);
      }
      $this->persist($file);
      $this->flush();

      $this->setFileNameCache($path, $file);
    } catch (\Throwable $t) {
      $this->logException($t);
      return false;
    }

    return true;
  }

  public function unlink($path)
  {
    if ($this->is_dir($path)) {
      return false;
    }
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathinfo($path);
    /** @var Entities\EncryptedFile $file */
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      return false;
    }
    $documentContainer = $file->getProjectBalanceSupportingDocument();
    if (empty($documentContainer)) {
      return false;
    }

    try {
      $documentContainer->removeDocument($file); // depends on orphanRemoval=true
      $file->setProjectBalanceSupportingDocument(null);

      $safeToDelete = true;

      // break the link to the supporting documents of payments
      /** @var Entities\CompositePayment $compositePayment */
      foreach ($documentContainer->getCompositePayments() as $compositePayment) {
        if ($file->getId() == $compositePayment->getSupportingDocument()->getId()) {
          $safeToDelete = false;
          $compositePayment->setProjectBalanceSupportingDocument(null);
        }
      }
      /** @var Entities\ProjectPayment $projectPayment */
      foreach ($documentContainer->getProjectPayments() as $projectPayment) {
        $supportingDocument = $projectPayment->getReceivable() ? $projectPayment->getReceivable()->getSupportingDocument() : null;
        if ($supportingDocument && $supportingDocument->getId() == $file->getId()) {
          $safeToDelete = false;
          $projectPayment->setProjectBalanceSupportingDocument(null);
        }
      }
      if ($safeToDelete) {
        // remove, unused by payments.
        $this->entityManager->remove($file);
      }
      $this->flush();

      $this->unsetFileNameCache($path);
    } catch (\Throwable $t) {
      $this->logException($t);
      return false;
    }

    return true;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

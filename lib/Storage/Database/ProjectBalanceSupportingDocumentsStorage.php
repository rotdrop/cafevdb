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

use \DateTimeImmutable;

// F I X M E: those are not public, but ...
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
use OCA\CAFEVDB\Exceptions;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class ProjectBalanceSupportingDocumentsStorage extends Storage
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /** @var ProjectService */
  private $projectService;

  /** @var Entities\Project */
  private $project;

  /** @var array */
  private $files = [];

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);
    $this->project = $params['project'];
    $this->projectService = $this->di(ProjectService::class);
    $this->diretoriesRepository = $this->getDatabaseRepository(Entities\DatabaseStorageDirectory::class);
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
        $this->diretoriesRepository = $this->getDatabaseRepository(Entities\DatabaseStorageDirectory::class);
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

  /**
   * Insert a "cache" entry.
   *
   * @param string $name Path-name.
   *
   * @param DirectoryNode|Entities\EncryptedFile $node File-system node.
   */
  private function setFileNameCache(string $name, $node):void
  {
    $name = $this->buildPath($name);
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);

    $this->files[$dirName][$baseName] = $node;
  }

  /**
   * Remove a "cache" entry.
   *
   * @param string $name Path-name.
   */
  private function unsetFileNameCache(string $name):void
  {
    $name = $this->buildPath($name);
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);

    if (isset($this->files[$dirName][$baseName])) {
      unset($this->files[$dirName][$baseName]);
    } elseif (isset($this->files[$dirName][$baseName . self::PATH_SEPARATOR ])) {
      unset($this->files[$dirName][$baseName . self::PATH_SEPARATOR]);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function findFiles(string $dirName):array
  {
    // $this->logInfo('FIND FILES IN ' . $dirName);

    $dirName = self::normalizeDirectoryName($dirName);
    if (!empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $this->files[$dirName] = [
      '.' => new DirectoryNode('.', new DateTimeImmutable('@1')),
    ];
    if (empty($dirName)) {
      $modificationTime = $this->project->getFinancialBalanceSupportingDocumentsChanged();
      $this->files[$dirName]['.']->updateModificationTime($modificationTime);
    }

    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $documents = $this->project->getFinancialBalanceSupportingDocuments();

    // $this->logInfo('NUMBER OF DOCUMENTS FOUND ' . count($documents));

    /** @var Entities\DatabaseStorageDirectory $documentFolder */
    foreach ($documents as $documentFolder) {

      $documentFileName = $documentFolder->getName();

      // $this->logInfo('DOCUMENT ' . $documentFileName);

      list('dirname' => $fileDirName) = self::pathInfo($this->buildPath($documentFileName) . self::PATH_SEPARATOR . '_');
      // $this->logInfo('FILE DIR NAME ' . $fileDirName);

      if (empty($dirName) || str_starts_with($fileDirName, $dirName)) {
        if ($fileDirName != $dirName) {
          // add a directory entry, $dirName is actually just the root ''
          $modificationTime = $documentFolder->getUpdated();
          list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
          $this->files[$dirName][$baseName] = new DirectoryNode($baseName, $modificationTime);
        } else {
          // add entries for all files in the directory
          $documentFiles = $documentFolder->getDocuments();
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

  /**  {@inheritdoc} */
  protected function getStorageModificationDateTime():\DateTimeInterface
  {
    return self::ensureDate($this->project->getFinancialBalanceSupportingDocumentsChanged());
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

  /**  {@inheritdoc} */
  public function isUpdatable($path)
  {
    $result = $this->file_exists($path);
    return $result;
  }

  /**
   * Return the sequence number if the given path refers to a document
   * container directory, otherwise return null.
   *
   * @param string $path File-system path.
   *
   * @return null|int
   */
  private function isContainerDirectory(string $path):?int
  {
    if (preg_match('|^/?' . $this->project->getName() . '-' . '(\d{3})/?$|', $path, $matches)) {
      return $matches[1];
    }
    return null;
  }

  /** {@inheritdoc} */
  public function mkdir($path)
  {
    $sequence = $this->isContainerDirectory($path);
    if ($sequence === null) {
      return false;
    }

    $this->entityManager->beginTransaction();
    try {
      /** @var Entities\DatabaseStorageDirectory $parent */
      $parent = $this->project->getFinancialBalanceDocumentsFolder();
      if (empty($parent)) {
        $parent = (new Entities\DatabaseStorageDirectory)
          ->setStorageId($this->getId());
        $this->project->setFinancialBalanceDocumentsFolder($parent);
        $this->persist($parent);
        $this->flush();
      }
      /** @var Entities\DatabaseStorageDirectory $documentContainer */
      $documentContainer = (new Entities\DatabaseStorageDirectory)
        ->setName($this->makeContainerPath($sequence))
        ->setParent($this->project->getFinancialBalanceDocumentsFolder());
      $parent->getDatabaseStorageDirectories()->add($documentContainer);
      $this->persist($documentContainer);
      $this->flush();

      $this->entityManager->commit();

      // update the local cache
      list('basename' => $baseName,) = self::pathinfo($path);
      $this->setFileNameCache($path, new DirectoryNode($baseName, $documentContainer->getDocumentsChanged()));
    } catch (\Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    return true;
  }

  /** {@inheritdoc} */
  public function rmdir($path)
  {
    $sequence = $this->isContainerDirectory($path);
    if ($sequence === null) {
      return false;
    }

    $this->entityManager->beginTransaction();
    try {
      $entity = $this->findBy([
        'parent' => $this->project->getFinancialBalanceDocumentsFolder()->getId(),
        'name' => $path
      ]);
      if (!empty($entity)) {
        $this->entityManager->remove($entity);
        $this->flush();
      }

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

  /**
   * Find the container data-base entity for the given sequence number.
   *
   * @param string $name Name of the container directory.
   *
   * @return null|Entities\DatabaseStorageDirectory
   */
  private function findContainer(string $name):?Entities\DatabaseStorageDirectory
  {
    // $this->logInfo('SEARCH CONTAINER ' . $name . ' parent ' .  $this->project->getFinancialBalanceDocumentsFolder()->getStorageId() . '@' . $this->project->getFinancialBalanceDocumentsFolder()->getId());
    return $this->findOneBy([
      'parent' => $this->project->getFinancialBalanceDocumentsFolder(),
      'name' => trim($name, self::PATH_SEPARATOR),
    ]);
  }

  /**
   * {@inheritdoc}
   *
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
    // list('dirname' => $dirName1, 'basename' => $baseName1) = self::pathinfo($path1);
    // list('dirname' => $dirName2, 'basename' => $baseName2) = self::pathinfo($path2);
    $baseName2 = self::pathInfo($path2, PATHINFO_BASENAME);

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
        $containerEntity = $this->findContainer($path1);
        if (empty($directory) || empty($containerEntity)) {
          return false;
        }

        // a little tricky as sequence belongs to the identifiers, hence we
        // have to create a new entity and delete the old one.

        $this->entityManager->beginTransaction();

        $renamedContainer = (new Entities\DatabaseStorageDirectory)
          ->setParent($containerEntity->getParent())
          ->setName($this->makeContainerPath($sequence2));

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
      } elseif ($sequence1 === null && $sequence2 === null) {
        /** @var Entities\EncryptedFile $file */
        $file = $this->fileFromFileName($path1);
        if (empty($file)) {
          return false;
        }
        if ($sequence1 === $sequence2) {

          $this->entityManager->beginTransaction();

          // rename inside same directory just changes the name
          $file->setFileName($baseName2); // actually rename
          $this->flush();

          $this->entityManager->commit();

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

  /** {@inheritdoc} */
  public function touch($path, $mtime = null)
  {
    $this->logInfo('TOUCH ' . $path);
    if ($this->is_dir($path)) {
      return false;
    }
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathinfo($path);
    $sequence = $this->isContainerDirectory($dirName);
    if ($sequence === false) {
      return false;
    }
    $file = $this->fileFromFileName($path);
    $this->entityManager->beginTransaction();
    try {
      if (empty($file)) {
        $containerEntity = $this->findContainer($dirName);
        if (empty($containerEntity)) {
          $this->logInfo('NO CONTAINER ENTITY FOR ' . $sequence);
          return false;
        }
        $file = new Entities\EncryptedFile($baseName, '', '');
        if ($mtime !== null) {
          $file->setCreated($mtime);
        }
        $this->persist($file);
        $this->flush();
        $containerEntity->addDocument($file);
      }
      if ($mtime !== false) {
        $file->setUpdated($mtime);
      }
      $this->persist($file);
      $this->flush();

      $this->entityManager->commit();

      $this->setFileNameCache($path, $file);

    } catch (\Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    return true;
  }

  /**
   * Parse the given path and return the project name and the supporting
   * document sequence number.
   *
   * @param string $path
   *
   * @return array
   * ```
   * [ 'projectName' => NAME, 'sequence' => SEQUENCE, ]
   * ```
   */
  private static function parsePath(string $path):array
  {
    $pathInfo = self::pathInfo($path);
    if (empty($pathInfo['dirname']) && empty($pathInfo['filename'])) {
      return [ 'projectName' => null, 'sequence' => null ];
    } elseif (empty($pathInfo['dirname'])) {
      $slug = trim($pathInfo['filename'], self::PATH_SEPARATOR);
    } else {
      $slug = trim($pathInfo['dirname'], self::PATH_SEPARATOR);
    }
    list($projectName, $sequence) = explode('-', $slug);
    return [ 'projectName' => $projectName, 'sequence' => $sequence ];
  }

  /**
   * @param int $seqence
   *
   * @return string The container name PROJECTNAME-NNN
   */
  private function makeContainerPath(int $sequence):string
  {
    return $this->project->getName() . '-' . sprintf('%03d', $sequence);
  }

  /** {@inheritdoc} */
  public function unlink($path)
  {
    if ($this->is_dir($path)) {
      return false;
    }
    /** @var Entities\EncryptedFile $file */
    $file = $this->fileFromFileName($path);
    if (empty($file)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Unable to find database entity for path "%s".', $path)
      );
    }
    // $this->logInfo('PATH ' . print_r(self::parsePath($path), true));
    $containerName = dirname($path);

    // filter the file's document containers, should not be so many ...
    $containers = $file->getDatabaseStorageDirectories()->filter(
      fn(Entities\DatabaseStorageDirectory $container) => $container->getParent() == $this->project->getFinancialBalanceDocumentsFolder() && $container->getName() == $containerName
    );
    if (count($containers) != 1) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Unable to find document container for path "%s".', $path)
      );
    }
    $documentContainer = $containers->first();

    try {
      $documentContainer->removeDocument($file);

      // break the link to the supporting documents of payments
      /** @var Entities\CompositePayment $compositePayment */
      foreach ($documentContainer->getCompositePayments() as $compositePayment) {
        if ($file->getId() == $compositePayment->getSupportingDocument()->getId()) {
          $compositePayment->setBalanceDocumentsFolder(null);
        }
      }
      /** @var Entities\ProjectPayment $projectPayment */
      foreach ($documentContainer->getProjectPayments() as $projectPayment) {
        $supportingDocument = $projectPayment->getReceivable() ? $projectPayment->getReceivable()->getSupportingDocument() : null;
        if ($supportingDocument && $supportingDocument->getId() == $file->getId()) {
          $projectPayment->setBalanceDocumentsFolder(null);
        }
      }

      if ($file->getNumberOfLinks() == 0) {
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

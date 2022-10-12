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
use OCA\CAFEVDB\Constants;

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
    $this->directoriesRepository = $this->getDatabaseRepository(Entities\DatabaseStorageDirEntry::class);
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
        $this->directoriesRepository = $this->getDatabaseRepository(Entities\DatabaseStorageDirEntry::class);
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
    if ($name == self::PATH_SEPARATOR) {
      $dirName = '';
      $baseName = '.';
    } else {
      list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);
    }

    // $this->logInfo('SEARCH DIR ENTRY FOR ' . $name . ' || ' . $dirName . ' // ' . $baseName);

    if (empty($this->files[$dirName])) {
      $this->findFiles($dirName);
    }

    $dirEntry = ($this->files[$dirName][$baseName]
                 ?? ($this->files[$dirName][$baseName . self::PATH_SEPARATOR]
                     ?? null));

    return $dirEntry;
  }

  /**
   * Insert a "cache" entry.
   *
   * @param string $name Path-name.
   *
   * @param Entities\DatabaseStorageDirEntry $node File-system node.
   *
   * @return void
   */
  private function setFileNameCache(string $name, Entities\DatabaseStorageDirEntry $node):void
  {
    $name = $this->buildPath($name);
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathInfo($name);

    $this->files[$dirName][$baseName] = $node;
  }

  /**
   * Remove a "cache" entry.
   *
   * @param string $name Path-name.
   *
   * @return void
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

    /** @var Entities\DatabaseStorageFolder $folderDirEntry */

    if (empty($dirName)) {
      $folderDirEntry = $this->project->getFinancialBalanceDocumentsFolder();
      $this->files[$dirName]['.'] = $folderDirEntry ?? new DirectoryNode('.', new DateTimeImmutable('@1'));
      if (empty($folderDirEntry)) {
        return $this->files[$dirName];
      }
    } else {
      $folderDirEntry = $this->fileFromFileName($dirName);
      $this->files[$dirName]['.'] = $folderDirEntry;
    }

    // the mount provider currently disables soft-deleteable filter ...
    $filterState = $this->disableFilter(EntityManager::SOFT_DELETEABLE_FILTER);

    $dirEntries = $folderDirEntry->getDirectoryEntries();

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

  /**
   * Ensure that the top-level root directory exists as database entity.
   *
   * @return Entities\DatabaseStorageFolder
   */
  private function ensureRootFolder():Entities\DatabaseStorageFolder
  {
    /** @var Entities\DatabaseStorageFolder $root */
    $root = $this->project->getFinancialBalanceDocumentsFolder();
    if (empty($root)) {
      $root = (new Entities\DatabaseStorageFolder)
        ->setName('')
        ->setParent(null);
      $this->project->setFinancialBalanceDocumentsFolder($root);
      $this->persist($root);
      $this->flush();
    }
    return $root;
  }

  /** {@inheritdoc} */
  public function mkdir($path)
  {
    $sequence = $this->isContainerDirectory($path);
    if ($sequence === null) {
      return false;
    }
    $baseName = self::pathinfo($path, PATHINFO_BASENAME);

    $this->entityManager->beginTransaction();
    try {
      $parent = $this->ensureRootFolder();

      /** @var Entities\DatabaseStorageFolder $documentContainer */
      $documentContainer = $parent->addSubFolder($baseName);
      $this->persist($documentContainer);
      $this->flush();

      $this->entityManager->commit();

      // update the local cache
      $this->setFileNameCache($path, $documentContainer);
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

    /** @var Entities\DatabaseStorageFolder $dirEntry */
    $dirEntry = $this->fileFromFileName($path);
    if (empty($dirEntry)) {
      return false;
    }

    $this->entityManager->beginTransaction();
    try {
      $dirEntry->setParent(null);
      $this->entityManager->remove($dirEntry);
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

  // /**
  //  * @param string $baseName
  //  *
  //  * @return bool
  //  */
  // private static function isReadMe(string $baseName):bool
  // {
  //   // Readme.md.ocTransferId950400209.part
  //   return strtolower($baseName) == strtolower(Constants::README_NAME)
  //     || (str_starts_with(strtolower($baseName), strtolower(Constants::README_NAME))
  //         && str_ends_with($baseName, '.part'));
  // }

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
    // $this->logInfo('P1 - P2 ' . $path1 . ' - ' . $path2);

    $path1 = $this->buildPath($path1);
    $path2 = $this->buildPath($path2);
    list('dirname' => $dirName1, /* 'basename' => $baseName1 */) = self::pathinfo($path1);
    list('dirname' => $dirName2, 'basename' => $baseName2) = self::pathinfo($path2);
    $baseName2 = self::pathInfo($path2, PATHINFO_BASENAME);

    $sequence1 = $this->isContainerDirectory($path1);
    $sequence2 = $this->isContainerDirectory($path2);

    if (($sequence1 === null) !== ($sequence2 === null)) {
      // balance folder may only be rename to balance folders
      return false;
    }

    /** @var Entities\DatabaseStorageDirEntry $dirEntry */
    $dirEntry = $this->fileFromFileName($path1);
    if (empty($dirEntry)) {
      // $this->logInfo('NO DIR ENTRY FOR ' . $path1);
      return false;
    }
    if ($dirName1 != $dirName2) {
      $parent2 = $this->fileFromFileName($dirName2);
      if (empty($parent2)) {
        // $this->logInfo('NO PARENT2 for ' . $path2);
        return false;
      }
    }

    $this->entityManager->beginTransaction();
    try {
      $dirEntry->setName($baseName2);
      if (!empty($parent2)) {
        $dirEntry->setParent($parent2);
      }

      $this->flush();

      $this->entityManager->commit();

      // update our local files cache
      $this->setFileNameCache($path2, $dirEntry);
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
    if ($this->is_dir($path)) {
      // $this->logInfo('IS DIR ' . $path);
      return false;
    }
    list('basename' => $baseName, 'dirname' => $dirName) = self::pathinfo($path);

    if ($this->isContainerDirectory($path) !== null) {
      // $this->logInfo('CONTAINER DIR CHCEK ' . $path);
      return false;
    }

    // $isReadMe = $this->isReadMe($baseName);
    // $sequence = $this->isContainerDirectory($dirName);
    // if ($sequence === false && !$isReadMe) {
    //   // $this->logInfo('NO SEQUENCE  ' . $path);
    //   return false;
    // }

    /** @var Entities\DatabaseStorageFile $dirEntry */
    $dirEntry = $this->fileFromFileName($path);
    $this->entityManager->beginTransaction();
    try {
      if (empty($dirEntry)) {
        $parent = $this->fileFromFileName($dirName);
        if (empty($parent)) {
          // $this->logInfo('NO CONTAINER ENTITY FOR ' . $sequence);
          return false;
        }
        $file = new Entities\EncryptedFile($baseName, '', '');
        if ($mtime !== null) {
          $file->setCreated($mtime);
        }
        $this->persist($file);
        $this->flush();
        $dirEntry = $parent->addDocument($file, $baseName);
        if ($mtime !== null) {
          $dirEntry->setCreated($mtime);
        }
      }
      if ($mtime !== false) {
        $dirEntry->setUpdated($mtime);
        $dirEntry->getFile()->setUpdated($mtime);
      }
      $this->flush();

      $this->entityManager->commit();

      $this->setFileNameCache($path, $dirEntry);

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
  public function unlink($path)
  {
    if ($this->is_dir($path)) {
      return false;
    }
    /** @var Entities\DatabaseStorageFile $dirEntry */
    $dirEntry = $this->fileFromFileName($path);
    if (empty($dirEntry)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Unable to find database entity for path "%s".', $path)
      );
    }
    if ($dirEntry instanceof Entities\DatabaseStorageFolder) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Path "%s" is a directory.', $path)
      );
    }

    $parent = $dirEntry->getParent();
    if (empty($parent)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('Unable to find document container for path "%s".', $path)
      );
    }
    $file = $dirEntry->getFile();
    if (empty($file)) {
      throw new Exceptions\DatabaseStorageException(
        $this->l->t('The directory entry "%s" is not linked to a file.', $path)
      );
    }

    $this->entityManager->beginTransaction();
    try {
      $dirEntry->setParent(null);
      $dirEntry->setFile(null);
      $this->entityManager->remove($dirEntry);

      // break the link to the supporting documents of payments
      // @todo Maybe better use a find-by instead of bloating the entities with too many associations.

      /** @var Repositories\CompositePaymentsRepository $repository */
      $repository = $this->entityManager->getRepository(Entities\CompositePayment::class);
      $compositePayments = $repository->findBy([
        'balanceDocumentsFolder' => $parent,
        'supportingDocument' => $file,
      ]);
      /** @var Entities\CompositePayment $compositePayment */
      foreach ($compositePayments as $compositePayment) {
        $compositePayment->setBalanceDocumentsFolder(null);
      }

      /** @var Repositories\ProjectPaymentsRepository $repository */
      $repository = $this->entityManager->getRepository(Entities\ProjectPayment::class);
      $projectPayments = $repository->findBy([
        'balanceDocumentsFolder' => $parent,
      ]);
      /** @var Entities\ProjectPayment $projectPayment */
      foreach ($projectPayments as $projectPayment) {
        $supportingDocument = $projectPayment->getReceivable() ? $projectPayment->getReceivable()->getSupportingDocument() : null;
        if ($supportingDocument == $file) {
          $projectPayment->setBalanceDocumentsFolder(null);
        }
      }

      if ($file->getNumberOfLinks() == 0) {
        $this->entityManager->remove($file);
      }
      $this->flush();

      $this->entityManager->commit();

      $this->unsetFileNameCache($path);
    } catch (\Throwable $t) {
      $this->logException($t);
      if ($this->entityManager->isTransactionActive()) {
        $this->entityManager->rollback();
      }
      return false;
    }

    return true;
  }
}

<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine  <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;
use OCA\CAFEVDB\Common\Util;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class BankTransactionsStorage extends Storage
{
  use \OCA\CAFEVDB\Traits\DateTimeTrait;

  /** @var \OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBulkTransactionsRepository */
  private $transactionsRepository;

  /** @var array */
  private $files = [];

  /** {@inheritdoc} */
  public function __construct($params)
  {
    parent::__construct($params);
    $this->transactionsRepository = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class);
    /** @var IEventDispatcher $eventDispatcher */
    $eventDispatcher = $this->di(IEventDispatcher::class);
    $eventDispatcher->addListener(Events\EntityManagerBoundEvent::class, function(Events\EntityManagerBoundEvent $event) {
      $this->logDebug('Entity-manager shoot down, re-fetching cached entities.');
      $this->clearDatabaseRepository();
      $this->transactionsRepository = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class);
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
   * {@inheritdoc}
   */
  protected function findFiles(string $dirName):array
  {
    $dirName = self::normalizeDirectoryName($dirName);
    if (!empty($this->files[$dirName])) {
      return $this->files[$dirName];
    }

    $this->files[$dirName] = [
      '.' => new DirectoryNode('.', new DateTimeImmutable('@1')),
    ];

    $directoryYear = (int)basename($dirName);
    if ($directoryYear >= 1000 && $directoryYear <= 9999) {
      $transactions = $this->transactionsRepository->findByCreationYear($directoryYear);
    } else {
      $transactions = $this->transactionsRepository->findAll();
    }

    $directories = [];

    /** @var Entities\SepaBulkTransaction $transaction */
    foreach ($transactions as $transaction) {

      // choose the createdAt field for the subdirectory name
      $year = $transaction->getCreated()->format('Y');
      list('dirname' => $fileDirName) = self::pathInfo($this->buildPath($year . self::PATH_SEPARATOR . '_'));
      if (strpos($fileDirName, $dirName) !== 0) {
        // not our directory
        continue;
      }
      $sepaTransactionData = $transaction->getSepaTransactionData();
      if ($fileDirName != $dirName) {
        // parent directory of year directory
        $modificationTime = $transaction->getSepaTransactionDataChanged();
        // should just be the year ...
        list($yearBaseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);

        if (!empty($modificationTime) && $sepaTransactionData->count() == 0) {
          // just update the timestamp of the parent
          $this->files[$dirName]['.']->updateModificationTime($modificationTime);
        } elseif (!empty($modificationTime) || $sepaTransactionData->count() > 0) {
          // add a directory entry
          if (empty($directories[$yearBaseName]) || $directories[$yearBaseName] < $modificationTime) {
            $directories[$yearBaseName] = $modificationTime;
          }
        }
      } else {
        // just inside the year sub-directory
        /** @var Entities\File $file */
        foreach ($sepaTransactionData as $file) {
          $fileName = $file->getFileName();
          $fileName = $year . self::PATH_SEPARATOR . $fileName;
          $fileName = $this->buildPath($fileName);
          list('basename' => $baseName) = self::pathInfo($fileName);
          $this->files[$dirName][$baseName] = $file;
        }
      }
    }
    foreach ($directories as $name => $mtime) {
      $this->files[$dirName][$name] = new DirectoryNode($name, $mtime);
    }

    return $this->files[$dirName];
  }

  /** {@inheritdoc} */
  protected function getStorageModificationDateTime():?\DateTimeInterface
  {
    return self::ensureDate($this->transactionsRepository->sepaTransactionDataModificationTime());
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return parent::getId()
      . implode(self::PATH_SEPARATOR, [
        'finance', 'transactions',
      ])
      . self::PATH_SEPARATOR;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

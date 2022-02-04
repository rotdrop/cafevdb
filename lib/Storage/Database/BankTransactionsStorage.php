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
  /** @var OCA\CAFEVDB\Database\Doctrine\ORM\Repositories\SepaBulkTransactionsRepository */
  private $transactionsRepository;

  /** @var array */
  private $files = [];

  public function __construct($params)
  {
    parent::__construct($params);
    $this->transactionsRepository = $this->getDatabaseRepository(Entities\SepaBulkTransaction::class);
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
    $this->files[$dirName] = [];
    $transactions = $this->transactionsRepository->findAll();

    // This is a hack around the task to track the deletion time of
    // objects.
    $databaseMTime = $this->getDatabaseRepository(Entities\LogEntry::class)->modificationTime();

    $directories = [];

    /** @var Entities\SepaBulkTransaction $transaction */
    foreach ($transactions as $transaction) {

      $modificationTime = $transaction->getSepaTransactionDataChanged()
        ?? $databaseMTime;

      /** @var Entities\File $file */
      foreach ($transaction->getSepaTransactionData() as $file) {
        // @todo For now generate sub-directories for every year. This should
        // perhaps be changed ...
        $fileName = $file->getFileName();
        if (preg_match('/^([0-9]{4})[0-9]{4}-[0-9]{6}/', $fileName, $matches)) {
          $year = $matches[1];
          $fileName = $year . self::PATH_SEPARATOR . $fileName;
        }
        $fileName = $this->buildPath($fileName);
        list('dirname' => $fileDirName, 'basename' => $baseName) = self::pathInfo($fileName);
        if ($fileDirName == $dirName) {
          $this->files[$dirName][$baseName] = $file;
        } else if (strpos($fileDirName, $dirName) === 0) {
          list($baseName) = explode(self::PATH_SEPARATOR, substr($fileDirName, strlen($dirName)), 1);
          if (empty($directories[$baseName]) || $directories[$baseName] < $modificationTime) {
            $directories[$baseName] = $modificationTime;
          }
        }
      }
    }
    foreach ($directories as $name => $mtime) {
      $this->files[$dirName][$name] = new DirectoryNode($name, $mtime);
    }

    $this->logDebug('FOUND ' . count($this->files[$dirName]) . ' entries for "' . $dirName . '"');

    return $this->files[$dirName];
  }

  protected function getStorageModificationDateTime():?\DateTimeInterface
  {
    return $this->getDatabaseRepository(Entities\LogEntry::class)->modificationTime();
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return $this->appName()
      . '::'
      . 'database-storage/finance/transactions'
      . self::PATH_SEPARATOR;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

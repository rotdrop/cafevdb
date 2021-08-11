<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine
 * @copyright 2011-2014, 2016, 2020, 2021, Claus-Justus Heine <himself@claus-justus-heine.de>
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

use OCA\CAFEVDB\Service\ConfigService;
use OCA\CAFEVDB\Database\EntityManager;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities;

/**
 * Storage implementation for data-base storage, including access to
 * encrypted entities.
 */
class Storage extends AbstractStorage
{
  use CopyDirectory;
  use \OCA\CAFEVDB\Traits\ConfigTrait;
  use \OCA\CAFEVDB\Traits\EntityManagerTrait;

  public function __construct($parameters)
  {
    $this->configService = \OC::$server->query(ConfigService::class);
    $this->l = $this->l10n();
    $this->entityManager = $this->di(EntityManager::class);
  }

  /** {@inheritdoc} */
  public function getId()
  {
    return 'ftp::' . $this->username . '@' . $this->host . '/' . $this->root;
  }

  public static function checkDependencies() {
    if (function_exists('ftp_login')) {
      return (true);
    } else {
      return ['ftp'];
    }
  }

  public function filemtime($path)
  {
    return 0;
  }

  public function filesize($path)
  {
    return false;
  }

  public function rmdir($path)
  {
    return false;
  }

  public function test() {
    return false;
  }

  public function stat($path) {
    return false;
  }

  public function file_exists($path)
  {
    return false;
  }

  public function unlink($path)
  {
    return false;
  }

  public function opendir($path)
  {
    return null;
  }

  public function mkdir($path)
  {
    return false;
  }

  public function is_dir($path)
  {
    return false;
  }

  public function is_file($path) {
    return false;
  }

  public function filetype($path)
  {
    return false;
  }

  public function fopen($path, $mode)
  {
    return false;
  }

  public function writeStream(string $path, $stream, int $size = null): int
  {
    $size = 0;
    return $size;
  }

  public function readStream(string $path)
  {
    return null;
  }

  public function touch($path, $mtime = null)
  {
    return false;
  }

  public function rename($path1, $path2)
  {
    return false;
  }

  public function getDirectoryContent($directory): \Traversable
  {
    yield null;
  }
}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

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

namespace OCA\CAFEVDB\Storage;

use OCP\IL10N;
use OCP\ILogger;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class AppStorage
{
  use \OCA\CAFEVDB\Traits\LoggerTrait;

  /** @var IAppData */
  private $appData;

  public function __construct(
    IAppData $appData
    , ILogger $logger
    , IL10N $l10n
  ) {
    $this->appData = $appData;
    $this->logger = $logger;
    $this->l = $l10n;
    if (!empty($this->userId)) {
      $this->userFolder = $this->rootFolder->getUserFolder($this->userId);
    }
  }

  /**
   * Get the folder with name $name
   *
   * @param string $name
   * @return ISimpleFolder
   * @throws NotFoundException
   * @throws \RuntimeException
   * @since 11.0.0
   */
  public function getFolder(string $name): ISimpleFolder
  {
    return $this->appData->getFolder($name);
  }

  /**
   * Get all the Folders
   *
   * @return ISimpleFolder[]
   * @throws NotFoundException
   * @throws \RuntimeException
   * @since 11.0.0
   */
  public function getDirectoryListing(): array
  {
    return $this->appData->getDirectoryListing();
  }

  /**
   * Create a new folder named $name
   *
   * @param string $name
   * @return ISimpleFolder
   * @throws NotPermittedException
   * @throws \RuntimeException
   * @since 11.0.0
   */
  public function newFolder(string $name): ISimpleFolder
  {
    return $this->appData->newFolder($name);
  }

  /**
   * Return a system-directory path to temporary storage.
   */
  public function getTemporaryDirectory()
  {
    return sys_get_temp_dir();
  }

}

// Local Variables: ***
// c-basic-offset: 2 ***
// indent-tabs-mode: nil ***
// End: ***

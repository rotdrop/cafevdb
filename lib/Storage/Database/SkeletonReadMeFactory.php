<?php
/**
 * Orchestra member, musician and project management application.
 *
 * CAFEVDB -- Camerata Academica Freiburg e.V. DataBase.
 *
 * @author Claus-Justus Heine <himself@claus-justus-heine.de>
 * @copyright 2024 Claus-Justus Heine
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

use DateTime;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use OCP\IConfig;
use OCP\IL10N;
use OCP\IUserSession;
use OCP\Files\File;

use OCA\CAFEVDB\AppInfo\AppL10N;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder;
use OCA\CAFEVDB\Service\AppMTimeService;
use OCA\CAFEVDB\Service\ToolTipsService;
use OCA\CAFEVDB\Storage\UserStorage;

/**
 * Generate in-memory README nodes, also look for files in the skeleton
 * subdirectory in the templates directory tree.
 */
class SkeletonReadMeFactory extends ReadMeFactory
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /**
   * @param ContainerInterface $appContainer
   *
   * @param IL10N $l
   *
   * @param AppL10N $appL10n
   *
   * @param string $appName
   *
   * @param LoggerInterface $logger
   *
   * @param IConfig $cloudConfig
   *
   * @param ToolTipsService $toolTipsService
   *
   * @param UserStorage $userStorage
   *
   * @param null|string $skeletonPath The starting point for the search.
   */
  public function __construct(
    ContainerInterface $appContainer,
    IL10N $l,
    AppL10N $appL10n,
    string $appName,
    LoggerInterface $logger,
    IConfig $cloudConfig,
    ToolTipsService $toolTipsService,
    protected UserStorage $userStorage,
    protected ?string $skeletonPath = null,
  ) {
    parent::__construct($appContainer, $l, $appL10n, $appName, $logger, $cloudConfig, $toolTipsService);
  }

  /** {@inheritdoc} */
  public function generateReadMe(DatabaseStorageFolder|EmptyDirectoryNode $parent, string $dirName):?InMemoryFileNode
  {
    if (isset($this->documentCache[$dirName])) {
      return $this->documentCache[$dirName];
    }
    $skeletonDir = $this->skeletonPath . ($dirName ? Constants::PATH_SEP . $dirName : '');
    $readMeFile = null;
    foreach ($this->getReadMeFileNames() as $fileName) {
      /** @var File $readMeFile */
      $readMeFile = $this->userStorage->getFile($skeletonDir . Constants::PATH_SEP . $fileName);
      if (!empty($readMeFile)) {
        break;
      }
    }
    if (empty($readMeFile)) {
      $node = parent::generateReadMe($parent, $dirName);
    } else {
      $node = new InMemoryFileNode(
        $parent,
        $this->getReadMeFileNames()[0],
        $readMeFile->getContent(),
        self::MIME_TYPE,
        (new DateTime)->setTimestamp($readMeFile->getMTime()),
      );
    }

    $this->documentCache[$dirName] = $node;

    return $node;
  }

  /**
   * @param string $skeletonPath
   *
   * @return ReadMeFactoryInterface $this
   */
  public function setSkeletonPath(string $skeletonPath):ReadMeFactoryInterface
  {
    $this->documentCache = [];

    $this->skeletonPath = $skeletonPath;

    return $this;
  }

  /**
   * @return string The currenttly set skeleton base directory.
   */
  public function getSkeletonPath():string
  {
    return $this->skeletonPath;
  }
}

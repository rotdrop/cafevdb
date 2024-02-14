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

use OCA\CAFEVDB\AppInfo\AppL10N;
use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder;
use OCA\CAFEVDB\Service\AppMTimeService;
use OCA\CAFEVDB\Service\ToolTipsService;

/**
 * Generate in-memory README nodes.
 */
class ReadMeFactory extends AbstractReadMeFactory
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  /**
   * @param string $appName
   *
   * @param LoggerInterface $logger
   *
   * @param IConfig $cloudConfig
   *
   * @param ContainerInterface $appContainer
   *
   * @param ToolTipsService $toolTipsService
   */
  public function __construct(
    ContainerInterface $appContainer,
    IL10N $l,
    AppL10N $appL10n,
    protected string $appName,
    protected LoggerInterface $logger,
    protected IConfig $cloudConfig,
    protected ToolTipsService $toolTipsService,
  ) {
    parent::__construct($appContainer, $l, $appL10n);
  }

  /**
   * @param DatabaseStorageFolder|EmptyDirectoryNode $parent
   *
   * @param string $dirName
   *
   * @return null|InMemoryFileNode
   */
  public function generateReadMe(DatabaseStorageFolder|EmptyDirectoryNode $parent, string $dirName):?InMemoryFileNode
  {
    $storageId = $parent->getStorage()->getStorageId();
    $content = $this->getDefaultReadMeContents($storageId, $dirName);
    if (empty($content)) {
      // no empty files
      return null;
    }
    $updated = (new DateTime)->setTimestamp(
      $this->cloudConfig->getAppValue($this->appName, AppMTimeService::L10N_MTIME_KEY, 1),
    );
    $node = new InMemoryFileNode($parent, $this->getReadMeFileNames()[0], $content, self::MIME_TYPE, $updated);

    return $node;
  }

  /**
   * Compute a lookup key for a possible default readme content.
   *
   * @param string $storageId
   *
   * @param string $dirName
   *
   * @return string
   */
  protected function getReadMeTooltipsKey(string $storageId, string $dirName = null):?string
  {
    if (!empty($dirName)) {
      $storageId .= Constants::PATH_SEP . trim($dirName, Constants::PATH_SEP);
    }
    $tooltipsKey = str_replace(Constants::PATH_SEP, ToolTipsService::SUB_KEY_SEP, $storageId);
    $tooltipsKey = trim(Storage::STORAGE_ID_TAG . ToolTipsService::SUB_KEY_SEP . $tooltipsKey, ToolTipsService::SUB_KEY_SEP);

    return $tooltipsKey;
  }

  /**
   * Recurse to the tooltips service and see if there is a default text for
   * the given storage.
   *
   * @param string $storageId
   *
   * @param string $dirName
   *
   * @return string
   */
  protected function getDefaultReadMeContents(string $storageId, string $dirName = null):?string
  {
    $tooltipsKey = $this->getReadMeTooltipsKey($storageId, $dirName);
    $contents = $this->toolTipsService->fetch($tooltipsKey, escape: false);

    $contents = str_replace(ToolTipsService::PARAGRAPH, '', $contents);

    return $contents;
  }
}

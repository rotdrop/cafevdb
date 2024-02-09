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

use Psr\Log\LoggerInterface;

use OCP\AppFramework\IAppContainer;
use OCP\IConfig;

use OCA\Text\Service\WorkspaceService;

use OCA\CAFEVDB\Constants;
use OCA\CAFEVDB\Database\Doctrine\ORM\Entities\DatabaseStorageFolder;
use OCA\CAFEVDB\Service\AppMTimeService;
use OCA\CAFEVDB\Storage\StorageUtil;
use OCA\CAFEVDB\Service\ToolTipsService;

/**
 * Generate in-memory README nodes.
 */
class ReadMeFactory
{
  use \OCA\CAFEVDB\Toolkit\Traits\LoggerTrait;

  public const MIME_TYPE = 'text/plain'; // markdown';

  /**
   * @var array
   *
   * A cache for possible names of the Readme.md. The first element is the
   * preferred localized name.
   */
  protected ?array $readMeFileNames = null;

  /**
   * @param string $appName
   *
   * @param LoggerInterface $logger
   *
   * @param IConfig $cloudConfig
   *
   * @param IAppContainer $appContainer
   *
   * @param ToolTipsService $toolTipsService
   */
  public function __construct(
    protected string $appName,
    protected LoggerInterface $logger,
    protected IConfig $cloudConfig,
    protected IAppContainer $appContainer,
    protected ToolTipsService $toolTipsService,
  ) {
  }

  /**
   * @param DatabaseStorageFolder|EmptyRootNode $parent
   *
   * @param string $dirName
   *
   * @return null|InMemoryFileNode
   */
  public function generateReadMe(DatabaseStorageFolder|EmptyRootNode $parent, string $dirName):?InMemoryFileNode
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

  /**
   * Possibly populate and return the array of possible "readMe"
   * variations. We try to recurse to the "text" app and come up with suitable
   * fallback if that fails.
   *
   * @return array<int, string>
   */
  public function getReadMeFileNames():array
  {
    if ($this->readMeFileNames === null) {
      try {
        /** @var WorkspaceService $workspaceService */
        $workspaceService = $this->appContainer->get(WorkspaceService::class);
        $this->readMeFileNames = $workspaceService->getSupportedFilenames();
      } catch (Throwable $t) {
        $this->readMeFileNames = array_map(
          fn($value) => $value . '.md', [
            $this->l->t('Readme'),
            $this->appL10n()->t('Readme'),
            'Readme.md',
            'README.md',
            'readme.md'
          ]);
      }
    }
    return $this->readMeFileNames;
  }

    /**
   * Check whether the given file is a ReadMe.md file.
   *
   * @param string $path
   *
   * @return bool
   */
  public function isReadMe(string $path):bool
  {
    $readMeFileNames = $this->getReadMeFileNames();
    $path = basename(StorageUtil::uploadBasename($path));
    foreach ($readMeFileNames as $readMeName) {
      if ($path == $readMeName) {
        return true;
      }
    }
    return false;
  }
}

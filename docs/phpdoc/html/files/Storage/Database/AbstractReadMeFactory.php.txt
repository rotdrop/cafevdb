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

use Throwable;

use Psr\Container\ContainerInterface;

use OCP\IL10N;

use OCA\Text\Service\WorkspaceService;

use OCA\CAFEVDB\AppInfo\AppL10N;
use OCA\CAFEVDB\Storage\StorageUtil;

/**
 * Abstract factory baseclass.
 */
abstract class AbstractReadMeFactory implements ReadMeFactoryInterface
{
  public const MIME_TYPE = 'text/plain'; // markdown';

  /**
   * @var array
   *
   * A cache for possible names of the Readme.md. The first element is the
   * preferred localized name.
   */
  protected ?array $readMeFileNames = null;

  /**
   * @param ContainerInterface $appContainer
   *
   * @param IL10N $l
   *
   * @param AppL10N $appL10n
   */
  public function __construct(
    protected ContainerInterface $appContainer,
    protected IL10N $l,
    protected AppL10N $appL10n,
  ) {
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
      } catch (Throwable/* $t */) {
        $this->readMeFileNames = array_map(
          fn($value) => $value . '.md', [
            $this->l->t('Readme'),
            $this->appL10n->t('Readme'),
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
